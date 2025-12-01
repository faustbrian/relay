<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Transport\Pool;

use Cline\Relay\Core\Connector;
use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;
use Cline\Relay\Support\Exceptions\GenericRequestException;
use Cline\Relay\Support\Exceptions\RequestException;
use Closure;
use Generator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException as GuzzleException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Pool as GuzzlePool;
use GuzzleHttp\Psr7\Request as Psr7Request;
use Psr\Http\Message\ResponseInterface;

use function array_key_exists;
use function assert;
use function http_build_query;
use function is_int;
use function is_string;
use function iterator_to_array;
use function json_encode;
use function mb_ltrim;
use function mb_rtrim;
use function sprintf;

/**
 * Request pool for concurrent execution.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Pool
{
    private int $concurrency = 5;

    private ?Closure $onResponse = null;

    private ?Closure $onError = null;

    private bool $lazy = false;

    private ?HandlerStack $handler = null;

    /**
     * @param Connector                  $connector The connector to use
     * @param array<int|string, Request> $requests  Array of requests (optionally keyed)
     */
    public function __construct(
        private readonly Connector $connector,
        private readonly array $requests,
    ) {}

    /**
     * Set a custom handler stack for testing.
     */
    public function withHandler(HandlerStack $handler): self
    {
        $this->handler = $handler;

        return $this;
    }

    /**
     * Set the concurrency limit.
     */
    public function concurrent(int $limit): self
    {
        $this->concurrency = $limit;

        return $this;
    }

    /**
     * Set the response callback.
     *
     * @param Closure(Response, Request, int|string): void $callback
     */
    public function onResponse(Closure $callback): self
    {
        $this->onResponse = $callback;

        return $this;
    }

    /**
     * Set the error callback.
     *
     * @param Closure(RequestException, Request, int|string): void $callback
     */
    public function onError(Closure $callback): self
    {
        $this->onError = $callback;

        return $this;
    }

    /**
     * Enable lazy iteration for memory-efficient processing.
     */
    public function lazy(): self
    {
        $this->lazy = true;

        return $this;
    }

    /**
     * Send all requests and get responses.
     *
     * @return array<int|string, Response>
     */
    public function send(): array
    {
        if ($this->lazy) {
            return iterator_to_array($this->iterate());
        }

        return $this->executePool();
    }

    /**
     * Iterate through responses as they complete.
     *
     * @return Generator<int|string, Response>
     */
    public function iterate(): Generator
    {
        foreach ($this->executePool() as $key => $response) {
            yield $key => $response;
        }
    }

    /**
     * Process each response with a callback.
     *
     * @param Closure(Response, int|string): void $callback
     */
    public function each(Closure $callback): void
    {
        foreach ($this->iterate() as $key => $response) {
            $callback($response, $key);
        }
    }

    /**
     * Execute the pool and return responses.
     *
     * @return array<int|string, Response>
     */
    private function executePool(): array
    {
        $options = [
            'timeout' => $this->connector->timeout(),
            'connect_timeout' => $this->connector->connectTimeout(),
        ];

        if ($this->handler instanceof HandlerStack) {
            $options['handler'] = $this->handler;
        }

        $client = new Client($options);

        $responses = [];
        $requestMap = [];

        // Create request generator
        $requestGenerator = function () use (&$requestMap): Generator {
            foreach ($this->requests as $key => $request) {
                $request->initialize();
                $this->connector->authenticate($request);

                $url = $this->buildUrl($request);
                $headers = $this->mergeHeaders($request);
                $body = $this->buildBody($request);

                $requestMap[$key] = $request;

                yield $key => new Psr7Request(
                    $request->method(),
                    $url,
                    $headers,
                    $body,
                );
            }
        };

        // Configure pool options
        $poolConfig = [
            'concurrency' => $this->concurrency,
            'fulfilled' => function ($psrResponse, $key) use (&$responses, &$requestMap): void {
                assert(is_int($key) || is_string($key));
                assert($psrResponse instanceof ResponseInterface);
                $request = $requestMap[$key];
                $response = new Response($psrResponse, $request);

                if ($this->onResponse instanceof Closure) {
                    ($this->onResponse)($response, $request, $key);
                }

                $responses[$key] = $response;
            },
            'rejected' => function ($reason, $key) use (&$responses, &$requestMap): void {
                assert(is_int($key) || is_string($key));
                $request = $requestMap[$key];

                if ($reason instanceof GuzzleException) {
                    $exception = GenericRequestException::fromGuzzleException($reason, $request);

                    if ($this->onError instanceof Closure) {
                        ($this->onError)($exception, $request, $key);
                    }

                    // Store exception response if available
                    $psrResponse = $reason->getResponse();

                    if ($reason->hasResponse() && $psrResponse instanceof ResponseInterface) {
                        $responses[$key] = new Response($psrResponse, $request);
                    }
                }
            },
        ];

        // Execute pool
        $pool = new GuzzlePool($client, $requestGenerator(), $poolConfig);
        $pool->promise()->wait();

        return $responses;
    }

    /**
     * Build the full URL for a request.
     */
    private function buildUrl(Request $request): string
    {
        $baseUrl = mb_rtrim($this->connector->resolveBaseUrl(), '/');
        $endpoint = mb_ltrim($request->endpoint(), '/');
        $url = sprintf('%s/%s', $baseUrl, $endpoint);

        $query = $request->allQuery();

        if ($query !== []) {
            $url .= '?'.http_build_query($query);
        }

        return $url;
    }

    /**
     * Merge connector and request headers.
     *
     * @return array<string, string>
     */
    private function mergeHeaders(Request $request): array
    {
        $headers = [
            ...$this->connector->defaultHeaders(),
            ...$request->allHeaders(),
        ];

        $contentType = $request->contentType();

        if ($contentType !== null) {
            $headers['Content-Type'] = $contentType;
        }

        if (!array_key_exists('Accept', $headers) && $contentType === 'application/json') {
            $headers['Accept'] = 'application/json';
        }

        return $headers;
    }

    /**
     * Build the request body.
     */
    private function buildBody(Request $request): ?string
    {
        $body = $request->body();

        if ($body === null) {
            return null;
        }

        $contentType = $request->contentType();

        return match ($contentType) {
            'application/json' => json_encode($body) ?: null,
            'application/x-www-form-urlencoded' => http_build_query($body),
            default => json_encode($body) ?: null,
        };
    }
}

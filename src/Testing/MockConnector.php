<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Testing;

use Cline\Relay\Core\Connector;
use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;
use Cline\Relay\Support\Exceptions\MockConnectorException;
use Closure;
use Override;

use function array_shift;
use function assert;
use function count;
use function end;
use function throw_if;

/**
 * Mock connector for testing that records requests and returns predefined responses.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class MockConnector extends Connector
{
    /** @var array<Closure|Response> */
    private array $responses = [];

    /** @var array<Request> */
    private array $sentRequests = [];

    /** @var array<array{request: Request, response: Response}> */
    private array $history = [];

    private bool $sequentialMode = true;

    public function baseUrl(): string
    {
        return 'https://mock.api.test';
    }

    /**
     * Add a response to be returned.
     *
     * @param Closure(Request): Response|Response $response
     */
    public function addResponse(Response|Closure $response): self
    {
        $this->responses[] = $response;

        return $this;
    }

    /**
     * Add multiple responses.
     *
     * @param array<Closure|Response> $responses
     */
    public function addResponses(array $responses): self
    {
        foreach ($responses as $response) {
            $this->addResponse($response);
        }

        return $this;
    }

    /**
     * Set a single response to be returned for all requests.
     */
    public function alwaysReturn(Response $response): self
    {
        $this->sequentialMode = false;
        $this->responses = [$response];

        return $this;
    }

    /**
     * Send a request and return the next mock response.
     */
    #[Override()]
    public function send(Request $request): Response
    {
        $this->sentRequests[] = $request;

        throw_if($this->responses === [], MockConnectorException::noResponsesConfigured());

        /** @var Closure|Response $response */
        $response = $this->sequentialMode
            ? array_shift($this->responses)
            : $this->responses[0];

        if ($response instanceof Closure) {
            $response = $response($request);
        }

        assert($response instanceof Response);

        // Attach the request to the response for DTO mapping
        $response = $response->withRequest($request);

        $this->history[] = [
            'request' => $request,
            'response' => $response,
        ];

        return $response;
    }

    /**
     * Get all sent requests.
     *
     * @return array<Request>
     */
    public function sentRequests(): array
    {
        return $this->sentRequests;
    }

    /**
     * Get the last sent request.
     */
    public function lastRequest(): ?Request
    {
        return $this->sentRequests !== [] ? end($this->sentRequests) : null;
    }

    /**
     * Get the request/response history.
     *
     * @return array<array{request: Request, response: Response}>
     */
    public function history(): array
    {
        return $this->history;
    }

    /**
     * Assert a request was sent.
     */
    public function assertSent(string $endpoint, ?string $method = null): void
    {
        foreach ($this->sentRequests as $request) {
            if ($request->endpoint() === $endpoint && ($method === null || $request->method() === $method)) {
                return;
                // Found matching request
            }
        }

        throw MockConnectorException::requestNotSent($endpoint, $method);
    }

    /**
     * Assert a request was not sent.
     */
    public function assertNotSent(string $endpoint, ?string $method = null): void
    {
        foreach ($this->sentRequests as $request) {
            if ($request->endpoint() !== $endpoint) {
                continue;
            }

            if ($method !== null && $request->method() !== $method) {
                continue;
            }

            throw MockConnectorException::unexpectedRequest($endpoint);
        }
    }

    /**
     * Assert the number of requests sent.
     */
    public function assertSentCount(int $count): void
    {
        $actual = count($this->sentRequests);

        throw_if($actual !== $count, MockConnectorException::requestCountMismatch($count, $actual));
    }

    /**
     * Assert no requests were sent.
     */
    public function assertNothingSent(): void
    {
        $this->assertSentCount(0);
    }

    /**
     * Clear all recorded requests and responses.
     */
    public function reset(): self
    {
        $this->responses = [];
        $this->sentRequests = [];
        $this->history = [];
        $this->sequentialMode = true;

        return $this;
    }

    /**
     * Get remaining response count.
     */
    public function remainingResponses(): int
    {
        return $this->sequentialMode ? count($this->responses) : -1;
    }
}

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Testing;

use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;
use Cline\Relay\Support\Exceptions\MockClientException;
use Closure;

use function array_key_exists;
use function array_shift;
use function class_exists;
use function count;
use function end;
use function fnmatch;
use function is_string;
use function mb_ltrim;
use function mb_rtrim;
use function preg_match;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function throw_if;

/**
 * Mock client for testing that intercepts requests and returns predefined responses.
 *
 * Supports global mocking for testing deep in application code.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class MockClient
{
    private static ?self $globalInstance = null;

    /** @var array<string, Closure|Fixture|Response> */
    private array $mappedResponses = [];

    /** @var array<Closure|Fixture|Response> */
    private array $sequenceResponses = [];

    /** @var array<Request> */
    private array $sentRequests = [];

    /** @var array<array{request: Request, response: Response}> */
    private array $history = [];

    private bool $sequentialMode = false;

    /**
     * Create a new MockClient instance.
     *
     * @param array<int|string, Closure|Fixture|Response> $responses
     */
    public function __construct(array $responses = [])
    {
        foreach ($responses as $key => $response) {
            if (is_string($key)) {
                $this->mappedResponses[$key] = $response;
            } else {
                $this->sequenceResponses[] = $response;
                $this->sequentialMode = true;
            }
        }
    }

    /**
     * Create a global mock client for testing.
     *
     * @param array<int|string, Closure|Fixture|Response> $responses
     */
    public static function global(array $responses = []): self
    {
        self::$globalInstance = new self($responses);

        return self::$globalInstance;
    }

    /**
     * Get the global mock client instance.
     */
    public static function getGlobal(): ?self
    {
        return self::$globalInstance;
    }

    /**
     * Check if a global mock client is active.
     */
    public static function hasGlobal(): bool
    {
        return self::$globalInstance instanceof self;
    }

    /**
     * Destroy the global mock client.
     */
    public static function destroyGlobal(): void
    {
        self::$globalInstance = null;
    }

    /**
     * Add a mapped response for a request class or URL pattern.
     */
    public function addResponse(string $key, Closure|Fixture|Response $response): self
    {
        $this->mappedResponses[$key] = $response;

        return $this;
    }

    /**
     * Add a sequence response.
     */
    public function addSequenceResponse(Closure|Fixture|Response $response): self
    {
        $this->sequenceResponses[] = $response;
        $this->sequentialMode = true;

        return $this;
    }

    /**
     * Resolve a response for a request.
     */
    public function resolve(Request $request, string $baseUrl): Response
    {
        $this->sentRequests[] = $request;

        $response = $this->findResponse($request, $baseUrl);

        if ($response instanceof Closure) {
            $response = $response($request);
        }

        if ($response instanceof Fixture) {
            $response = $response->resolve();
        }

        if (!$response instanceof Response) {
            throw MockClientException::invalidResponse($request::class);
        }

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
     *
     * @param Closure|string $callback Request class, endpoint, or callback
     */
    public function assertSent(Closure|string $callback): void
    {
        if ($callback instanceof Closure) {
            foreach ($this->history as $entry) {
                if ($callback($entry['request'], $entry['response']) === true) {
                    return;
                }
            }

            throw MockClientException::assertionFailed('No request matched the callback.');
        }

        // String can be request class or endpoint
        foreach ($this->sentRequests as $request) {
            if ($request::class === $callback || $request->endpoint() === $callback) {
                return;
            }
        }

        throw MockClientException::assertionFailed(sprintf("Request '%s' was not sent.", $callback));
    }

    /**
     * Assert a request was not sent.
     *
     * @param Closure|string $callback Request class, endpoint, or callback
     */
    public function assertNotSent(Closure|string $callback): void
    {
        if ($callback instanceof Closure) {
            foreach ($this->history as $entry) {
                if ($callback($entry['request'], $entry['response']) === true) {
                    throw MockClientException::assertionFailed('Request matched the callback but should not have been sent.');
                }
            }

            return;
        }

        foreach ($this->sentRequests as $request) {
            if ($request::class === $callback || $request->endpoint() === $callback) {
                throw MockClientException::assertionFailed(sprintf("Request '%s' was sent but should not have been.", $callback));
            }
        }
    }

    /**
     * Assert the number of requests sent.
     */
    public function assertSentCount(int $count, ?string $requestClass = null): void
    {
        if ($requestClass !== null) {
            $actual = 0;

            foreach ($this->sentRequests as $request) {
                if ($request::class !== $requestClass) {
                    continue;
                }

                ++$actual;
            }
        } else {
            $actual = count($this->sentRequests);
        }

        throw_if(
            $actual !== $count,
            MockClientException::assertionFailed(sprintf('Expected %d requests, but %d were sent.', $count, $actual)),
        );
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
        $this->sentRequests = [];
        $this->history = [];

        return $this;
    }

    /**
     * Get remaining sequence response count.
     */
    public function remainingResponses(): int
    {
        return count($this->sequenceResponses);
    }

    /**
     * Find a matching response for a request.
     */
    private function findResponse(Request $request, string $baseUrl): Closure|Fixture|Response
    {
        // Try sequential mode first
        if ($this->sequentialMode && $this->sequenceResponses !== []) {
            return array_shift($this->sequenceResponses);
        }

        // Try request class match
        $requestClass = $request::class;

        if (array_key_exists($requestClass, $this->mappedResponses)) {
            return $this->mappedResponses[$requestClass];
        }

        // Try URL pattern match
        $fullUrl = mb_rtrim($baseUrl, '/').'/'.mb_ltrim($request->endpoint(), '/');

        foreach ($this->mappedResponses as $pattern => $response) {
            if ($this->matchesUrlPattern($pattern, $fullUrl)) {
                return $response;
            }
        }

        throw MockClientException::noMatchingResponse($requestClass, $fullUrl);
    }

    /**
     * Check if a URL matches a pattern.
     *
     * Supports wildcards (*) for flexible matching.
     */
    private function matchesUrlPattern(string $pattern, string $url): bool
    {
        // Skip class names
        if (class_exists($pattern)) {
            return false;
        }

        // Exact match
        if ($pattern === $url) {
            return true;
        }

        // Wildcard pattern (using fnmatch)
        if (str_contains($pattern, '*')) {
            return fnmatch($pattern, $url);
        }

        // URL contains pattern
        if (str_contains($url, $pattern)) {
            return true;
        }

        // Regex pattern (starts with /)
        return str_starts_with($pattern, '/') && preg_match($pattern, $url) === 1;
    }
}

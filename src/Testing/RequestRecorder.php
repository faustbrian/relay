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
use Cline\Relay\Support\Exceptions\RequestRecorderException;

use function array_column;
use function count;
use function end;
use function mb_strtoupper;
use function microtime;
use function throw_if;

/**
 * Records requests and responses for testing assertions.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class RequestRecorder
{
    /** @var array<array{request: Request, response: null|Response, timestamp: float}> */
    private array $records = [];

    /**
     * Record a request/response pair.
     */
    public function record(Request $request, ?Response $response = null): void
    {
        $this->records[] = [
            'request' => $request,
            'response' => $response,
            'timestamp' => microtime(true),
        ];
    }

    /**
     * Get all records.
     *
     * @return array<array{request: Request, response: null|Response, timestamp: float}>
     */
    public function all(): array
    {
        return $this->records;
    }

    /**
     * Get all recorded requests.
     *
     * @return array<Request>
     */
    public function requests(): array
    {
        return array_column($this->records, 'request');
    }

    /**
     * Get all recorded responses.
     *
     * @return array<null|Response>
     */
    public function responses(): array
    {
        return array_column($this->records, 'response');
    }

    /**
     * Get the last recorded request.
     */
    public function lastRequest(): ?Request
    {
        return $this->records !== [] ? end($this->records)['request'] : null;
    }

    /**
     * Get the last recorded response.
     */
    public function lastResponse(): ?Response
    {
        return $this->records !== [] ? end($this->records)['response'] : null;
    }

    /**
     * Get the count of recorded requests.
     */
    public function count(): int
    {
        return count($this->records);
    }

    /**
     * Check if any requests were recorded.
     */
    public function hasRecords(): bool
    {
        return $this->records !== [];
    }

    /**
     * Find requests matching a filter.
     *
     * @param  callable(Request): bool $filter
     * @return array<Request>
     */
    public function findRequests(callable $filter): array
    {
        $matches = [];

        foreach ($this->records as $record) {
            if ($filter($record['request'])) {
                $matches[] = $record['request'];
            }
        }

        return $matches;
    }

    /**
     * Find requests to a specific endpoint.
     *
     * @return array<Request>
     */
    public function findByEndpoint(string $endpoint): array
    {
        return $this->findRequests(
            fn (Request $r): bool => $r->endpoint() === $endpoint,
        );
    }

    /**
     * Find requests with a specific method.
     *
     * @return array<Request>
     */
    public function findByMethod(string $method): array
    {
        return $this->findRequests(
            fn (Request $r): bool => $r->method() === mb_strtoupper($method),
        );
    }

    /**
     * Clear all records.
     */
    public function clear(): void
    {
        $this->records = [];
    }

    /**
     * Assert a request was recorded matching the filter.
     *
     * @param callable(Request): bool $filter
     */
    public function assertRecorded(callable $filter): void
    {
        $matches = $this->findRequests($filter);

        throw_if($matches === [], RequestRecorderException::noMatchingRequest());
    }

    /**
     * Assert a request to endpoint was recorded.
     */
    public function assertRecordedEndpoint(string $endpoint): void
    {
        $matches = $this->findByEndpoint($endpoint);

        throw_if($matches === [], RequestRecorderException::noRequestToEndpoint($endpoint));
    }
}

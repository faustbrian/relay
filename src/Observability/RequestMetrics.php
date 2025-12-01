<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Observability;

use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;

use function json_encode;
use function mb_strlen;

/**
 * Metrics collected for a request/response cycle.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class RequestMetrics
{
    public function __construct(
        public string $method,
        public string $endpoint,
        public int $status,
        public float $duration,
        public int $requestSize,
        public int $responseSize,
        public ?string $traceId = null,
        public ?string $spanId = null,
        public bool $cached = false,
        public int $retryCount = 0,
        public ?string $errorType = null,
    ) {}

    /**
     * Create from request and response.
     */
    public static function fromRequestResponse(
        Request $request,
        Response $response,
        int $retryCount = 0,
    ): self {
        return new self(
            method: $request->method(),
            endpoint: $request->endpoint(),
            status: $response->status(),
            duration: $response->duration() ?? 0.0,
            requestSize: mb_strlen(json_encode($request->body() ?? []) ?: ''),
            responseSize: mb_strlen($response->body()),
            traceId: $response->traceId(),
            spanId: $response->spanId(),
            cached: $response->fromCache(),
            retryCount: $retryCount,
            errorType: $response->failed() ? self::getErrorType($response->status()) : null,
        );
    }

    /**
     * Convert to array for logging/reporting.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'method' => $this->method,
            'endpoint' => $this->endpoint,
            'status' => $this->status,
            'duration_ms' => $this->duration,
            'request_size_bytes' => $this->requestSize,
            'response_size_bytes' => $this->responseSize,
            'trace_id' => $this->traceId,
            'span_id' => $this->spanId,
            'cached' => $this->cached,
            'retry_count' => $this->retryCount,
            'error_type' => $this->errorType,
        ];
    }

    /**
     * Get the error type based on status code.
     */
    private static function getErrorType(int $status): string
    {
        return match (true) {
            $status >= 500 => 'server_error',
            $status === 429 => 'rate_limited',
            $status === 401 => 'unauthorized',
            $status === 403 => 'forbidden',
            $status === 404 => 'not_found',
            $status === 408 => 'timeout',
            $status >= 400 => 'client_error',
            default => 'unknown',
        };
    }
}

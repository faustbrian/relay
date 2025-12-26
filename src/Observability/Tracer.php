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
use Cline\Relay\Support\Contracts\TracerInterface;

use function array_key_exists;
use function bin2hex;
use function count;
use function explode;
use function max;
use function random_bytes;
use function sprintf;

/**
 * Default tracer implementation using random IDs.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Tracer implements TracerInterface
{
    private ?string $traceId = null;

    private ?string $parentSpanId = null;

    /**
     * Start a new trace.
     */
    public function startTrace(): string
    {
        $this->traceId = $this->generateId(32);
        $this->parentSpanId = null;

        return $this->traceId;
    }

    /**
     * Get the current trace ID.
     */
    public function traceId(): ?string
    {
        return $this->traceId;
    }

    /**
     * Start a new span within the current trace.
     */
    public function startSpan(string $name): string
    {
        $spanId = $this->generateId(16);

        if ($this->parentSpanId !== null) {
            // Nested span, previous becomes parent
            $this->parentSpanId = $spanId;
        } else {
            $this->parentSpanId = $spanId;
        }

        return $spanId;
    }

    /**
     * Get the parent span ID.
     */
    public function parentSpanId(): ?string
    {
        return $this->parentSpanId;
    }

    /**
     * End the current span.
     */
    public function endSpan(string $spanId): void
    {
        // In this simple implementation, we don't track span hierarchy
    }

    /**
     * Create headers for propagation.
     *
     * @return array<string, string>
     */
    public function propagationHeaders(): array
    {
        if ($this->traceId === null) {
            return [];
        }

        $headers = [
            'X-Trace-Id' => $this->traceId,
        ];

        if ($this->parentSpanId !== null) {
            $headers['X-Span-Id'] = $this->parentSpanId;
        }

        // W3C Trace Context format
        $headers['traceparent'] = sprintf(
            '00-%s-%s-01',
            $this->traceId,
            $this->parentSpanId ?? $this->generateId(16),
        );

        return $headers;
    }

    /**
     * Extract trace context from incoming headers.
     *
     * @param array<string, string> $headers
     */
    public function extractContext(array $headers): void
    {
        // Try X-Trace-Id first
        if (array_key_exists('X-Trace-Id', $headers)) {
            $this->traceId = $headers['X-Trace-Id'];
        }

        if (array_key_exists('X-Span-Id', $headers)) {
            $this->parentSpanId = $headers['X-Span-Id'];
        }

        // Try W3C traceparent
        if (!array_key_exists('traceparent', $headers)) {
            return;
        }

        $parts = explode('-', $headers['traceparent']);

        if (count($parts) < 3) {
            return;
        }

        $this->traceId = $parts[1];
        $this->parentSpanId = $parts[2];
    }

    /**
     * Record request attributes for telemetry.
     */
    public function recordRequest(Request $request): void
    {
        // Override in custom implementations for APM integration
    }

    /**
     * Record response attributes for telemetry.
     */
    public function recordResponse(Response $response): void
    {
        // Override in custom implementations for APM integration
    }

    /**
     * Generate a random hex ID of specified length.
     */
    private function generateId(int $length): string
    {
        return bin2hex(random_bytes(max(1, (int) ($length / 2))));
    }
}

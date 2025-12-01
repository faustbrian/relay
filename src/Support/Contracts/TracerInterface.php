<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Contracts;

use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;

/**
 * Interface for distributed tracing.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface TracerInterface
{
    /**
     * Start a new trace.
     */
    public function startTrace(): string;

    /**
     * Get the current trace ID.
     */
    public function traceId(): ?string;

    /**
     * Start a new span within the current trace.
     */
    public function startSpan(string $name): string;

    /**
     * Get the parent span ID.
     */
    public function parentSpanId(): ?string;

    /**
     * End the current span.
     */
    public function endSpan(string $spanId): void;

    /**
     * Create headers for propagation.
     *
     * @return array<string, string>
     */
    public function propagationHeaders(): array;

    /**
     * Extract trace context from incoming headers.
     *
     * @param array<string, string> $headers
     */
    public function extractContext(array $headers): void;

    /**
     * Record request attributes for telemetry.
     */
    public function recordRequest(Request $request): void;

    /**
     * Record response attributes for telemetry.
     */
    public function recordResponse(Response $response): void;
}

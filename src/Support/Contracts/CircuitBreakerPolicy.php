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
use Throwable;

/**
 * Interface for circuit breaker policy implementations.
 *
 * Implement this interface to create reusable circuit breaker policies that
 * encapsulate all configuration and failure detection logic.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface CircuitBreakerPolicy
{
    /**
     * Number of failures before opening the circuit.
     */
    public function failureThreshold(): int;

    /**
     * Seconds to wait before transitioning to half-open.
     */
    public function resetTimeout(): int;

    /**
     * Number of requests allowed in half-open state.
     */
    public function halfOpenRequests(): int;

    /**
     * Time window in seconds for counting failures.
     */
    public function failureWindow(): int;

    /**
     * Number of successes needed to close the circuit.
     */
    public function successThreshold(): int;

    /**
     * Determine if a response should be counted as a failure.
     */
    public function isFailure(Request $request, Response $response): bool;

    /**
     * Determine if an exception should be counted as a failure.
     */
    public function isExceptionFailure(Request $request, Throwable $exception): bool;

    /**
     * Called when the circuit opens.
     */
    public function onOpen(string $key): void;

    /**
     * Called when the circuit closes.
     */
    public function onClose(string $key): void;

    /**
     * Called when the circuit transitions to half-open.
     */
    public function onHalfOpen(string $key): void;
}

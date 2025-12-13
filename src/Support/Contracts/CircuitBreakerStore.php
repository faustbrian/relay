<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Contracts;

use Cline\Relay\Features\Resilience\CircuitState;

/**
 * Interface for circuit breaker state storage.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface CircuitBreakerStore
{
    /**
     * Get the circuit state.
     */
    public function getState(string $key): CircuitState;

    /**
     * Set the circuit state.
     */
    public function setState(string $key, CircuitState $state): void;

    /**
     * Record a failure.
     */
    public function recordFailure(string $key, int $windowSeconds): void;

    /**
     * Record a success.
     */
    public function recordSuccess(string $key): void;

    /**
     * Get the failure count within the window.
     */
    public function getFailureCount(string $key): int;

    /**
     * Get the success count (for half-open state).
     */
    public function getSuccessCount(string $key): int;

    /**
     * Get when the circuit was opened.
     */
    public function getOpenedAt(string $key): ?int;

    /**
     * Set when the circuit was opened.
     */
    public function setOpenedAt(string $key, int $timestamp): void;

    /**
     * Get the allowed half-open requests count.
     */
    public function getHalfOpenAttempts(string $key): int;

    /**
     * Increment half-open attempts.
     */
    public function incrementHalfOpenAttempts(string $key): void;

    /**
     * Reset the circuit for a key.
     */
    public function reset(string $key): void;
}

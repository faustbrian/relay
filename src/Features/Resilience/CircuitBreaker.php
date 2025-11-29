<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Features\Resilience;

use Cline\Relay\Support\Contracts\CircuitBreakerStore;
use Cline\Relay\Support\Exceptions\CircuitOpenException;
use Closure;
use Illuminate\Support\Facades\Date;

use function max;

/**
 * Circuit breaker implementation.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class CircuitBreaker
{
    public function __construct(
        private CircuitBreakerStore $store,
        private CircuitBreakerConfig $config,
        private string $key,
    ) {}

    /**
     * Get the current circuit state.
     */
    public function state(): CircuitState
    {
        $state = $this->store->getState($this->key);

        // Check if we should transition from open to half-open
        if ($state === CircuitState::Open) {
            $openedAt = $this->store->getOpenedAt($this->key);

            if ($openedAt !== null && Date::now()->getTimestamp() - $openedAt >= $this->config->resetTimeout) {
                $this->halfOpen();

                return CircuitState::HalfOpen;
            }
        }

        return $state;
    }

    /**
     * Check if the circuit is open.
     */
    public function isOpen(): bool
    {
        return $this->state() === CircuitState::Open;
    }

    /**
     * Check if the circuit is closed.
     */
    public function isClosed(): bool
    {
        return $this->state() === CircuitState::Closed;
    }

    /**
     * Check if the circuit is half-open.
     */
    public function isHalfOpen(): bool
    {
        return $this->state() === CircuitState::HalfOpen;
    }

    /**
     * Check if a request can proceed.
     *
     * @throws CircuitOpenException If circuit is open
     */
    public function allowRequest(): bool
    {
        $state = $this->state();

        if ($state === CircuitState::Closed) {
            return true;
        }

        if ($state === CircuitState::Open) {
            $retryAfter = $this->getRetryAfter();

            throw CircuitOpenException::open($retryAfter);
        }

        // Half-open: allow limited requests
        if ($this->store->getHalfOpenAttempts($this->key) < $this->config->halfOpenRequests) {
            $this->store->incrementHalfOpenAttempts($this->key);

            return true;
        }

        throw CircuitOpenException::halfOpenAtCapacity();
    }

    /**
     * Record a successful request.
     */
    public function recordSuccess(): void
    {
        $state = $this->state();

        if ($state === CircuitState::HalfOpen) {
            $this->store->recordSuccess($this->key);

            // Check if we have enough successes to close
            if ($this->store->getSuccessCount($this->key) >= $this->config->successThreshold) {
                $this->close();
            }
        }
    }

    /**
     * Record a failed request.
     */
    public function recordFailure(): void
    {
        $state = $this->state();

        if ($state === CircuitState::HalfOpen) {
            // Any failure in half-open reopens the circuit
            $this->open();

            return;
        }

        if ($state === CircuitState::Closed) {
            $this->store->recordFailure($this->key, $this->config->failureWindow);

            // Check if we should open the circuit
            if ($this->shouldOpen()) {
                $this->open();
            }
        }
    }

    /**
     * Force the circuit open.
     */
    public function open(): void
    {
        $this->store->setState($this->key, CircuitState::Open);
        $this->store->setOpenedAt($this->key, Date::now()->getTimestamp());

        if ($this->config->onOpen instanceof Closure) {
            ($this->config->onOpen)($this->key);
        }
    }

    /**
     * Force the circuit closed.
     */
    public function close(): void
    {
        $this->store->reset($this->key);
        $this->store->setState($this->key, CircuitState::Closed);

        if ($this->config->onClose instanceof Closure) {
            ($this->config->onClose)($this->key);
        }
    }

    /**
     * Reset the circuit (same as close but no callback).
     */
    public function reset(): void
    {
        $this->store->reset($this->key);
    }

    /**
     * Transition to half-open state.
     */
    private function halfOpen(): void
    {
        $this->store->setState($this->key, CircuitState::HalfOpen);

        if ($this->config->onHalfOpen instanceof Closure) {
            ($this->config->onHalfOpen)($this->key);
        }
    }

    /**
     * Check if we should open the circuit based on failures.
     */
    private function shouldOpen(): bool
    {
        $failures = $this->store->getFailureCount($this->key);

        // Percentage-based threshold
        if ($this->config->failurePercentage !== null && $this->config->minimumRequests !== null) {
            // TODO: Track total requests for percentage calculation
            // For now, fall back to count-based
        }

        // Count-based threshold
        return $failures >= $this->config->failureThreshold;
    }

    /**
     * Get seconds until retry is allowed.
     */
    private function getRetryAfter(): int
    {
        $openedAt = $this->store->getOpenedAt($this->key);

        if ($openedAt === null) {
            return $this->config->resetTimeout;
        }

        return max(0, $this->config->resetTimeout - (Date::now()->getTimestamp() - $openedAt));
    }
}

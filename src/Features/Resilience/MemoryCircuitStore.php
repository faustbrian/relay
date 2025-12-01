<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Features\Resilience;

use Cline\Relay\Support\Contracts\CircuitBreakerStore;
use Illuminate\Support\Facades\Date;

use function array_filter;
use function count;

/**
 * In-memory circuit breaker storage.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class MemoryCircuitStore implements CircuitBreakerStore
{
    /** @var array<string, array{state: CircuitState, failures: array<int>, successes: int, opened_at: ?int, half_open_attempts: int}> */
    private array $circuits = [];

    public function getState(string $key): CircuitState
    {
        return $this->getCircuit($key)['state'];
    }

    public function setState(string $key, CircuitState $state): void
    {
        $circuit = $this->getCircuit($key);
        $circuit['state'] = $state;
        $this->circuits[$key] = $circuit;
    }

    public function recordFailure(string $key, int $windowSeconds): void
    {
        $circuit = $this->getCircuit($key);
        $now = Date::now()->getTimestamp();

        // Add failure timestamp
        $circuit['failures'][] = $now;

        // Clean up old failures outside window
        $circuit['failures'] = array_filter(
            $circuit['failures'],
            fn (int $time): bool => $time > $now - $windowSeconds,
        );

        $this->circuits[$key] = $circuit;
    }

    public function recordSuccess(string $key): void
    {
        $circuit = $this->getCircuit($key);
        ++$circuit['successes'];
        $this->circuits[$key] = $circuit;
    }

    public function getFailureCount(string $key): int
    {
        return count($this->getCircuit($key)['failures']);
    }

    public function getSuccessCount(string $key): int
    {
        return $this->getCircuit($key)['successes'];
    }

    public function getOpenedAt(string $key): ?int
    {
        return $this->getCircuit($key)['opened_at'];
    }

    public function setOpenedAt(string $key, int $timestamp): void
    {
        $circuit = $this->getCircuit($key);
        $circuit['opened_at'] = $timestamp;
        $this->circuits[$key] = $circuit;
    }

    public function getHalfOpenAttempts(string $key): int
    {
        return $this->getCircuit($key)['half_open_attempts'];
    }

    public function incrementHalfOpenAttempts(string $key): void
    {
        $circuit = $this->getCircuit($key);
        ++$circuit['half_open_attempts'];
        $this->circuits[$key] = $circuit;
    }

    public function reset(string $key): void
    {
        $this->circuits[$key] = $this->defaultCircuit();
    }

    /**
     * Get or create circuit data.
     *
     * @return array{state: CircuitState, failures: array<int>, successes: int, opened_at: ?int, half_open_attempts: int}
     */
    private function getCircuit(string $key): array
    {
        return $this->circuits[$key] ?? $this->defaultCircuit();
    }

    /**
     * Get default circuit state.
     *
     * @return array{state: CircuitState, failures: array<int>, successes: int, opened_at: ?int, half_open_attempts: int}
     */
    private function defaultCircuit(): array
    {
        return [
            'state' => CircuitState::Closed,
            'failures' => [],
            'successes' => 0,
            'opened_at' => null,
            'half_open_attempts' => 0,
        ];
    }
}

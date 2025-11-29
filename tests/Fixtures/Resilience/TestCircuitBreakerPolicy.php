<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\Resilience;

use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;
use Cline\Relay\Support\Contracts\CircuitBreakerPolicy;
use RuntimeException;
use Throwable;

/**
 * Test circuit breaker policy for unit tests.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final class TestCircuitBreakerPolicy implements CircuitBreakerPolicy
{
    /** @var array<string> */
    public static array $events = [];

    public static function resetEvents(): void
    {
        self::$events = [];
    }

    public function failureThreshold(): int
    {
        return 3;
    }

    public function resetTimeout(): int
    {
        return 15;
    }

    public function halfOpenRequests(): int
    {
        return 2;
    }

    public function failureWindow(): int
    {
        return 30;
    }

    public function successThreshold(): int
    {
        return 2;
    }

    public function isFailure(Request $request, Response $response): bool
    {
        // Only 500 and 503 are failures
        if ($response->status() === 500) {
            return true;
        }

        return $response->status() === 503;
    }

    public function isExceptionFailure(Request $request, Throwable $exception): bool
    {
        return $exception instanceof RuntimeException;
    }

    public function onOpen(string $key): void
    {
        self::$events[] = 'opened:'.$key;
    }

    public function onClose(string $key): void
    {
        self::$events[] = 'closed:'.$key;
    }

    public function onHalfOpen(string $key): void
    {
        self::$events[] = 'half-open:'.$key;
    }
}

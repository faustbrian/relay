<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\RateLimiting;

use Cline\Relay\Core\Request;
use Cline\Relay\Support\Contracts\BackoffStrategy;

/**
 * Test backoff strategy for unit tests.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class TestBackoffStrategy implements BackoffStrategy
{
    public function calculateDelay(Request $request, int $attempt, int $retryAfter = 0): int
    {
        // If retry-after is set, respect it
        if ($retryAfter > 0) {
            return $retryAfter * 1_000;
        }

        // Custom fibonacci-like backoff: 1s, 1s, 2s, 3s, 5s...
        return $this->fibonacci($attempt) * 1_000;
    }

    private function fibonacci(int $n): int
    {
        if ($n <= 2) {
            return 1;
        }

        $prev = 1;
        $curr = 1;

        for ($i = 3; $i <= $n; ++$i) {
            $next = $prev + $curr;
            $prev = $curr;
            $curr = $next;
        }

        return $curr;
    }
}

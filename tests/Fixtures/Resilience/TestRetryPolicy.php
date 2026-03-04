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
use Cline\Relay\Support\Contracts\RetryPolicy;
use RuntimeException;
use Throwable;

use function in_array;

/**
 * Test retry policy for unit tests.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class TestRetryPolicy implements RetryPolicy
{
    public function times(): int
    {
        return 5;
    }

    public function delay(): int
    {
        return 250;
    }

    public function multiplier(): float
    {
        return 1.5;
    }

    public function maxDelay(): int
    {
        return 10_000;
    }

    public function shouldRetry(Request $request, Response $response, int $attempt): bool
    {
        // Only retry on 500 and 503
        return in_array($response->status(), [500, 503], true);
    }

    public function shouldRetryException(Request $request, Throwable $exception, int $attempt): bool
    {
        // Only retry RuntimeException
        return $exception instanceof RuntimeException;
    }
}

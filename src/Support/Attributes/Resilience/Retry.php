<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Attributes\Resilience;

use Attribute;
use Cline\Relay\Support\Contracts\RetryDecider;
use Cline\Relay\Support\Contracts\RetryPolicy;
use Throwable;

/**
 * Configure retry behavior for failed requests.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Retry
{
    /**
     * @param int                                    $times      Maximum retry attempts
     * @param int                                    $delay      Initial delay in milliseconds
     * @param float                                  $multiplier Exponential backoff multiplier
     * @param int                                    $maxDelay   Maximum delay in milliseconds
     * @param null|array<int>                        $when       Status codes to retry on
     * @param null|array<class-string<Throwable>>    $exceptions Exception types to retry on
     * @param null|class-string<RetryDecider>|string $callback   Method name or RetryDecider class for custom retry logic
     * @param null|class-string<RetryPolicy>         $policy     RetryPolicy class that encapsulates all retry configuration
     */
    public function __construct(
        public int $times = 3,
        public int $delay = 100,
        public float $multiplier = 2.0,
        public int $maxDelay = 30_000,
        public ?array $when = null,
        public ?array $exceptions = null,
        public ?string $callback = null,
        public ?string $policy = null,
    ) {}
}

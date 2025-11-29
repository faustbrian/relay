<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Features\Resilience;

use Closure;
use Throwable;

/**
 * Configuration for retry behavior.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class RetryConfig
{
    /**
     * @param int                                 $times       Maximum retry attempts
     * @param int                                 $delay       Initial delay in milliseconds
     * @param float                               $multiplier  Exponential backoff multiplier
     * @param int                                 $maxDelay    Maximum delay in milliseconds
     * @param null|array<int>                     $statusCodes Status codes to retry on
     * @param null|array<class-string<Throwable>> $exceptions  Exception types to retry
     * @param null|Closure                        $when        Custom condition: fn(Response $r) => bool
     */
    public function __construct(
        public int $times = 3,
        public int $delay = 100,
        public float $multiplier = 2.0,
        public int $maxDelay = 30_000,
        public ?array $statusCodes = null,
        public ?array $exceptions = null,
        public ?Closure $when = null,
    ) {}
}

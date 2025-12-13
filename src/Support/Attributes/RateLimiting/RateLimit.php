<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Attributes\RateLimiting;

use Attribute;
use Cline\Relay\Support\Contracts\BackoffStrategy;

/**
 * Configure rate limiting for a request.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class RateLimit
{
    /**
     * @param int                                  $requests   Maximum number of requests allowed
     * @param int                                  $perSeconds Time window in seconds
     * @param null|string                          $key        Shared limiter key (supports placeholders)
     * @param bool                                 $retry      Auto-retry when rate limited
     * @param int                                  $maxRetries Maximum retry attempts
     * @param class-string<BackoffStrategy>|string $backoff    Backoff strategy name or BackoffStrategy class
     */
    public function __construct(
        public int $requests,
        public int $perSeconds,
        public ?string $key = null,
        public bool $retry = false,
        public int $maxRetries = 3,
        public string $backoff = 'exponential',
    ) {}
}

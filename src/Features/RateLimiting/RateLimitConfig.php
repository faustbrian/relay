<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Features\RateLimiting;

/**
 * Configuration for rate limiting.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class RateLimitConfig
{
    /**
     * @param int    $requests   Maximum requests allowed
     * @param int    $perSeconds Time window in seconds
     * @param bool   $retry      Auto-retry when rate limited
     * @param int    $maxRetries Maximum retry attempts
     * @param string $backoff    Backoff strategy
     */
    public function __construct(
        public int $requests,
        public int $perSeconds,
        public bool $retry = false,
        public int $maxRetries = 3,
        public string $backoff = 'exponential',
    ) {}
}

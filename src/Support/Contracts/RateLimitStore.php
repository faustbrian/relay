<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Contracts;

/**
 * Interface for rate limit storage backends.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface RateLimitStore
{
    /**
     * Attempt to acquire a rate limit slot.
     *
     * @param  string $key        The rate limit key
     * @param  int    $limit      Maximum requests allowed
     * @param  int    $perSeconds Time window in seconds
     * @return bool   True if the request is allowed, false if rate limited
     */
    public function attempt(string $key, int $limit, int $perSeconds): bool;

    /**
     * Get the current count for a key.
     */
    public function getCount(string $key): int;

    /**
     * Get the remaining requests for a key.
     */
    public function getRemaining(string $key, int $limit): int;

    /**
     * Get the time until the rate limit resets.
     */
    public function getResetTime(string $key): ?int;

    /**
     * Reset the rate limit for a key.
     */
    public function reset(string $key): void;

    /**
     * Clear all rate limits.
     */
    public function clear(): void;
}

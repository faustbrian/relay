<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Features\RateLimiting;

use Cline\Relay\Support\Contracts\RateLimitStore;
use Illuminate\Support\Facades\Date;
use Psr\SimpleCache\CacheInterface;

use function max;

/**
 * PSR-16 cache-backed rate limit storage.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class CacheStore implements RateLimitStore
{
    private const string PREFIX = 'relay:ratelimit:';

    public function __construct(
        private CacheInterface $cache,
    ) {}

    public function attempt(string $key, int $limit, int $perSeconds): bool
    {
        $cacheKey = self::PREFIX.$key;
        $now = Date::now()->getTimestamp();

        /** @var null|array{count: int, window_start: int, window_size: int} $bucket */
        $bucket = $this->cache->get($cacheKey);

        // Create new bucket if none exists or window has expired
        if ($bucket === null || $now >= $bucket['window_start'] + $bucket['window_size']) {
            $bucket = [
                'count' => 0,
                'window_start' => $now,
                'window_size' => $perSeconds,
            ];
        }

        // Check if within rate limit
        if ($bucket['count'] >= $limit) {
            return false;
        }

        // Increment count
        ++$bucket['count'];
        $ttl = $bucket['window_start'] + $bucket['window_size'] - $now;
        $this->cache->set($cacheKey, $bucket, max(1, $ttl));

        return true;
    }

    public function getCount(string $key): int
    {
        $cacheKey = self::PREFIX.$key;

        /** @var null|array{count: int, window_start: int, window_size: int} $bucket */
        $bucket = $this->cache->get($cacheKey);

        if ($bucket === null) {
            return 0;
        }

        // Check if bucket has expired
        $now = Date::now()->getTimestamp();

        if ($now >= $bucket['window_start'] + $bucket['window_size']) {
            return 0;
        }

        return $bucket['count'];
    }

    public function getRemaining(string $key, int $limit): int
    {
        return max(0, $limit - $this->getCount($key));
    }

    public function getResetTime(string $key): ?int
    {
        $cacheKey = self::PREFIX.$key;

        /** @var null|array{count: int, window_start: int, window_size: int} $bucket */
        $bucket = $this->cache->get($cacheKey);

        if ($bucket === null) {
            return null;
        }

        return $bucket['window_start'] + $bucket['window_size'];
    }

    public function reset(string $key): void
    {
        $cacheKey = self::PREFIX.$key;
        $this->cache->delete($cacheKey);
    }

    public function clear(): void
    {
        $this->cache->clear();
    }
}

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

use function max;

/**
 * In-memory rate limit storage.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class MemoryStore implements RateLimitStore
{
    /** @var array<string, array{count: int, window_start: int, window_size: int}> */
    private array $buckets = [];

    public function attempt(string $key, int $limit, int $perSeconds): bool
    {
        $now = Date::now()->getTimestamp();
        $bucket = $this->getBucket($key, $perSeconds, $now);

        // Check if within rate limit
        if ($bucket['count'] >= $limit) {
            return false;
        }

        // Increment count
        ++$bucket['count'];
        $this->buckets[$key] = $bucket;

        return true;
    }

    public function getCount(string $key): int
    {
        $bucket = $this->buckets[$key] ?? null;

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
        $bucket = $this->buckets[$key] ?? null;

        if ($bucket === null) {
            return null;
        }

        return $bucket['window_start'] + $bucket['window_size'];
    }

    public function reset(string $key): void
    {
        unset($this->buckets[$key]);
    }

    public function clear(): void
    {
        $this->buckets = [];
    }

    /**
     * Get or create a bucket for a key.
     *
     * @return array{count: int, window_start: int, window_size: int}
     */
    private function getBucket(string $key, int $windowSize, int $now): array
    {
        $bucket = $this->buckets[$key] ?? null;

        // Create new bucket if none exists or window has expired
        if ($bucket === null || $now >= $bucket['window_start'] + $bucket['window_size']) {
            return [
                'count' => 0,
                'window_start' => $now,
                'window_size' => $windowSize,
            ];
        }

        return $bucket;
    }
}

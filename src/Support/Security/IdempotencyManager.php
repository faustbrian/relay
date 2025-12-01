<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Security;

use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;
use Psr\SimpleCache\CacheInterface;

use function bin2hex;
use function is_array;
use function is_int;
use function random_bytes;

/**
 * Manages idempotency for requests.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class IdempotencyManager
{
    private const string DEFAULT_HEADER = 'Idempotency-Key';

    private const int DEFAULT_TTL = 86_400; // 24 hours

    public function __construct(
        private ?CacheInterface $cache = null,
        private string $headerName = self::DEFAULT_HEADER,
        private int $ttl = self::DEFAULT_TTL,
    ) {}

    /**
     * Generate a unique idempotency key.
     */
    public function generateKey(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Get the idempotency header name.
     */
    public function headerName(): string
    {
        return $this->headerName;
    }

    /**
     * Add idempotency key to request.
     */
    public function addToRequest(Request $request, ?string $key = null): Request
    {
        $key ??= $request->idempotencyKey() ?? $this->generateKey();

        return $request
            ->withIdempotencyKey($key)
            ->withHeader($this->headerName, $key);
    }

    /**
     * Check if we have a cached response for this idempotency key.
     */
    public function getCachedResponse(string $key): ?Response
    {
        if (!$this->cache instanceof CacheInterface) {
            return null;
        }

        $cached = $this->cache->get($this->cacheKey($key));

        if ($cached === null) {
            return null;
        }

        if (!is_array($cached) || !isset($cached['data'], $cached['status'], $cached['headers'])) {
            return null;
        }

        if (!is_array($cached['data']) || !is_int($cached['status']) || !is_array($cached['headers'])) {
            return null;
        }

        /** @var array<string, mixed> $data */
        $data = $cached['data'];

        /** @var array<string, string> $headers */
        $headers = $cached['headers'];

        return Response::make(
            data: $data,
            status: $cached['status'],
            headers: $headers,
        );
    }

    /**
     * Cache a response for an idempotency key.
     */
    public function cacheResponse(string $key, Response $response): void
    {
        if (!$this->cache instanceof CacheInterface) {
            return;
        }

        $jsonData = $response->json();

        $this->cache->set(
            $this->cacheKey($key),
            [
                'data' => is_array($jsonData) ? $jsonData : [],
                'status' => $response->status(),
                'headers' => $response->headers(),
            ],
            $this->ttl,
        );
    }

    /**
     * Invalidate a cached response.
     */
    public function invalidate(string $key): void
    {
        $this->cache?->delete($this->cacheKey($key));
    }

    /**
     * Check if a response indicates it was an idempotent replay.
     */
    public function isReplay(Response $response): bool
    {
        return $response->wasIdempotentReplay();
    }

    /**
     * Mark a response as an idempotent replay.
     */
    public function markAsReplay(Response $response): Response
    {
        return $response->setWasIdempotentReplay(true);
    }

    /**
     * Generate the cache key for an idempotency key.
     */
    private function cacheKey(string $key): string
    {
        return 'relay:idempotency:'.$key;
    }
}

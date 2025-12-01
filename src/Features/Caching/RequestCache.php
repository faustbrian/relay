<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Features\Caching;

use Cline\Relay\Core\Connector;
use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;
use Cline\Relay\Support\Attributes\Caching\Cache;
use Cline\Relay\Support\Attributes\Caching\InvalidatesCache;
use Cline\Relay\Support\Attributes\Caching\NoCache;
use Illuminate\Support\Facades\Date;
use Psr\SimpleCache\CacheInterface;
use ReflectionClass;

use function array_filter;
use function array_key_exists;
use function array_unique;
use function in_array;
use function is_array;

/**
 * Handles request caching operations.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class RequestCache
{
    /** @var array<string, array<string>> Tag to keys mapping for invalidation */
    private array $taggedKeys = [];

    private readonly CacheKeyGenerator $keyGenerator;

    public function __construct(
        private readonly CacheConfig $config,
    ) {
        $this->keyGenerator = new CacheKeyGenerator($config);
    }

    /**
     * Get a cached response if available.
     */
    public function get(Connector $connector, Request $request): ?Response
    {
        if (!$this->isCacheable($request)) {
            return null;
        }

        $key = $this->keyGenerator->generate($connector, $request);
        $cached = $this->config->store->get($key);

        if ($cached === null) {
            return null;
        }

        if (!is_array($cached)) {
            return null;
        }

        /** @var array<string, mixed> $cached */
        return $this->deserializeResponse($cached);
    }

    /**
     * Store a response in cache.
     */
    public function put(Connector $connector, Request $request, Response $response): void
    {
        if (!$this->isCacheable($request)) {
            return;
        }

        $key = $this->keyGenerator->generate($connector, $request);
        $ttl = $this->keyGenerator->getTtl($request);
        $tags = $this->keyGenerator->getTags($request);

        $this->config->store->set($key, $this->serializeResponse($response), $ttl);

        // Track tagged keys for invalidation
        foreach ($tags as $tag) {
            $this->trackTaggedKey($tag, $key);
        }
    }

    /**
     * Forget a specific request from cache.
     */
    public function forget(Connector $connector, Request $request): bool
    {
        $key = $this->keyGenerator->generate($connector, $request);

        return $this->config->store->delete($key);
    }

    /**
     * Invalidate cache entries by tags.
     *
     * @param array<string> $tags
     */
    public function invalidateTags(array $tags): void
    {
        foreach ($tags as $tag) {
            $keys = $this->getTaggedKeys($tag);

            foreach ($keys as $key) {
                $this->config->store->delete($key);
            }

            $this->clearTaggedKeys($tag);
        }
    }

    /**
     * Invalidate cache after a mutation request.
     */
    public function handleInvalidation(Connector $connector, Request $request): void
    {
        $attribute = $this->getInvalidatesCacheAttribute($request);

        if (!$attribute instanceof InvalidatesCache) {
            return;
        }

        // Invalidate by tags
        if ($attribute->tags !== []) {
            $this->invalidateTags($attribute->tags);
        }

        // Invalidate by specific keys
        if ($attribute->keys !== null) {
            foreach ($attribute->keys as $key) {
                $this->config->store->delete($key);
            }
        }
    }

    /**
     * Flush the entire cache.
     */
    public function flush(): bool
    {
        $this->taggedKeys = [];

        return $this->config->store->clear();
    }

    /**
     * Get the underlying cache store.
     */
    public function store(): CacheInterface
    {
        return $this->config->store;
    }

    /**
     * Check if a request can be cached.
     */
    public function isCacheable(Request $request): bool
    {
        // Check for NoCache attribute
        if ($this->hasNoCacheAttribute($request)) {
            return false;
        }

        // Check if method is cacheable
        return in_array($request->method(), $this->config->cacheableMethods, true);
    }

    /**
     * Check if request has Cache attribute.
     */
    public function hasCacheAttribute(Request $request): bool
    {
        $reflection = new ReflectionClass($request);

        return $reflection->getAttributes(Cache::class) !== [];
    }

    /**
     * Check if request has NoCache attribute.
     */
    private function hasNoCacheAttribute(Request $request): bool
    {
        $reflection = new ReflectionClass($request);

        return $reflection->getAttributes(NoCache::class) !== [];
    }

    /**
     * Get the InvalidatesCache attribute from a request.
     */
    private function getInvalidatesCacheAttribute(Request $request): ?InvalidatesCache
    {
        $reflection = new ReflectionClass($request);
        $attributes = $reflection->getAttributes(InvalidatesCache::class);

        if ($attributes === []) {
            return null;
        }

        return $attributes[0]->newInstance();
    }

    /**
     * Track a key for a tag.
     */
    private function trackTaggedKey(string $tag, string $key): void
    {
        $tagKey = $this->getTagStorageKey($tag);
        $keys = $this->getTaggedKeys($tag);
        $keys[] = $key;
        $keys = array_unique($keys);

        $this->config->store->set($tagKey, $keys);
        $this->taggedKeys[$tag] = $keys;
    }

    /**
     * Get all keys for a tag.
     *
     * @return array<string>
     */
    private function getTaggedKeys(string $tag): array
    {
        if (array_key_exists($tag, $this->taggedKeys)) {
            return $this->taggedKeys[$tag];
        }

        $tagKey = $this->getTagStorageKey($tag);
        $keys = $this->config->store->get($tagKey);

        if (!is_array($keys)) {
            return [];
        }

        // Filter to ensure only strings are returned
        return array_filter($keys, is_string(...));
    }

    /**
     * Clear tracked keys for a tag.
     */
    private function clearTaggedKeys(string $tag): void
    {
        $tagKey = $this->getTagStorageKey($tag);
        $this->config->store->delete($tagKey);
        unset($this->taggedKeys[$tag]);
    }

    /**
     * Get the storage key for a tag's tracked keys.
     */
    private function getTagStorageKey(string $tag): string
    {
        return $this->config->prefix.'_tags:'.$tag;
    }

    /**
     * Serialize a response for storage.
     *
     * @return array<string, mixed>
     */
    private function serializeResponse(Response $response): array
    {
        return [
            'status' => $response->status(),
            'headers' => $response->headers(),
            'data' => $response->json(),
            'cached_at' => Date::now()->getTimestamp(),
        ];
    }

    /**
     * Deserialize a cached response.
     *
     * @param array<string, mixed> $cached
     */
    private function deserializeResponse(array $cached): Response
    {
        /** @var array<string, mixed> $data */
        $data = is_array($cached['data']) ? $cached['data'] : [];

        /** @var int $status */
        $status = $cached['status'];

        /** @var array<string, string> $headers */
        $headers = is_array($cached['headers']) ? $cached['headers'] : [];

        return Response::make(
            data: $data,
            status: $status,
            headers: $headers,
        );
    }
}

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Relay\Core\Connector;
use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;
use Cline\Relay\Features\Caching\CacheConfig;
use Cline\Relay\Features\Caching\CacheKeyGenerator;
use Cline\Relay\Features\Caching\RequestCache;
use Cline\Relay\Support\Attributes\Caching\Cache;
use Cline\Relay\Support\Attributes\Caching\InvalidatesCache;
use Cline\Relay\Support\Attributes\Caching\NoCache;
use Illuminate\Support\Facades\Date;
use Psr\SimpleCache\CacheInterface;
use Tests\Fixtures\Caching\TestCacheKeyResolver;

// Simple in-memory cache for testing
function createInMemoryCache(): CacheInterface
{
    return new class() implements CacheInterface
    {
        private array $data = [];

        public function get(string $key, mixed $default = null): mixed
        {
            return $this->data[$key] ?? $default;
        }

        public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
        {
            $this->data[$key] = $value;

            return true;
        }

        public function delete(string $key): bool
        {
            unset($this->data[$key]);

            return true;
        }

        public function clear(): bool
        {
            $this->data = [];

            return true;
        }

        public function getMultiple(iterable $keys, mixed $default = null): iterable
        {
            $result = [];

            foreach ($keys as $key) {
                $result[$key] = $this->get($key, $default);
            }

            return $result;
        }

        public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
        {
            foreach ($values as $key => $value) {
                $this->set($key, $value, $ttl);
            }

            return true;
        }

        public function deleteMultiple(iterable $keys): bool
        {
            foreach ($keys as $key) {
                $this->delete($key);
            }

            return true;
        }

        public function has(string $key): bool
        {
            return array_key_exists($key, $this->data);
        }

        public function getData(): array
        {
            return $this->data;
        }
    };
}

function createTestConnector(): Connector
{
    return new class() extends Connector
    {
        public function baseUrl(): string
        {
            return 'https://api.example.com';
        }
    };
}

describe('Cache Attribute', function (): void {
    it('has default TTL of 300 seconds', function (): void {
        $cache = new Cache();

        expect($cache->ttl)->toBe(300);
    });

    it('accepts custom TTL', function (): void {
        $cache = new Cache(ttl: 3_600);

        expect($cache->ttl)->toBe(3_600);
    });

    it('accepts custom key', function (): void {
        $cache = new Cache(keyResolver: 'user.{id}');

        expect($cache->keyResolver)->toBe('user.{id}');
    });

    it('accepts tags', function (): void {
        $cache = new Cache(tags: ['users', 'api']);

        expect($cache->tags)->toBe(['users', 'api']);
    });
});

describe('NoCache Attribute', function (): void {
    it('can be instantiated', function (): void {
        $noCache = new NoCache();

        expect($noCache)->toBeInstanceOf(NoCache::class);
    });
});

describe('InvalidatesCache Attribute', function (): void {
    it('accepts tags', function (): void {
        $attribute = new InvalidatesCache(tags: ['users']);

        expect($attribute->tags)->toBe(['users']);
    });

    it('accepts specific keys', function (): void {
        $attribute = new InvalidatesCache(keys: ['user.1', 'user.2']);

        expect($attribute->keys)->toBe(['user.1', 'user.2']);
    });
});

describe('CacheConfig', function (): void {
    it('creates with store', function (): void {
        $store = createInMemoryCache();
        $config = new CacheConfig(store: $store);

        expect($config->store)->toBe($store);
        expect($config->hashAlgorithm)->toBe('md5');
        expect($config->defaultTtl)->toBe(300);
    });

    it('accepts custom configuration', function (): void {
        $store = createInMemoryCache();
        $config = new CacheConfig(
            store: $store,
            hashAlgorithm: 'sha1',
            maxKeyLength: 250,
            prefix: 'api:',
            defaultTtl: 600,
            cacheableMethods: ['GET'],
        );

        expect($config->hashAlgorithm)->toBe('sha1');
        expect($config->maxKeyLength)->toBe(250);
        expect($config->prefix)->toBe('api:');
        expect($config->defaultTtl)->toBe(600);
        expect($config->cacheableMethods)->toBe(['GET']);
    });
});

describe('CacheKeyGenerator', function (): void {
    it('generates default key from request', function (): void {
        $store = createInMemoryCache();
        $config = new CacheConfig(store: $store);
        $generator = new CacheKeyGenerator($config);
        $connector = createTestConnector();

        $request = new class() extends Request
        {
            public function endpoint(): string
            {
                return '/users';
            }

            public function method(): string
            {
                return 'GET';
            }
        };

        $key = $generator->generate($connector, $request);

        expect($key)->toContain('GET');
        expect($key)->toContain('/users');
    });

    it('applies prefix to key', function (): void {
        $store = createInMemoryCache();
        $config = new CacheConfig(store: $store, prefix: 'api:v2:');
        $generator = new CacheKeyGenerator($config);
        $connector = createTestConnector();

        $request = new class() extends Request
        {
            public function endpoint(): string
            {
                return '/users';
            }

            public function method(): string
            {
                return 'GET';
            }
        };

        $key = $generator->generate($connector, $request);

        expect($key)->toStartWith('api:v2:');
    });

    it('truncates long keys', function (): void {
        $store = createInMemoryCache();
        $config = new CacheConfig(store: $store, maxKeyLength: 50);
        $generator = new CacheKeyGenerator($config);
        $connector = createTestConnector();

        $request = new class() extends Request
        {
            public function endpoint(): string
            {
                return '/very/long/endpoint/path/that/will/exceed/the/maximum/key/length';
            }

            public function method(): string
            {
                return 'GET';
            }
        };

        $key = $generator->generate($connector, $request);

        expect(mb_strlen($key))->toBeLessThanOrEqual(50);
    });

    it('uses custom cache key from Cache attribute when method exists on request', function (): void {
        $store = createInMemoryCache();
        $config = new CacheConfig(store: $store);
        $generator = new CacheKeyGenerator($config);
        $connector = createTestConnector();

        $request = new #[Cache(keyResolver: 'cacheKey')] class() extends Request
        {
            public function endpoint(): string
            {
                return '/users';
            }

            public function method(): string
            {
                return 'GET';
            }

            public function cacheKey(): string
            {
                return 'custom_method_key';
            }
        };

        $key = $generator->generate($connector, $request);

        expect($key)->toBe('custom_method_key');
    });

    it('resolves custom cache key with property placeholders', function (): void {
        $store = createInMemoryCache();
        $config = new CacheConfig(store: $store);
        $generator = new CacheKeyGenerator($config);
        $connector = createTestConnector();

        $request = new #[Cache(keyResolver: 'user.{userId}')] class() extends Request
        {
            public function __construct(
                public readonly int $userId = 123,
            ) {}

            public function endpoint(): string
            {
                return '/users';
            }

            public function method(): string
            {
                return 'GET';
            }
        };

        $key = $generator->generate($connector, $request);

        expect($key)->toBe('user.123');
    });

    it('keeps placeholder unchanged when property does not exist in request', function (): void {
        $store = createInMemoryCache();
        $config = new CacheConfig(store: $store);
        $generator = new CacheKeyGenerator($config);
        $connector = createTestConnector();

        $request = new #[Cache(keyResolver: 'user.{nonExistentProp}')] class() extends Request
        {
            public function endpoint(): string
            {
                return '/users';
            }

            public function method(): string
            {
                return 'GET';
            }
        };

        $key = $generator->generate($connector, $request);

        expect($key)->toBe('user.{nonExistentProp}');
    });

    it('includes headers in cache key when includeHeaders is true', function (): void {
        $store = createInMemoryCache();
        $config = new CacheConfig(store: $store, includeHeaders: true);
        $generator = new CacheKeyGenerator($config);
        $connector = createTestConnector();

        $request = new class() extends Request
        {
            public function endpoint(): string
            {
                return '/users';
            }

            public function method(): string
            {
                return 'GET';
            }

            public function headers(): array
            {
                return ['X-Custom-Header' => 'value'];
            }
        };

        $keyWithHeaders = $generator->generate($connector, $request);

        // Create config without includeHeaders
        $configNoHeaders = new CacheConfig(store: $store, includeHeaders: false);
        $generatorNoHeaders = new CacheKeyGenerator($configNoHeaders);
        $keyWithoutHeaders = $generatorNoHeaders->generate($connector, $request);

        // Keys should be different when headers are included
        expect($keyWithHeaders)->not->toBe($keyWithoutHeaders);
    });

    it('uses CacheKeyResolver class when specified', function (): void {
        $store = createInMemoryCache();
        $config = new CacheConfig(store: $store);
        $generator = new CacheKeyGenerator($config);
        $connector = createTestConnector();

        $request = new #[Cache(keyResolver: TestCacheKeyResolver::class)] class() extends Request
        {
            public function endpoint(): string
            {
                return '/api/users';
            }

            public function method(): string
            {
                return 'GET';
            }
        };

        $key = $generator->generate($connector, $request);

        // TestCacheKeyResolver returns 'test-resolver:' + endpoint
        expect($key)->toBe('test-resolver:/api/users');
    });
});

describe('RequestCache', function (): void {
    it('stores and retrieves responses', function (): void {
        $store = createInMemoryCache();
        $config = new CacheConfig(store: $store);
        $cache = new RequestCache($config);
        $connector = createTestConnector();

        $request = new class() extends Request
        {
            public function endpoint(): string
            {
                return '/users';
            }

            public function method(): string
            {
                return 'GET';
            }
        };

        $response = Response::make(['id' => 1, 'name' => 'John'], 200);

        $cache->put($connector, $request, $response);

        $cached = $cache->get($connector, $request);

        expect($cached)->not->toBeNull();
        expect($cached->json('id'))->toBe(1);
    });

    it('returns null for uncached requests', function (): void {
        $store = createInMemoryCache();
        $config = new CacheConfig(store: $store);
        $cache = new RequestCache($config);
        $connector = createTestConnector();

        $request = new class() extends Request
        {
            public function endpoint(): string
            {
                return '/users';
            }

            public function method(): string
            {
                return 'GET';
            }
        };

        $cached = $cache->get($connector, $request);

        expect($cached)->toBeNull();
    });

    it('does not cache non-cacheable methods', function (): void {
        $store = createInMemoryCache();
        $config = new CacheConfig(store: $store);
        $cache = new RequestCache($config);
        $connector = createTestConnector();

        $request = new class() extends Request
        {
            public function endpoint(): string
            {
                return '/users';
            }

            public function method(): string
            {
                return 'POST';
            }
        };

        expect($cache->isCacheable($request))->toBeFalse();
    });

    it('forgets specific requests', function (): void {
        $store = createInMemoryCache();
        $config = new CacheConfig(store: $store);
        $cache = new RequestCache($config);
        $connector = createTestConnector();

        $request = new class() extends Request
        {
            public function endpoint(): string
            {
                return '/users';
            }

            public function method(): string
            {
                return 'GET';
            }
        };

        $response = Response::make(['id' => 1], 200);
        $cache->put($connector, $request, $response);

        expect($cache->get($connector, $request))->not->toBeNull();

        $cache->forget($connector, $request);

        expect($cache->get($connector, $request))->toBeNull();
    });

    it('flushes entire cache', function (): void {
        $store = createInMemoryCache();
        $config = new CacheConfig(store: $store);
        $cache = new RequestCache($config);
        $connector = createTestConnector();

        $request1 = new class() extends Request
        {
            public function endpoint(): string
            {
                return '/users';
            }

            public function method(): string
            {
                return 'GET';
            }
        };

        $request2 = new class() extends Request
        {
            public function endpoint(): string
            {
                return '/posts';
            }

            public function method(): string
            {
                return 'GET';
            }
        };

        $response = Response::make(['id' => 1], 200);
        $cache->put($connector, $request1, $response);
        $cache->put($connector, $request2, $response);

        $cache->flush();

        expect($cache->get($connector, $request1))->toBeNull();
        expect($cache->get($connector, $request2))->toBeNull();
    });

    it('does not cache requests with NoCache attribute', function (): void {
        $store = createInMemoryCache();
        $config = new CacheConfig(store: $store);
        $cache = new RequestCache($config);
        $connector = createTestConnector();

        $request = new #[NoCache()] class() extends Request
        {
            public function endpoint(): string
            {
                return '/users';
            }

            public function method(): string
            {
                return 'GET';
            }
        };

        expect($cache->isCacheable($request))->toBeFalse();

        $response = Response::make(['id' => 1], 200);
        $cache->put($connector, $request, $response);

        expect($cache->get($connector, $request))->toBeNull();
    });

    it('returns underlying cache store', function (): void {
        $store = createInMemoryCache();
        $config = new CacheConfig(store: $store);
        $cache = new RequestCache($config);

        expect($cache->store())->toBe($store);
    });

    it('checks if request has Cache attribute', function (): void {
        $store = createInMemoryCache();
        $config = new CacheConfig(store: $store);
        $cache = new RequestCache($config);

        $requestWithCache = new #[Cache(ttl: 300)] class() extends Request
        {
            public function endpoint(): string
            {
                return '/users';
            }
        };

        $requestWithoutCache = new class() extends Request
        {
            public function endpoint(): string
            {
                return '/posts';
            }
        };

        expect($cache->hasCacheAttribute($requestWithCache))->toBeTrue();
        expect($cache->hasCacheAttribute($requestWithoutCache))->toBeFalse();
    });

    it('tracks and retrieves tagged cache entries', function (): void {
        $store = createInMemoryCache();
        $config = new CacheConfig(store: $store);
        $cache = new RequestCache($config);
        $connector = createTestConnector();

        $request = new #[Cache(tags: ['users', 'api'])] class() extends Request
        {
            public function endpoint(): string
            {
                return '/users';
            }

            public function method(): string
            {
                return 'GET';
            }
        };

        $response = Response::make(['id' => 1, 'name' => 'John'], 200);
        $cache->put($connector, $request, $response);

        // Verify cache entry exists
        expect($cache->get($connector, $request))->not->toBeNull();

        // Invalidate by tags
        $cache->invalidateTags(['users']);

        // Verify cache entry was removed
        expect($cache->get($connector, $request))->toBeNull();
    });

    it('invalidates multiple tagged entries', function (): void {
        $store = createInMemoryCache();
        $config = new CacheConfig(store: $store);
        $cache = new RequestCache($config);
        $connector = createTestConnector();

        $request1 = new #[Cache(tags: ['users'])] class() extends Request
        {
            public function endpoint(): string
            {
                return '/users';
            }

            public function method(): string
            {
                return 'GET';
            }
        };

        $request2 = new #[Cache(tags: ['users', 'admin'])] class() extends Request
        {
            public function endpoint(): string
            {
                return '/admin/users';
            }

            public function method(): string
            {
                return 'GET';
            }
        };

        $response = Response::make(['id' => 1], 200);
        $cache->put($connector, $request1, $response);
        $cache->put($connector, $request2, $response);

        // Invalidate all 'users' tagged entries
        $cache->invalidateTags(['users']);

        expect($cache->get($connector, $request1))->toBeNull();
        expect($cache->get($connector, $request2))->toBeNull();
    });

    it('handles invalidation with InvalidatesCache attribute and tags', function (): void {
        $store = createInMemoryCache();
        $config = new CacheConfig(store: $store);
        $cache = new RequestCache($config);
        $connector = createTestConnector();

        // Create and cache a GET request with tags
        $getRequest = new #[Cache(tags: ['users'])] class() extends Request
        {
            public function endpoint(): string
            {
                return '/users';
            }

            public function method(): string
            {
                return 'GET';
            }
        };

        $response = Response::make(['id' => 1], 200);
        $cache->put($connector, $getRequest, $response);

        expect($cache->get($connector, $getRequest))->not->toBeNull();

        // Create a mutation request that invalidates the tag
        $mutationRequest = new #[InvalidatesCache(tags: ['users'])] class() extends Request
        {
            public function endpoint(): string
            {
                return '/users';
            }

            public function method(): string
            {
                return 'POST';
            }
        };

        // Handle invalidation
        $cache->handleInvalidation($connector, $mutationRequest);

        // Verify the cached entry was invalidated
        expect($cache->get($connector, $getRequest))->toBeNull();
    });

    it('handles invalidation with InvalidatesCache attribute and specific keys', function (): void {
        $store = createInMemoryCache();
        $config = new CacheConfig(store: $store);
        $cache = new RequestCache($config);
        $connector = createTestConnector();

        // Manually store entries with known keys
        $store->set('api:user:1', ['id' => 1]);
        $store->set('api:user:2', ['id' => 2]);

        expect($store->has('api:user:1'))->toBeTrue();
        expect($store->has('api:user:2'))->toBeTrue();

        // Create a mutation request that invalidates specific keys
        $mutationRequest = new #[InvalidatesCache(keys: ['api:user:1', 'api:user:2'])] class() extends Request
        {
            public function endpoint(): string
            {
                return '/users/bulk-delete';
            }

            public function method(): string
            {
                return 'DELETE';
            }
        };

        // Handle invalidation
        $cache->handleInvalidation($connector, $mutationRequest);

        // Verify the specific keys were deleted
        expect($store->has('api:user:1'))->toBeFalse();
        expect($store->has('api:user:2'))->toBeFalse();
    });

    it('does not invalidate when request has no InvalidatesCache attribute', function (): void {
        $store = createInMemoryCache();
        $config = new CacheConfig(store: $store);
        $cache = new RequestCache($config);
        $connector = createTestConnector();

        // Create and cache a GET request
        $getRequest = new #[Cache(tags: ['users'])] class() extends Request
        {
            public function endpoint(): string
            {
                return '/users';
            }

            public function method(): string
            {
                return 'GET';
            }
        };

        $response = Response::make(['id' => 1], 200);
        $cache->put($connector, $getRequest, $response);

        // Create a mutation request WITHOUT InvalidatesCache attribute
        $mutationRequest = new class() extends Request
        {
            public function endpoint(): string
            {
                return '/users';
            }

            public function method(): string
            {
                return 'POST';
            }
        };

        // Handle invalidation (should do nothing)
        $cache->handleInvalidation($connector, $mutationRequest);

        // Verify the cached entry still exists
        expect($cache->get($connector, $getRequest))->not->toBeNull();
    });

    it('deserializes cached response with non-array data', function (): void {
        $store = createInMemoryCache();
        $config = new CacheConfig(store: $store);
        $cache = new RequestCache($config);
        $connector = createTestConnector();

        $request = new class() extends Request
        {
            public function endpoint(): string
            {
                return '/users';
            }

            public function method(): string
            {
                return 'GET';
            }
        };

        // Manually store a cached response with non-array data
        $keyGenerator = new CacheKeyGenerator($config);
        $key = $keyGenerator->generate($connector, $request);

        $store->set($key, [
            'status' => 200,
            'headers' => [],
            'data' => null, // Non-array data
            'cached_at' => Date::now()->getTimestamp(),
        ]);

        $cached = $cache->get($connector, $request);

        expect($cached)->not->toBeNull();
        expect($cached->json())->toBe([]);
    });

    it('combines InvalidatesCache with both tags and keys', function (): void {
        $store = createInMemoryCache();
        $config = new CacheConfig(store: $store);
        $cache = new RequestCache($config);
        $connector = createTestConnector();

        // Create and cache a GET request with tags
        $getRequest = new #[Cache(tags: ['users'])] class() extends Request
        {
            public function endpoint(): string
            {
                return '/users';
            }

            public function method(): string
            {
                return 'GET';
            }
        };

        $response = Response::make(['id' => 1], 200);
        $cache->put($connector, $getRequest, $response);

        // Manually add a specific key entry
        $store->set('api:user:special', ['special' => true]);

        expect($cache->get($connector, $getRequest))->not->toBeNull();
        expect($store->has('api:user:special'))->toBeTrue();

        // Create a mutation request that invalidates both tags and specific keys
        $mutationRequest = new #[InvalidatesCache(tags: ['users'], keys: ['api:user:special'])] class() extends Request
        {
            public function endpoint(): string
            {
                return '/users/reset';
            }

            public function method(): string
            {
                return 'POST';
            }
        };

        // Handle invalidation
        $cache->handleInvalidation($connector, $mutationRequest);

        // Verify both tagged entries and specific keys were invalidated
        expect($cache->get($connector, $getRequest))->toBeNull();
        expect($store->has('api:user:special'))->toBeFalse();
    });
});

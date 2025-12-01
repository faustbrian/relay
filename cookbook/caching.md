# Caching

Relay supports response caching to reduce API calls and improve performance. Caching is configured at the connector level and can be customized per-request using attributes.

## Overview

Caching in Relay:
- Uses PSR-16 Simple Cache interface
- Supports any Laravel cache store (Redis, Memcached, File, etc.)
- Allows per-request TTL and cache key customization
- Supports cache tags for selective invalidation
- Only caches successful responses (2xx status codes)

## Enabling Caching

Configure caching in your connector:

```php
use Cline\Relay\Features\Caching\CacheConfig;
use Cline\Relay\Core\Connector;
use Psr\SimpleCache\CacheInterface;

class GitHubConnector extends Connector
{
    public function baseUrl(): string
    {
        return 'https://api.github.com';
    }

    public function cache(): ?CacheInterface
    {
        return app('cache')->store('redis');
    }

    public function cacheTtl(): int
    {
        return 300; // 5 minutes default
    }

    public function cacheKeyPrefix(): string
    {
        return 'github_api';
    }

    public function cacheableMethods(): array
    {
        return ['GET', 'HEAD']; // Only cache these methods
    }
}
```

## Cache Configuration

Use `CacheConfig` for advanced configuration:

```php
use Cline\Relay\Features\Caching\CacheConfig;

public function cacheConfig(): ?CacheConfig
{
    return new CacheConfig(
        store: app('cache')->store('redis'), // Required: PSR-16 cache store
        hashAlgorithm: 'md5',                // Algorithm for hashing query/body
        maxKeyLength: null,                  // Max key length (null for unlimited)
        includeHeaders: false,               // Include headers in cache key
        prefix: 'api_cache',                 // Key prefix
        defaultTtl: 3600,                    // Default TTL in seconds
        cacheableMethods: ['GET', 'HEAD'],   // HTTP methods that can be cached
    );
}
```

## Request-Level Caching

### Enable Cache

Use the `#[Cache]` attribute on requests:

```php
use Cline\Relay\Support\Attributes\Caching\Cache;
use Cline\Relay\Support\Attributes\Methods\Get;
use Cline\Relay\Core\Request;

#[Get]
#[Cache(ttl: 3600)]
class GetUserRequest extends Request
{
    public function endpoint(): string
    {
        return '/users';
    }
}
```

### Cache with Tags

Tags allow selective cache invalidation:

```php
#[Get]
#[Cache(ttl: 3600, tags: ['users', 'profile'])]
class GetUserProfileRequest extends Request
{
    public function endpoint(): string
    {
        return "/users/{$this->userId}/profile";
    }
}
```

### Disable Cache

Skip caching for specific requests:

```php
use Cline\Relay\Support\Attributes\Caching\NoCache;

#[Get]
#[NoCache]
class GetCurrentStatusRequest extends Request
{
    public function endpoint(): string
    {
        return '/status';
    }
}
```

### Invalidate Cache

Invalidate cache tags after mutation requests:

```php
use Cline\Relay\Support\Attributes\Caching\InvalidatesCache;
use Cline\Relay\Support\Attributes\Methods\Post;

#[Post]
#[InvalidatesCache(tags: ['users'])]
class CreateUserRequest extends Request
{
    public function endpoint(): string
    {
        return '/users';
    }
}
```

## Cache Keys

Cache keys are generated from:
- Prefix (from connector)
- HTTP method
- Full URL (including query parameters)
- Request body (for POST/PUT/PATCH)

Example cache key: `github_api_GET_https://api.github.com/users?page=1`

### Custom Cache Keys

Override the cache key generation with a method:

```php
#[Cache(keyResolver: 'cacheKey')]
class GetUserRequest extends Request
{
    public function cacheKey(): string
    {
        return "user_{$this->userId}";
    }
}
```

### Cache Key Resolver Class

For reusable cache key logic, implement the `CacheKeyResolver` interface:

```php
use Cline\Relay\Support\Contracts\CacheKeyResolver;
use Cline\Relay\Core\Request;

class UserCacheKeyResolver implements CacheKeyResolver
{
    public function resolve(Request $request): string
    {
        // Generate consistent keys based on user context
        $userId = $request->userId ?? 'anonymous';
        $endpoint = $request->endpoint();

        return "user:{$userId}:{$endpoint}";
    }
}

// Use the resolver class
#[Cache(ttl: 3600, keyResolver: UserCacheKeyResolver::class)]
class GetUserDashboardRequest extends Request {}
```

## Checking Cache Status

Check if a response came from cache:

```php
$response = $connector->send(new GetUsersRequest());

if ($response->fromCache()) {
    // Response was served from cache
}
```

## Cache Management

### Forget Specific Request

```php
$request = new GetUserRequest(123);
$connector->forgetCache($request);
```

### Flush All Cache

```php
$connector->flushCache();
```

### Invalidate by Tags

```php
$connector->invalidateCacheTags(['users', 'profile']);
```

## Conditional Caching

### Cache Based on Response

Only cache successful responses:

```php
public function cacheConfig(): ?CacheConfig
{
    return new CacheConfig(
        ttl: 3600,
        shouldCache: fn (Response $response) => $response->ok(),
    );
}
```

### Cache Based on Content

```php
public function cacheConfig(): ?CacheConfig
{
    return new CacheConfig(
        ttl: 3600,
        shouldCache: fn (Response $response) =>
            $response->ok() && !$response->json('is_dynamic'),
    );
}
```

## Conditional Requests

Relay supports HTTP conditional requests with ETags and Last-Modified headers:

### ETag Support

```php
$response = $connector->send(new GetUserRequest(1));

// Get ETag for future requests
$etag = $response->etag();

// Check if response was 304 Not Modified
if ($response->wasNotModified()) {
    // Use cached version
}
```

### Last-Modified Support

```php
$response = $connector->send(new GetResourceRequest());

// Get Last-Modified as DateTimeImmutable
$lastModified = $response->lastModified();
```

## Cache Warmup

Pre-populate cache for frequently accessed data:

```php
class CacheWarmupCommand extends Command
{
    public function handle()
    {
        $connector = new GitHubConnector();

        // Warm up user cache
        foreach (User::all() as $user) {
            $connector->send(new GetGitHubProfileRequest($user->github_id));
        }
    }
}
```

## Cache Stores

### Redis (Recommended)

```php
public function cache(): ?CacheInterface
{
    return app('cache')->store('redis');
}
```

### Memcached

```php
public function cache(): ?CacheInterface
{
    return app('cache')->store('memcached');
}
```

### File Cache

```php
public function cache(): ?CacheInterface
{
    return app('cache')->store('file');
}
```

### Array (In-Memory)

For testing:

```php
public function cache(): ?CacheInterface
{
    return app('cache')->store('array');
}
```

## Best Practices

### 1. Choose Appropriate TTLs

```php
// Static data - long TTL
#[Cache(ttl: 86400)] // 24 hours
class GetCountriesRequest extends Request {}

// User data - medium TTL
#[Cache(ttl: 3600)] // 1 hour
class GetUserRequest extends Request {}

// Frequently changing - short TTL
#[Cache(ttl: 60)] // 1 minute
class GetPricesRequest extends Request {}
```

### 2. Use Tags for Related Data

```php
// All user-related requests share tags
#[Cache(ttl: 3600, tags: ['users'])]
class GetUsersRequest extends Request {}

#[Cache(ttl: 3600, tags: ['users', 'user-profile'])]
class GetUserProfileRequest extends Request {}

#[Cache(ttl: 3600, tags: ['users', 'user-orders'])]
class GetUserOrdersRequest extends Request {}

// Invalidate all user data at once
#[Post]
#[InvalidatesCache(tags: ['users'])]
class UpdateUserRequest extends Request {}
```

### 3. Don't Cache Sensitive Data

```php
// Never cache authentication tokens
#[NoCache]
class GetAccessTokenRequest extends Request {}

// Never cache payment data
#[NoCache]
class GetPaymentDetailsRequest extends Request {}
```

### 4. Cache at Multiple Levels

```php
class MyConnector extends Connector
{
    // Connector-level defaults
    public function cacheTtl(): int
    {
        return 300; // 5 minutes
    }
}

// Override per-request
#[Cache(ttl: 3600)] // 1 hour for this specific endpoint
class GetStaticDataRequest extends Request {}
```

## Full Example

Complete caching setup:

```php
<?php

namespace App\Http\Connectors;

use Cline\Relay\Features\Caching\CacheConfig;
use Cline\Relay\Core\Connector;
use Cline\Relay\Core\Response;
use Psr\SimpleCache\CacheInterface;

class CachedApiConnector extends Connector
{
    public function baseUrl(): string
    {
        return 'https://api.example.com/v1';
    }

    public function cache(): ?CacheInterface
    {
        return app('cache')->store('redis');
    }

    public function cacheConfig(): ?CacheConfig
    {
        return new CacheConfig(
            ttl: 1800, // 30 minutes default
            prefix: 'api_v1',
            shouldCache: fn (Response $response) =>
                $response->ok() &&
                !$response->header('X-No-Cache'),
        );
    }

    public function cacheableMethods(): array
    {
        return ['GET', 'HEAD'];
    }
}
```

Requests with caching:

```php
use Cline\Relay\Support\Attributes\Caching\Cache;
use Cline\Relay\Support\Attributes\Caching\InvalidatesCache;
use Cline\Relay\Support\Attributes\Caching\NoCache;

// Cached GET request
#[Get]
#[Cache(ttl: 3600, tags: ['products'])]
class GetProductsRequest extends Request
{
    public function endpoint(): string
    {
        return '/products';
    }
}

// Never cached
#[Get]
#[NoCache]
class GetInventoryRequest extends Request
{
    public function endpoint(): string
    {
        return '/inventory';
    }
}

// Invalidates product cache on creation
#[Post]
#[InvalidatesCache(tags: ['products'])]
class CreateProductRequest extends Request
{
    public function endpoint(): string
    {
        return '/products';
    }
}
```

Usage:

```php
$connector = new CachedApiConnector();

// First call - hits API, caches response
$response1 = $connector->send(new GetProductsRequest());
echo $response1->fromCache(); // false

// Second call - returns cached response
$response2 = $connector->send(new GetProductsRequest());
echo $response2->fromCache(); // true

// Create product - invalidates cache
$connector->send(new CreateProductRequest(['name' => 'New Product']));

// Third call - hits API again (cache was invalidated)
$response3 = $connector->send(new GetProductsRequest());
echo $response3->fromCache(); // false
```

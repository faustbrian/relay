---
title: Caching
description: Cache API responses to reduce calls and improve performance
---

Relay supports response caching to reduce API calls and improve performance.

## Overview

Caching in Relay:
- Uses PSR-16 Simple Cache interface
- Supports any Laravel cache store (Redis, Memcached, File, etc.)
- Allows per-request TTL and cache key customization
- Supports cache tags for selective invalidation
- Only caches successful responses (2xx status codes)

## Enabling Caching

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
        return ['GET', 'HEAD'];
    }
}
```

## Cache Configuration

```php
use Cline\Relay\Features\Caching\CacheConfig;

public function cacheConfig(): ?CacheConfig
{
    return new CacheConfig(
        store: app('cache')->store('redis'),
        hashAlgorithm: 'md5',
        maxKeyLength: null,
        includeHeaders: false,
        prefix: 'api_cache',
        defaultTtl: 3600,
        cacheableMethods: ['GET', 'HEAD'],
    );
}
```

## Request-Level Caching

### Enable Cache

```php
use Cline\Relay\Support\Attributes\Caching\Cache;
use Cline\Relay\Support\Attributes\Methods\Get;

#[Get]
#[Cache(ttl: 3600)]
class GetUserRequest extends Request {}
```

### Cache with Tags

```php
#[Get]
#[Cache(ttl: 3600, tags: ['users', 'profile'])]
class GetUserProfileRequest extends Request {}
```

### Disable Cache

```php
use Cline\Relay\Support\Attributes\Caching\NoCache;

#[Get]
#[NoCache]
class GetCurrentStatusRequest extends Request {}
```

### Invalidate Cache

```php
use Cline\Relay\Support\Attributes\Caching\InvalidatesCache;
use Cline\Relay\Support\Attributes\Methods\Post;

#[Post]
#[InvalidatesCache(tags: ['users'])]
class CreateUserRequest extends Request {}
```

## Custom Cache Keys

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

```php
use Cline\Relay\Support\Contracts\CacheKeyResolver;
use Cline\Relay\Core\Request;

class UserCacheKeyResolver implements CacheKeyResolver
{
    public function resolve(Request $request): string
    {
        $userId = $request->userId ?? 'anonymous';
        $endpoint = $request->endpoint();

        return "user:{$userId}:{$endpoint}";
    }
}

#[Cache(ttl: 3600, keyResolver: UserCacheKeyResolver::class)]
class GetUserDashboardRequest extends Request {}
```

## Checking Cache Status

```php
$response = $connector->send(new GetUsersRequest());

if ($response->fromCache()) {
    // Response was served from cache
}
```

## Cache Management

```php
// Forget specific request
$request = new GetUserRequest(123);
$connector->forgetCache($request);

// Flush all cache
$connector->flushCache();

// Invalidate by tags
$connector->invalidateCacheTags(['users', 'profile']);
```

## Conditional Caching

```php
public function cacheConfig(): ?CacheConfig
{
    return new CacheConfig(
        ttl: 3600,
        shouldCache: fn (Response $response) => $response->ok(),
    );
}

// Based on content
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

```php
$response = $connector->send(new GetUserRequest(1));

$etag = $response->etag();
$lastModified = $response->lastModified();

if ($response->wasNotModified()) {
    // Use cached version
}
```

## Cache Stores

```php
// Redis (Recommended)
public function cache(): ?CacheInterface
{
    return app('cache')->store('redis');
}

// Memcached
public function cache(): ?CacheInterface
{
    return app('cache')->store('memcached');
}

// File
public function cache(): ?CacheInterface
{
    return app('cache')->store('file');
}

// Array (In-Memory, for testing)
public function cache(): ?CacheInterface
{
    return app('cache')->store('array');
}
```

## Best Practices

### Choose Appropriate TTLs

```php
// Static data - long TTL
#[Cache(ttl: 86400)]
class GetCountriesRequest extends Request {}

// User data - medium TTL
#[Cache(ttl: 3600)]
class GetUserRequest extends Request {}

// Frequently changing - short TTL
#[Cache(ttl: 60)]
class GetPricesRequest extends Request {}
```

### Use Tags for Related Data

```php
#[Cache(ttl: 3600, tags: ['users'])]
class GetUsersRequest extends Request {}

#[Cache(ttl: 3600, tags: ['users', 'user-profile'])]
class GetUserProfileRequest extends Request {}

#[Post]
#[InvalidatesCache(tags: ['users'])]
class UpdateUserRequest extends Request {}
```

### Don't Cache Sensitive Data

```php
#[NoCache]
class GetAccessTokenRequest extends Request {}

#[NoCache]
class GetPaymentDetailsRequest extends Request {}
```

## Full Example

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
            ttl: 1800,
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

// Third call - hits API again
$response3 = $connector->send(new GetProductsRequest());
echo $response3->fromCache(); // false
```

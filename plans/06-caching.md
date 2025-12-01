# Caching

## Via Attributes

```php
// Cache for 60 seconds
#[Get, Json, Cache(60)]
class GetUser extends Request { ... }

// Cache with custom key
#[Get, Json, Cache(ttl: 3600, key: 'user.{id}')]
class GetUser extends Request
{
    public function __construct(
        private readonly int $id,
    ) {}

    public function endpoint(): string
    {
        return "/users/{$this->id}";
    }
}

// Cache with tags (for invalidation)
#[Get, Json, Cache(ttl: 3600, tags: ['users', 'api'])]
class GetUsers extends Request { ... }

// Disable caching for specific request (when connector has default cache)
#[Get, Json, NoCache]
class GetSensitiveData extends Request { ... }
```

## Connector-Level Caching

```php
class ApiConnector extends Connector
{
    public function __construct(
        private readonly CacheInterface $cache,
    ) {}

    public function cache(): ?CacheInterface
    {
        return $this->cache;
    }

    // Default TTL for all requests (can be overridden per-request)
    public function cacheTtl(): int
    {
        return 300; // 5 minutes
    }

    // Only cache these methods
    public function cacheableMethods(): array
    {
        return ['GET', 'HEAD'];
    }
}
```

## Cache Invalidation

```php
// Invalidate specific request
$connector->cache()->forget(new GetUser(1));

// Invalidate by tags
$connector->cache()->invalidateTags(['users']);

// Invalidate all
$connector->cache()->flush();

// Bust cache on mutation
#[Post, Json, InvalidatesCache(tags: ['users'])]
class CreateUser extends Request { ... }

#[Delete, Json, InvalidatesCache(GetUser::class)]
class DeleteUser extends Request
{
    public function __construct(
        private readonly int $id,
    ) {}

    public function endpoint(): string
    {
        return "/users/{$this->id}";
    }
}
```

## Laravel Integration

```php
// Uses Laravel's cache automatically
class ApiConnector extends Connector
{
    public function cache(): ?CacheInterface
    {
        return app('cache')->store('redis');
    }
}
```

## Cache Key Generation

How cache keys are derived by default:

```php
// Default key format: {connector}:{method}:{endpoint}:{query_hash}:{body_hash}
// Example: "ApiConnector:GET:/users:a1b2c3:d4e5f6"

$key = sprintf(
    '%s:%s:%s:%s:%s',
    $connector::class,
    $request->method(),
    $request->endpoint(),
    md5(serialize($request->query() ?? [])),
    md5(serialize($request->body() ?? [])),
);
```

### Custom Key via Attribute

```php
// Static key with placeholders
#[Get, Json, Cache(ttl: 3600, key: 'user.{id}')]
class GetUser extends Request
{
    public function __construct(
        private readonly int $id,
    ) {}
}
// Produces: "user.123"

// Multiple placeholders
#[Get, Json, Cache(ttl: 3600, key: 'users.{page}.{perPage}')]
class GetUsers extends Request
{
    public function __construct(
        private readonly int $page = 1,
        private readonly int $perPage = 25,
    ) {}
}
// Produces: "users.1.25"
```

### Custom Key via Method

```php
#[Get, Json, Cache(ttl: 3600, key: 'cacheKey')]
class GetUser extends Request
{
    public function __construct(
        private readonly int $id,
        private readonly ?string $include = null,
    ) {}

    public function cacheKey(): string
    {
        $key = "user.{$this->id}";

        if ($this->include) {
            $key .= ".with.{$this->include}";
        }

        return $key;
    }
}
```

### Connector-Level Key Prefix

```php
class ApiConnector extends Connector
{
    public function cacheKeyPrefix(): string
    {
        return 'api:v2:'; // All keys prefixed
    }
}
// Produces: "api:v2:user.123"
```

### Key Hashing Strategy

```php
class ApiConnector extends Connector
{
    public function cacheConfig(): CacheConfig
    {
        return new CacheConfig(
            store: app('cache')->store('redis'),

            // Hash algorithm for query/body
            hashAlgorithm: 'xxh3', // Faster than md5

            // Max key length (for cache stores with limits)
            maxKeyLength: 250,

            // Include headers in key (rarely needed)
            includeHeaders: false,
        );
    }
}
```

### Vary By User/Tenant

```php
#[Get, Json, Cache(ttl: 3600, key: 'cacheKey')]
class GetDashboard extends Request
{
    public function cacheKey(): string
    {
        // Different cache per user
        return sprintf(
            'dashboard.%s.%s',
            auth()->id(),
            $this->tenantId,
        );
    }
}
```

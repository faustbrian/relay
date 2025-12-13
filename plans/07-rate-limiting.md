# Rate Limiting

## Via Attributes

```php
// Simple rate limit: 100 requests per 60 seconds
#[Get, Json, RateLimit(100, 60)]
class GetUsers extends Request { ... }

// Named limiter (shared across requests)
#[Get, Json, RateLimit(100, 60, key: 'api')]
class GetUsers extends Request { ... }

#[Get, Json, RateLimit(100, 60, key: 'api')]
class GetOrders extends Request { ... } // Shares limit with GetUsers

// Per-resource limiting
#[Get, Json, RateLimit(10, 60, key: 'user.{id}')]
class GetUser extends Request { ... }
```

## Connector-Level Rate Limiting

```php
class ApiConnector extends Connector
{
    // Global rate limit for all requests
    public function rateLimit(): ?RateLimit
    {
        return new RateLimit(
            requests: 1000,
            perSeconds: 60,
        );
    }
}
```

## Handling Rate Limits

```php
// Default: throws RateLimitException when exceeded
try {
    $response = $connector->send($request);
} catch (RateLimitException $e) {
    $e->retryAfter();  // Seconds until retry (from header)
    $e->limit();       // Total limit
    $e->remaining();   // Remaining requests
}

// Auto-retry with backoff
#[Get, Json, RateLimit(100, 60, retry: true)]
class GetUsers extends Request { ... }

// Custom retry strategy
#[Get, Json, RateLimit(100, 60, retry: true, maxRetries: 3, backoff: 'exponential')]
class GetUsers extends Request { ... }
```

## Reading Rate Limit Headers

```php
$response = $connector->send($request);

$response->rateLimit()->limit();      // X-RateLimit-Limit
$response->rateLimit()->remaining();  // X-RateLimit-Remaining
$response->rateLimit()->reset();      // X-RateLimit-Reset (Carbon instance)
```

## Custom Rate Limit Store

```php
class ApiConnector extends Connector
{
    public function rateLimitStore(): RateLimitStore
    {
        // Default: in-memory (per-process)
        return new MemoryStore();

        // Redis (shared across processes)
        return new RedisStore($this->redis);

        // Laravel cache
        return new LaravelStore(app('cache')->store('redis'));
    }
}
```

## Concurrent Request Limiting

```php
// Max 5 concurrent requests
class ApiConnector extends Connector
{
    public function concurrencyLimit(): int
    {
        return 5;
    }
}

// Or via attribute for specific requests
#[Get, Json, ConcurrencyLimit(2)]
class HeavyRequest extends Request { ... }
```

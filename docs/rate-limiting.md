---
title: Rate Limiting
description: Client-side rate limiting and handling API quotas in Relay
---

Relay provides client-side rate limiting to prevent exceeding API quotas and handles server-side 429 responses gracefully.

## Connector-Level Rate Limiting

```php
use Cline\Relay\Core\Connector;
use Cline\Relay\Features\RateLimiting\RateLimitConfig;

class TwitterConnector extends Connector
{
    public function baseUrl(): string
    {
        return 'https://api.twitter.com/2';
    }

    public function rateLimit(): ?RateLimitConfig
    {
        return new RateLimitConfig(
            requests: 300,
            perSeconds: 900,  // 15 minute window
        );
    }
}
```

## Rate Limit Configuration

```php
$config = new RateLimitConfig(
    requests: 100,
    perSeconds: 60,
    retry: false,
    maxRetries: 3,
    backoff: 'exponential',
);
```

### Concurrency Limiting

```php
public function concurrencyLimit(): ?int
{
    return 10; // Max 10 concurrent requests
}
```

## Rate Limit Stores

### Memory Store (Default)

```php
use Cline\Relay\Features\RateLimiting\MemoryStore;

public function rateLimitStore(): RateLimitStore
{
    return new MemoryStore();
}
```

### Cache Store

```php
use Cline\Relay\Features\RateLimiting\CacheStore;

public function rateLimitStore(): RateLimitStore
{
    return new CacheStore(
        cache: app('cache')->store('redis'),
        prefix: 'rate_limit',
    );
}
```

## Request-Level Rate Limiting

```php
use Cline\Relay\Support\Attributes\RateLimiting\RateLimit;
use Cline\Relay\Support\Attributes\RateLimiting\ConcurrencyLimit;

#[Get]
#[RateLimit(maxAttempts: 10, decaySeconds: 60)]
#[ConcurrencyLimit(max: 5)]
class SearchRequest extends Request
{
    public function endpoint(): string
    {
        return '/search';
    }
}
```

## Handling Rate Limit Exceptions

### Client-Side Rate Limit

```php
use Cline\Relay\Support\Exceptions\Client\RateLimitException;

try {
    $response = $connector->send(new SearchRequest());
} catch (RateLimitException $e) {
    if ($e->isClientSide()) {
        $retryAfter = $e->retryAfter();
        $remaining = $e->remaining();
    }
}
```

### Server-Side Rate Limit (429)

```php
try {
    $response = $connector->send(new SearchRequest());
} catch (RateLimitException $e) {
    if ($e->isServerSide()) {
        $retryAfter = $e->retryAfter(); // From Retry-After header
    }
}
```

## Reading Rate Limit Headers

```php
$response = $connector->send(new GetUsersRequest());

$rateLimit = $response->rateLimit();

if ($rateLimit) {
    echo "Limit: {$rateLimit->limit}";
    echo "Remaining: {$rateLimit->remaining}";
    echo "Reset: {$rateLimit->reset}";
}
```

## Rate Limit with Retry

```php
#[Get]
#[RateLimit(maxAttempts: 100, decaySeconds: 60)]
#[Retry(times: 3, sleepMs: 1000, when: [429])]
class SearchRequest extends Request {}
```

## Custom Backoff Strategy

```php
use Cline\Relay\Support\Contracts\BackoffStrategy;

class FibonacciBackoffStrategy implements BackoffStrategy
{
    public function calculateDelay(Request $request, int $attempt, int $retryAfter = 0): int
    {
        if ($retryAfter > 0) {
            return $retryAfter * 1_000;
        }
        return $this->fibonacci($attempt) * 1_000;
    }

    private function fibonacci(int $n): int
    {
        if ($n <= 2) return 1;
        $a = 1; $b = 1;
        for ($i = 3; $i <= $n; $i++) {
            $c = $a + $b;
            $a = $b;
            $b = $c;
        }
        return $b;
    }
}

#[RateLimit(requests: 100, perSeconds: 60, backoff: FibonacciBackoffStrategy::class)]
class ApiRequest extends Request {}
```

## Rate Limit Buckets

Use different buckets for different endpoints:

```php
#[Get]
#[RateLimit(maxAttempts: 10, decaySeconds: 60, key: 'search')]
class SearchRequest extends Request {}

#[Get]
#[RateLimit(maxAttempts: 100, decaySeconds: 60, key: 'read')]
class GetDataRequest extends Request {}
```

## Best Practices

1. **Use persistent storage** - CacheStore for distributed systems
2. **Set conservative limits** - Stay below API limits
3. **Combine with retry** - Auto-retry on 429 responses
4. **Monitor remaining quota** - Check headers and log warnings

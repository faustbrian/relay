# Rate Limiting

Relay provides client-side rate limiting to prevent exceeding API quotas. It also handles server-side rate limit responses (429 Too Many Requests) gracefully.

## Overview

Rate limiting in Relay:
- Client-side limiting prevents exceeding quotas before making requests
- Supports multiple storage backends (Memory, Cache, Redis)
- Can limit by request count and/or concurrency
- Handles 429 responses with retry-after support
- Configurable at connector or request level

## Connector-Level Rate Limiting

Configure rate limits in your connector:

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
            requests: 300,     // Max 300 requests
            perSeconds: 900,   // Per 15 minute window
        );
    }
}
```

## Rate Limit Configuration

### Basic Configuration

```php
use Cline\Relay\Features\RateLimiting\RateLimitConfig;

$config = new RateLimitConfig(
    requests: 100,       // Maximum requests allowed
    perSeconds: 60,      // Time window in seconds
    retry: false,        // Auto-retry when rate limited
    maxRetries: 3,       // Maximum retry attempts
    backoff: 'exponential', // Backoff strategy: 'linear', 'exponential', or BackoffStrategy class
);
```

### Custom Backoff Strategy

Implement the `BackoffStrategy` interface for custom retry timing:

```php
use Cline\Relay\Support\Contracts\BackoffStrategy;
use Cline\Relay\Core\Request;

class FibonacciBackoffStrategy implements BackoffStrategy
{
    public function calculateDelay(Request $request, int $attempt, int $retryAfter = 0): int
    {
        // Respect server's Retry-After header if provided
        if ($retryAfter > 0) {
            return $retryAfter * 1_000;
        }

        // Fibonacci-like backoff: 1s, 1s, 2s, 3s, 5s, 8s...
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

// Use the strategy class
#[RateLimit(requests: 100, perSeconds: 60, backoff: FibonacciBackoffStrategy::class)]
class ApiRequest extends Request {}
```

### Concurrency Limiting

Limit concurrent requests:

```php
public function concurrencyLimit(): ?int
{
    return 10; // Max 10 concurrent requests
}
```

## Rate Limit Stores

### Memory Store (Default)

In-memory storage, resets on application restart:

```php
use Cline\Relay\Features\RateLimiting\MemoryStore;
use Cline\Relay\Support\Contracts\RateLimitStore;

public function rateLimitStore(): RateLimitStore
{
    return new MemoryStore();
}
```

### Cache Store

Use Laravel's cache for persistent rate limiting:

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

Override limits per request using attributes:

```php
use Cline\Relay\Support\Attributes\RateLimiting\RateLimit;
use Cline\Relay\Support\Attributes\Methods\Get;
use Cline\Relay\Core\Request;

#[Get]
#[RateLimit(maxAttempts: 10, decaySeconds: 60)]
class SearchRequest extends Request
{
    public function endpoint(): string
    {
        return '/search';
    }
}
```

### Concurrency Limit Attribute

```php
use Cline\Relay\Support\Attributes\RateLimiting\ConcurrencyLimit;

#[Get]
#[ConcurrencyLimit(max: 5)]
class HeavyComputeRequest extends Request
{
    public function endpoint(): string
    {
        return '/compute';
    }
}
```

## Handling Rate Limit Exceptions

### Client-Side Rate Limit

When you exceed your client-side rate limit:

```php
use Cline\Relay\Support\Exceptions\Client\RateLimitException;

try {
    $response = $connector->send(new SearchRequest());
} catch (RateLimitException $e) {
    if ($e->isClientSide()) {
        // Client-side limit hit
        $retryAfter = $e->retryAfter(); // Seconds to wait
        $limit = $e->limit();           // Total limit
        $remaining = $e->remaining();   // Remaining requests
    }
}
```

### Server-Side Rate Limit (429)

When the API returns 429:

```php
try {
    $response = $connector->send(new SearchRequest());
} catch (RateLimitException $e) {
    if ($e->isServerSide()) {
        // Server returned 429
        $retryAfter = $e->retryAfter(); // From Retry-After header
        $response = $e->response();     // Original response
    }
}
```

## Reading Rate Limit Headers

Parse rate limit information from responses:

```php
$response = $connector->send(new GetUsersRequest());

$rateLimit = $response->rateLimit();

if ($rateLimit) {
    echo "Limit: {$rateLimit->limit}";         // Total limit
    echo "Remaining: {$rateLimit->remaining}"; // Requests remaining
    echo "Reset: {$rateLimit->reset}";         // Unix timestamp
}
```

Relay automatically parses these headers:
- `X-RateLimit-Limit`
- `X-RateLimit-Remaining`
- `X-RateLimit-Reset`

## Rate Limit with Retry

Combine rate limiting with automatic retry:

```php
use Cline\Relay\Support\Attributes\RateLimiting\RateLimit;
use Cline\Relay\Support\Attributes\Resilience\Retry;

#[Get]
#[RateLimit(maxAttempts: 100, decaySeconds: 60)]
#[Retry(times: 3, sleepMs: 1000, when: [429])]
class SearchRequest extends Request
{
    public function endpoint(): string
    {
        return '/search';
    }
}
```

## Multiple Rate Limit Tiers

Handle APIs with multiple rate limit tiers:

```php
class TwitterConnector extends Connector
{
    // Different limits for different endpoint groups
    private array $limits = [
        'tweets' => ['max' => 300, 'window' => 900],
        'users' => ['max' => 900, 'window' => 900],
        'search' => ['max' => 180, 'window' => 900],
    ];

    public function rateLimit(): ?RateLimitConfig
    {
        // Default limit
        return new RateLimitConfig(
            maxAttempts: 300,
            decaySeconds: 900,
        );
    }
}

// Request-specific override
#[Get]
#[RateLimit(maxAttempts: 180, decaySeconds: 900)]
class SearchTweetsRequest extends Request {}
```

## Sliding Window vs Fixed Window

### Fixed Window (Default)

Rate limit resets at fixed intervals:

```php
$config = new RateLimitConfig(
    maxAttempts: 100,
    decaySeconds: 60,
);
```

### Sliding Window

For more even distribution, use a sliding window approach with middleware:

```php
use GuzzleHttp\HandlerStack;

public function middleware(): HandlerStack
{
    $stack = HandlerStack::create();

    $stack->push(new SlidingWindowRateLimiter(
        maxRequests: 100,
        windowSeconds: 60,
    ));

    return $stack;
}
```

## Rate Limit Buckets

Use different buckets for different endpoints:

```php
use Cline\Relay\Features\RateLimiting\RateLimitConfig;

class ApiConnector extends Connector
{
    public function rateLimit(): ?RateLimitConfig
    {
        return new RateLimitConfig(
            maxAttempts: 1000,
            decaySeconds: 3600,
            key: 'api_global', // Global bucket
        );
    }
}

// Endpoint-specific bucket
#[Get]
#[RateLimit(maxAttempts: 10, decaySeconds: 60, key: 'search')]
class SearchRequest extends Request {}

#[Get]
#[RateLimit(maxAttempts: 100, decaySeconds: 60, key: 'read')]
class GetDataRequest extends Request {}
```

## Waiting for Rate Limit

Automatically wait when rate limited:

```php
use Cline\Relay\Support\Exceptions\Client\RateLimitException;

$maxRetries = 3;
$attempt = 0;

while ($attempt < $maxRetries) {
    try {
        return $connector->send(new SearchRequest($query));
    } catch (RateLimitException $e) {
        $attempt++;

        if ($attempt >= $maxRetries) {
            throw $e;
        }

        $waitSeconds = $e->retryAfter() ?? 60;
        sleep($waitSeconds);
    }
}
```

## Rate Limit Monitoring

Track rate limit usage:

```php
use Cline\Relay\Features\Middleware\TimingMiddleware;

class MonitoredConnector extends Connector
{
    private int $requestCount = 0;

    public function middleware(): HandlerStack
    {
        $stack = HandlerStack::create();

        $stack->push(function (callable $handler) {
            return function ($request, $options) use ($handler) {
                $this->requestCount++;

                return $handler($request, $options)->then(
                    function ($response) {
                        $rateLimit = $response->getHeaderLine('X-RateLimit-Remaining');

                        if ($rateLimit && (int) $rateLimit < 10) {
                            logger()->warning('Rate limit running low', [
                                'remaining' => $rateLimit,
                            ]);
                        }

                        return $response;
                    }
                );
            };
        });

        return $stack;
    }

    public function getRequestCount(): int
    {
        return $this->requestCount;
    }
}
```

## Full Example

Complete rate limiting setup:

```php
<?php

namespace App\Http\Connectors;

use Cline\Relay\Core\Connector;
use Cline\Relay\Support\Contracts\RateLimitStore;
use Cline\Relay\Features\RateLimiting\CacheStore;
use Cline\Relay\Features\RateLimiting\RateLimitConfig;

class RateLimitedConnector extends Connector
{
    public function baseUrl(): string
    {
        return 'https://api.example.com/v1';
    }

    public function rateLimit(): ?RateLimitConfig
    {
        return new RateLimitConfig(
            maxAttempts: 1000,
            decaySeconds: 3600, // 1000 requests per hour
        );
    }

    public function concurrencyLimit(): ?int
    {
        return 25; // Max 25 concurrent requests
    }

    public function rateLimitStore(): RateLimitStore
    {
        return new CacheStore(
            cache: app('cache')->store('redis'),
            prefix: 'rate_limit_api',
        );
    }
}
```

Requests with specific limits:

```php
use Cline\Relay\Support\Attributes\RateLimiting\ConcurrencyLimit;
use Cline\Relay\Support\Attributes\RateLimiting\RateLimit;
use Cline\Relay\Support\Attributes\Resilience\Retry;

// Search endpoint with stricter limits
#[Get]
#[RateLimit(maxAttempts: 60, decaySeconds: 60)]
#[ConcurrencyLimit(max: 5)]
#[Retry(times: 2, sleepMs: 2000, when: [429])]
class SearchRequest extends Request
{
    public function endpoint(): string
    {
        return '/search';
    }
}

// Bulk endpoint with higher concurrency
#[Post]
#[RateLimit(maxAttempts: 100, decaySeconds: 60)]
#[ConcurrencyLimit(max: 10)]
class BulkImportRequest extends Request
{
    public function endpoint(): string
    {
        return '/bulk/import';
    }
}
```

Usage with error handling:

```php
use Cline\Relay\Support\Exceptions\Client\RateLimitException;

$connector = new RateLimitedConnector();

try {
    $response = $connector->send(new SearchRequest($query));

    // Check remaining quota
    $rateLimit = $response->rateLimit();
    if ($rateLimit && $rateLimit->remaining < 10) {
        cache()->put('api_rate_limited', true, $rateLimit->reset - time());
    }

    return $response->json();

} catch (RateLimitException $e) {
    // Log the rate limit hit
    logger()->warning('Rate limit exceeded', [
        'client_side' => $e->isClientSide(),
        'retry_after' => $e->retryAfter(),
        'limit' => $e->limit(),
        'remaining' => $e->remaining(),
    ]);

    // Queue for retry
    dispatch(new RetrySearchJob($query))
        ->delay(now()->addSeconds($e->retryAfter() ?? 60));

    throw $e;
}
```

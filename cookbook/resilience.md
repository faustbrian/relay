# Resilience

Relay provides resilience patterns to handle transient failures gracefully. This includes automatic retries, circuit breakers, and timeouts.

## Overview

Resilience features in Relay:
- Automatic retries with configurable backoff
- Circuit breaker pattern to prevent cascading failures
- Configurable timeouts at connector and request level
- Works via attributes or connector configuration

## Retry Configuration

### Basic Retry

Add automatic retries using the `#[Retry]` attribute:

```php
use Cline\Relay\Support\Attributes\Methods\Get;
use Cline\Relay\Support\Attributes\Resilience\Retry;
use Cline\Relay\Core\Request;

#[Get]
#[Retry(times: 3)]
class GetDataRequest extends Request
{
    public function endpoint(): string
    {
        return '/data';
    }
}
```

### Retry with Delay

Add delay between retries:

```php
#[Retry(times: 3, delay: 1000)] // 1 second between retries
class GetDataRequest extends Request {}
```

### Exponential Backoff

Use a multiplier for exponential backoff:

```php
#[Retry(times: 3, delay: 100, multiplier: 2.0, maxDelay: 30000)]
// Retry 1: wait 100ms
// Retry 2: wait 200ms
// Retry 3: wait 400ms
class GetDataRequest extends Request {}
```

### Retry on Specific Status Codes

Only retry on certain HTTP status codes:

```php
#[Retry(times: 3, delay: 500, when: [429, 500, 502, 503, 504])]
class GetDataRequest extends Request {}
```

### Custom Retry Condition

Use a callback method to determine if retry should occur:

```php
use Cline\Relay\Core\Response;

#[Retry(times: 3, callback: 'shouldRetry')]
class GetDataRequest extends Request
{
    public function shouldRetry(Response $response, int $attempt): bool
    {
        // Retry on server errors or specific error codes
        if ($response->serverError()) {
            return true;
        }

        $errorCode = $response->json('error.code');
        return in_array($errorCode, ['TEMPORARY_ERROR', 'SERVICE_BUSY']);
    }
}
```

### Retry Decider Class

For reusable retry logic, implement the `RetryDecider` interface:

```php
use Cline\Relay\Support\Contracts\RetryDecider;
use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;

class RateLimitRetryDecider implements RetryDecider
{
    public function __invoke(Request $request, Response $response, int $attempt): bool
    {
        // Only retry on rate limiting
        if ($response->status() !== 429) {
            return false;
        }

        // Check if we have retry-after header
        return $response->header('Retry-After') !== null;
    }
}

// Use the decider class
#[Retry(times: 3, callback: RateLimitRetryDecider::class)]
class ApiRequest extends Request {}
```

### Retry Policy Class

For complete control over retry behavior, implement the `RetryPolicy` interface:

```php
use Cline\Relay\Support\Contracts\RetryPolicy;
use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;
use Throwable;

class ExponentialBackoffPolicy implements RetryPolicy
{
    public function times(): int
    {
        return 5;
    }

    public function delay(): int
    {
        return 200; // 200ms initial delay
    }

    public function multiplier(): float
    {
        return 2.0;
    }

    public function maxDelay(): int
    {
        return 30_000; // 30 seconds max
    }

    public function shouldRetry(Request $request, Response $response, int $attempt): bool
    {
        // Retry on server errors and rate limits
        return $response->serverError() || $response->status() === 429;
    }

    public function shouldRetryException(Request $request, Throwable $exception, int $attempt): bool
    {
        // Retry on connection timeouts
        return $exception instanceof ConnectionException;
    }
}

// Use the policy class - it overrides all other retry configuration
#[Retry(policy: ExponentialBackoffPolicy::class)]
class ResilientRequest extends Request {}
```

## Timeout Configuration

### Request Timeout

Set timeout for specific requests:

```php
use Cline\Relay\Support\Attributes\Resilience\Timeout;

#[Get]
#[Timeout(seconds: 5)]
class QuickCheckRequest extends Request {}
```

### Connection and Request Timeout

Configure both connection and request timeouts:

```php
#[Timeout(seconds: 30, connectSeconds: 5)]
class SlowEndpointRequest extends Request {}
```

### Connector-Level Timeout

Set default timeouts in the connector:

```php
class MyConnector extends Connector
{
    public function timeout(): int
    {
        return 30; // Request timeout in seconds
    }

    public function connectTimeout(): int
    {
        return 10; // Connection timeout in seconds
    }
}
```

## Circuit Breaker

The circuit breaker prevents repeated calls to a failing service.

### Basic Circuit Breaker

```php
use Cline\Relay\Support\Attributes\Resilience\CircuitBreaker;

#[Get]
#[CircuitBreaker(
    failureThreshold: 5,    // Open after 5 consecutive failures
    resetTimeout: 30,       // Wait 30 seconds before trying again
)]
class UnreliableApiRequest extends Request {}
```

### Circuit Breaker with Success Threshold

Require multiple successes before fully closing:

```php
#[CircuitBreaker(
    failureThreshold: 5,     // Open after 5 failures
    resetTimeout: 30,        // Wait 30 seconds
    successThreshold: 3,     // Require 3 successes to close
)]
class UnreliableApiRequest extends Request {}
```

### Circuit Breaker States

The circuit breaker has three states:

1. **Closed** - Normal operation, requests flow through
2. **Open** - Requests fail immediately without calling the API
3. **Half-Open** - Limited requests allowed to test if service recovered

```php
use Cline\Relay\Features\Resilience\CircuitBreaker;
use Cline\Relay\Features\Resilience\CircuitState;

$breaker = new CircuitBreaker($config, $store);

// Check current state
$state = $breaker->state();

match ($state) {
    CircuitState::Closed => 'Normal operation',
    CircuitState::Open => 'Circuit is open, requests blocked',
    CircuitState::HalfOpen => 'Testing if service recovered',
};
```

### Circuit Breaker Policy Class

For complete control over circuit breaker behavior, implement the `CircuitBreakerPolicy` interface:

```php
use Cline\Relay\Support\Contracts\CircuitBreakerPolicy;
use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;
use Throwable;

class ApiCircuitBreakerPolicy implements CircuitBreakerPolicy
{
    public function failureThreshold(): int
    {
        return 5;
    }

    public function resetTimeout(): int
    {
        return 30;
    }

    public function halfOpenRequests(): int
    {
        return 3;
    }

    public function failureWindow(): int
    {
        return 60;
    }

    public function successThreshold(): int
    {
        return 2;
    }

    public function isFailure(Request $request, Response $response): bool
    {
        // Only server errors are failures
        return $response->serverError();
    }

    public function isExceptionFailure(Request $request, Throwable $exception): bool
    {
        // Connection timeouts are failures
        return $exception instanceof ConnectionException;
    }

    public function onOpen(string $key): void
    {
        logger()->warning("Circuit opened: {$key}");
        Notification::send(new CircuitOpenedNotification($key));
    }

    public function onClose(string $key): void
    {
        logger()->info("Circuit closed: {$key}");
    }

    public function onHalfOpen(string $key): void
    {
        logger()->info("Circuit half-open: {$key}");
    }
}

// Use the policy class
#[CircuitBreaker(policy: ApiCircuitBreakerPolicy::class)]
class ApiRequest extends Request {}
```

### Circuit Breaker Storage

Use persistent storage for circuit breaker state:

```php
use Cline\Relay\Features\Resilience\MemoryCircuitStore;

class MyConnector extends Connector
{
    public function circuitBreakerStore(): MemoryCircuitStore
    {
        return new MemoryCircuitStore();
    }
}
```

For distributed systems, implement a Redis-based store:

```php
class RedisCircuitStore implements CircuitStore
{
    public function __construct(
        private readonly Redis $redis,
        private readonly string $prefix = 'circuit:',
    ) {}

    public function get(string $key): ?CircuitState
    {
        $state = $this->redis->get($this->prefix . $key);
        return $state ? CircuitState::from($state) : null;
    }

    public function set(string $key, CircuitState $state, int $ttl): void
    {
        $this->redis->setex($this->prefix . $key, $ttl, $state->value);
    }
}
```

## Combining Resilience Patterns

Use multiple patterns together for robust error handling:

```php
use Cline\Relay\Support\Attributes\Resilience\CircuitBreaker;
use Cline\Relay\Support\Attributes\Resilience\Retry;
use Cline\Relay\Support\Attributes\Resilience\Timeout;

#[Get]
#[Timeout(seconds: 10)]
#[Retry(times: 3, sleepMs: 500, multiplier: 2, when: [500, 502, 503])]
#[CircuitBreaker(failureThreshold: 5, resetTimeout: 60)]
class ResilientRequest extends Request
{
    public function endpoint(): string
    {
        return '/api/data';
    }
}
```

Execution order:
1. **Timeout** - Each attempt has a 10-second limit
2. **Retry** - If timeout or 5xx error, retry up to 3 times with backoff
3. **Circuit Breaker** - If 5 consecutive failures, open circuit

## Retry Handler

For programmatic retry control:

```php
use Cline\Relay\Features\Resilience\RetryConfig;
use Cline\Relay\Features\Resilience\RetryHandler;

$config = new RetryConfig(
    times: 3,
    sleepMs: 100,
    multiplier: 2.0,
    when: fn (Response $r) => $r->serverError(),
);

$handler = new RetryHandler($config);

// Check if should retry
$shouldRetry = $handler->shouldRetryResponse($request, $response, $attempt);

// Get delay for next attempt
$delay = $handler->getDelay($attempt); // milliseconds
```

## Error Handling Patterns

### Graceful Degradation

Fall back to cached or default data:

```php
try {
    $response = $connector->send(new GetProductsRequest());
    cache()->put('products', $response->json(), 3600);
    return $response->json();
} catch (RequestException $e) {
    // Return cached data if available
    if ($cached = cache()->get('products')) {
        logger()->warning('Using cached products due to API failure');
        return $cached;
    }

    // Return default data
    return ['products' => [], 'error' => 'Service temporarily unavailable'];
}
```

### Bulkhead Pattern

Isolate failures by using separate connectors for different services:

```php
class PaymentConnector extends Connector
{
    // Separate circuit breaker and rate limits
    public function rateLimit(): ?RateLimitConfig
    {
        return new RateLimitConfig(maxAttempts: 100, decaySeconds: 60);
    }
}

class InventoryConnector extends Connector
{
    // Independent from payment service failures
    public function rateLimit(): ?RateLimitConfig
    {
        return new RateLimitConfig(maxAttempts: 500, decaySeconds: 60);
    }
}
```

### Timeout Strategies

```php
// Fast fail for user-facing requests
#[Timeout(seconds: 3)]
class QuickLookupRequest extends Request {}

// Longer timeout for background jobs
#[Timeout(seconds: 60)]
class BulkProcessRequest extends Request {}

// Very long timeout for reports
#[Timeout(seconds: 300)]
class GenerateReportRequest extends Request {}
```

## Observability

Monitor resilience behavior:

```php
use Cline\Relay\Observability\RequestHooks;

class MyConnector extends Connector
{
    public function hooks(): RequestHooks
    {
        return new RequestHooks(
            onRetry: function (Request $request, int $attempt, int $maxAttempts) {
                logger()->warning("Retry attempt {$attempt}/{$maxAttempts}", [
                    'request' => $request->endpoint(),
                ]);

                metrics()->increment('api.retries');
            },

            onCircuitOpen: function (string $key) {
                logger()->error("Circuit breaker opened for {$key}");
                metrics()->increment('api.circuit_opened');
            },

            onCircuitClose: function (string $key) {
                logger()->info("Circuit breaker closed for {$key}");
            },
        );
    }
}
```

## Full Example

Complete resilient connector:

```php
<?php

namespace App\Http\Connectors;

use Cline\Relay\Core\Connector;
use Cline\Relay\Features\Resilience\CircuitBreakerConfig;
use Cline\Relay\Features\Resilience\MemoryCircuitStore;

class ResilientApiConnector extends Connector
{
    public function baseUrl(): string
    {
        return 'https://api.example.com/v1';
    }

    public function timeout(): int
    {
        return 15; // 15 second default timeout
    }

    public function connectTimeout(): int
    {
        return 5; // 5 second connection timeout
    }

    public function circuitBreakerStore(): MemoryCircuitStore
    {
        return new MemoryCircuitStore();
    }
}
```

Resilient request:

```php
use Cline\Relay\Support\Attributes\Resilience\CircuitBreaker;
use Cline\Relay\Support\Attributes\Resilience\Retry;
use Cline\Relay\Support\Attributes\Resilience\Timeout;
use Cline\Relay\Support\Attributes\ThrowOnError;
use Cline\Relay\Core\Response;

#[Get]
#[Timeout(seconds: 10, connectSeconds: 3)]
#[Retry(
    times: 3,
    sleepMs: 200,
    multiplier: 2,
    whenCallback: 'shouldRetry',
)]
#[CircuitBreaker(
    failureThreshold: 5,
    resetTimeout: 30,
    successThreshold: 2,
)]
#[ThrowOnError(serverErrors: true)]
class GetCriticalDataRequest extends Request
{
    public function endpoint(): string
    {
        return '/critical/data';
    }

    public function shouldRetry(Response $response): bool
    {
        // Retry on server errors
        if ($response->serverError()) {
            return true;
        }

        // Retry on specific error codes
        $code = $response->json('error.code');
        return in_array($code, ['TEMPORARY_FAILURE', 'TRY_AGAIN']);
    }
}
```

Usage with fallback:

```php
use Cline\Relay\Support\Exceptions\RequestException;

$connector = new ResilientApiConnector();

try {
    $response = $connector->send(new GetCriticalDataRequest());
    return $response->json();

} catch (RequestException $e) {
    // Log the failure
    logger()->error('Critical data fetch failed', [
        'status' => $e->status(),
        'message' => $e->getMessage(),
    ]);

    // Check circuit breaker state
    if ($e->getMessage() === 'Circuit breaker is open') {
        // Service is known to be down
        return $this->getFallbackData();
    }

    // Rethrow for caller to handle
    throw $e;
}
```

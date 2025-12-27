---
title: Resilience
description: Retries, circuit breakers, and timeouts for handling transient failures in Relay
---

Relay provides resilience patterns to handle transient failures gracefully.

## Retry Configuration

### Basic Retry

```php
use Cline\Relay\Support\Attributes\Resilience\Retry;

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

```php
#[Retry(times: 3, delay: 1000)] // 1 second between retries
class GetDataRequest extends Request {}
```

### Exponential Backoff

```php
#[Retry(times: 3, delay: 100, multiplier: 2.0, maxDelay: 30000)]
// Retry 1: wait 100ms
// Retry 2: wait 200ms
// Retry 3: wait 400ms
class GetDataRequest extends Request {}
```

### Retry on Specific Status Codes

```php
#[Retry(times: 3, delay: 500, when: [429, 500, 502, 503, 504])]
class GetDataRequest extends Request {}
```

### Custom Retry Condition

```php
#[Retry(times: 3, callback: 'shouldRetry')]
class GetDataRequest extends Request
{
    public function shouldRetry(Response $response, int $attempt): bool
    {
        if ($response->serverError()) {
            return true;
        }
        $errorCode = $response->json('error.code');
        return in_array($errorCode, ['TEMPORARY_ERROR', 'SERVICE_BUSY']);
    }
}
```

## Timeout Configuration

### Request Timeout

```php
use Cline\Relay\Support\Attributes\Resilience\Timeout;

#[Get]
#[Timeout(seconds: 5)]
class QuickCheckRequest extends Request {}
```

### Connection and Request Timeout

```php
#[Timeout(seconds: 30, connectSeconds: 5)]
class SlowEndpointRequest extends Request {}
```

### Connector-Level Timeout

```php
class MyConnector extends Connector
{
    public function timeout(): int
    {
        return 30;
    }

    public function connectTimeout(): int
    {
        return 10;
    }
}
```

## Circuit Breaker

Prevents repeated calls to a failing service.

### Basic Circuit Breaker

```php
use Cline\Relay\Support\Attributes\Resilience\CircuitBreaker;

#[Get]
#[CircuitBreaker(
    failureThreshold: 5,
    resetTimeout: 30,
)]
class UnreliableApiRequest extends Request {}
```

### Circuit Breaker with Success Threshold

```php
#[CircuitBreaker(
    failureThreshold: 5,
    resetTimeout: 30,
    successThreshold: 3,  // Require 3 successes to close
)]
class UnreliableApiRequest extends Request {}
```

### Circuit Breaker States

1. **Closed** - Normal operation, requests flow through
2. **Open** - Requests fail immediately without calling the API
3. **Half-Open** - Limited requests allowed to test if service recovered

### Circuit Breaker Policy

```php
use Cline\Relay\Support\Contracts\CircuitBreakerPolicy;

class ApiCircuitBreakerPolicy implements CircuitBreakerPolicy
{
    public function failureThreshold(): int { return 5; }
    public function resetTimeout(): int { return 30; }
    public function successThreshold(): int { return 2; }

    public function isFailure(Request $request, Response $response): bool
    {
        return $response->serverError();
    }

    public function onOpen(string $key): void
    {
        logger()->warning("Circuit opened: {$key}");
    }
}

#[CircuitBreaker(policy: ApiCircuitBreakerPolicy::class)]
class ApiRequest extends Request {}
```

## Combining Resilience Patterns

```php
#[Get]
#[Timeout(seconds: 10)]
#[Retry(times: 3, sleepMs: 500, multiplier: 2, when: [500, 502, 503])]
#[CircuitBreaker(failureThreshold: 5, resetTimeout: 60)]
class ResilientRequest extends Request {}
```

Execution order:
1. **Timeout** - Each attempt has a 10-second limit
2. **Retry** - If timeout or 5xx error, retry up to 3 times with backoff
3. **Circuit Breaker** - If 5 consecutive failures, open circuit

## Error Handling Patterns

### Graceful Degradation

```php
try {
    $response = $connector->send(new GetProductsRequest());
    cache()->put('products', $response->json(), 3600);
    return $response->json();
} catch (RequestException $e) {
    if ($cached = cache()->get('products')) {
        return $cached;
    }
    return ['products' => [], 'error' => 'Service temporarily unavailable'];
}
```

### Bulkhead Pattern

Isolate failures by using separate connectors:

```php
class PaymentConnector extends Connector
{
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

## Best Practices

1. **Combine patterns** - Timeout + Retry + Circuit Breaker
2. **Use exponential backoff** - Avoid thundering herd
3. **Set appropriate thresholds** - Balance availability and protection
4. **Log circuit state changes** - Monitor service health

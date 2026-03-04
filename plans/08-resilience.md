# Resilience

## Timeouts

### Via Attribute

```php
// Request timeout in seconds
#[Get, Json, Timeout(30)]
class SlowRequest extends Request { ... }

// Separate connect and read timeouts
#[Get, Json, Timeout(connect: 5, read: 30)]
class SlowRequest extends Request { ... }
```

### Connector-Level

```php
class ApiConnector extends Connector
{
    public function timeout(): int
    {
        return 30; // Default for all requests
    }

    public function connectTimeout(): int
    {
        return 5;
    }
}
```

## Retry

### Via Attribute

```php
// Retry up to 3 times on failure
#[Get, Json, Retry(3)]
class UnreliableRequest extends Request { ... }

// With custom configuration
#[Get, Json, Retry(
    times: 3,
    delay: 1000,           // ms between retries (default: 100)
    multiplier: 2,          // exponential backoff multiplier
    maxDelay: 30000,        // cap delay at 30 seconds
    when: [500, 502, 503],  // only retry these status codes
)]
class UnreliableRequest extends Request { ... }

// Retry on specific exceptions
#[Get, Json, Retry(3, exceptions: [ConnectionException::class, TimeoutException::class])]
class UnreliableRequest extends Request { ... }
```

### Connector-Level

```php
class ApiConnector extends Connector
{
    public function retry(): ?RetryConfig
    {
        return new RetryConfig(
            times: 3,
            delay: 100,
            multiplier: 2,
            when: fn(Response $r) => $r->serverError(),
        );
    }
}
```

### Custom Retry Logic

```php
#[Get, Json, Retry(3, when: 'shouldRetry')]
class SmartRetryRequest extends Request
{
    public function shouldRetry(Response $response, int $attempt): bool
    {
        // Retry on rate limit with backoff
        if ($response->status() === 429) {
            sleep($response->header('Retry-After') ?? $attempt * 2);
            return true;
        }

        // Retry on server errors
        return $response->serverError();
    }
}
```

## Circuit Breaker

Prevents cascade failures by fast-failing after repeated errors.

### Via Attribute

```php
#[Get, Json, CircuitBreaker(
    failureThreshold: 5,    // Open circuit after 5 failures
    resetTimeout: 30,       // Try again after 30 seconds
    halfOpenRequests: 3,    // Allow 3 requests in half-open state
)]
class UnstableApiRequest extends Request { ... }
```

### Threshold Configuration Examples

```php
// Conservative: slow to open, quick to recover
#[CircuitBreaker(
    failureThreshold: 10,     // Need 10 failures to open
    failureWindow: 60,        // Within 60 seconds
    resetTimeout: 15,         // Try again after 15 seconds
    halfOpenRequests: 5,      // Allow 5 test requests
    successThreshold: 3,      // Need 3 successes to fully close
)]
class CriticalPaymentRequest extends Request { ... }

// Aggressive: fast to open, slow to recover
#[CircuitBreaker(
    failureThreshold: 3,      // Open after just 3 failures
    failureWindow: 30,        // Within 30 seconds
    resetTimeout: 60,         // Wait 60 seconds before retry
    halfOpenRequests: 1,      // Only 1 test request
    successThreshold: 5,      // Need 5 successes to close
)]
class NonCriticalRequest extends Request { ... }

// Percentage-based threshold
#[CircuitBreaker(
    failurePercentage: 50,    // Open when 50% of requests fail
    minimumRequests: 10,      // Need at least 10 requests to evaluate
    failureWindow: 60,        // Within 60 second window
    resetTimeout: 30,
)]
class HighVolumeRequest extends Request { ... }
```

### Sliding Window Configuration

```php
class ApiConnector extends Connector
{
    public function circuitBreaker(): ?CircuitBreakerConfig
    {
        return new CircuitBreakerConfig(
            // Count-based sliding window
            windowType: 'count',
            windowSize: 100,           // Last 100 requests
            failureThreshold: 50,      // 50% failure rate opens circuit

            // Or time-based sliding window
            // windowType: 'time',
            // windowSize: 60,          // Last 60 seconds
            // failureThreshold: 10,    // 10 failures opens circuit
        );
    }
}
```

### Connector-Level

```php
class ApiConnector extends Connector
{
    public function circuitBreaker(): ?CircuitBreakerConfig
    {
        return new CircuitBreakerConfig(
            failureThreshold: 5,
            resetTimeout: 30,
            halfOpenRequests: 3,
            // What counts as failure
            failureCondition: fn(Response $r) => $r->serverError(),
        );
    }
}
```

### Circuit States

```php
// Check circuit state
$connector->circuit()->state();  // 'closed', 'open', 'half-open'
$connector->circuit()->isOpen();
$connector->circuit()->isClosed();

// Manual control
$connector->circuit()->open();   // Force open
$connector->circuit()->close();  // Force close
$connector->circuit()->reset();  // Reset failure count

// Throws CircuitOpenException when open
try {
    $response = $connector->send($request);
} catch (CircuitOpenException $e) {
    $e->retryAfter();  // Seconds until circuit resets
}
```

### Per-Service Circuit Breakers

```php
class ApiConnector extends Connector
{
    public function circuitBreaker(): ?CircuitBreakerConfig
    {
        return new CircuitBreakerConfig(
            failureThreshold: 5,
            resetTimeout: 30,
            // Separate circuits per base URL or key
            key: $this->baseUrl(),
        );
    }
}
```

### Circuit Breaker Storage

```php
class ApiConnector extends Connector
{
    public function circuitBreakerStore(): CircuitBreakerStore
    {
        // Default: in-memory (per-process)
        return new MemoryStore();

        // Redis (shared across processes/servers)
        return new RedisStore($this->redis, prefix: 'circuit:');

        // Laravel cache
        return new LaravelStore(app('cache')->store('redis'));
    }
}
```

### Events

```php
class ApiConnector extends Connector
{
    public function circuitBreaker(): ?CircuitBreakerConfig
    {
        return new CircuitBreakerConfig(
            failureThreshold: 5,
            resetTimeout: 30,
            onOpen: function (string $key) {
                Log::warning("Circuit opened for {$key}");
                Notification::send(new CircuitOpenedNotification($key));
            },
            onClose: function (string $key) {
                Log::info("Circuit closed for {$key}");
            },
            onHalfOpen: function (string $key) {
                Log::info("Circuit half-open for {$key}");
            },
        );
    }
}
```

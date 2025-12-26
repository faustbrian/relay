# Idempotency

Automatic idempotency key generation for safe request retries.

## Via Attribute

```php
#[Post, Json, Idempotent]
class CreatePayment extends Request
{
    public function endpoint(): string
    {
        return '/payments';
    }

    public function body(): ?array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
        ];
    }
}

// Auto-generates: Idempotency-Key: <uuid>
```

## Custom Header Name

```php
// Different APIs use different header names
#[Post, Json, Idempotent(header: 'X-Request-Id')]
class CreateOrder extends Request { ... }

#[Post, Json, Idempotent(header: 'X-Idempotency-Key')]
class CreateCharge extends Request { ... }
```

## Custom Key Generation

```php
// Generate key from request data (deterministic)
#[Post, Json, Idempotent(key: 'generateKey')]
class CreatePayment extends Request
{
    public function __construct(
        private readonly string $orderId,
        private readonly int $amount,
    ) {}

    public function generateKey(): string
    {
        // Same order+amount = same key = idempotent
        return hash('sha256', "{$this->orderId}:{$this->amount}");
    }
}
```

## Connector-Level Default

```php
#[Idempotent(header: 'Idempotency-Key')]
class StripeConnector extends Connector
{
    // All POST/PUT/PATCH requests get idempotency keys
}

// Override or disable per-request
#[Post, Json, Idempotent(false)]
class CreateWebhook extends Request
{
    // No idempotency key for this request
}
```

## Explicit Key

```php
$request = new CreatePayment(
    orderId: 'order_123',
    amount: 5000,
);

// Override auto-generated key
$request->withIdempotencyKey('my-custom-key-123');

$connector->send($request);
```

## Idempotency with Retry

```php
#[Post, Json, Idempotent, Retry(times: 3)]
class CreatePayment extends Request { ... }

// Same idempotency key used for all retry attempts
// Server should return same response for duplicate keys
```

## Key Storage

Track used keys to prevent accidental reuse:

```php
class IdempotencyConfig
{
    public function __construct(
        // Store to track used keys
        public readonly ?IdempotencyStore $store = null,

        // TTL for stored keys
        public readonly int $ttl = 86400, // 24 hours

        // Throw if key was already used
        public readonly bool $throwOnDuplicate = true,
    ) {}
}

class ApiConnector extends Connector
{
    public function idempotency(): ?IdempotencyConfig
    {
        return new IdempotencyConfig(
            store: new RedisIdempotencyStore(app('redis')),
            ttl: 3600,
            throwOnDuplicate: true,
        );
    }
}
```

## Response Handling

```php
$response = $connector->send(new CreatePayment(...));

// Check if response came from idempotent replay
$response->wasIdempotentReplay(); // true if server returned cached response

// Get the idempotency key that was used
$response->idempotencyKey(); // "abc123..."
```

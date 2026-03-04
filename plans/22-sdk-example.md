# Full SDK Example

A complete Stripe SDK demonstrating connectors, requests, resources, DTOs, and testing.

See also:
- [01-requests.md](01-requests.md) - Request attributes and base class
- [02-connectors.md](02-connectors.md) - Connector configuration
- [03-responses.md](03-responses.md) - Response and DTO mapping
- [04-authentication.md](04-authentication.md) - Authentication strategies
- [20-testing.md](20-testing.md) - Testing patterns
- [26-idempotency.md](26-idempotency.md) - Idempotency for payments

## Connector

```php
use Saloon\Attributes\ThrowOnError;

#[ThrowOnError] // Stripe errors should throw
class StripeConnector extends Connector
{
    public function __construct(
        private readonly string $secretKey,
    ) {}

    public function baseUrl(): string
    {
        return 'https://api.stripe.com/v1';
    }

    public function authenticate(Request $request): void
    {
        $request->withBasicAuth($this->secretKey, '');
    }

    public function defaultHeaders(): array
    {
        return [
            'Stripe-Version' => '2024-01-01',
        ];
    }

    // Resource accessors
    public function customers(): CustomerResource
    {
        return new CustomerResource($this);
    }

    public function charges(): ChargeResource
    {
        return new ChargeResource($this);
    }
}

// Resource (groups related requests)
class CustomerResource extends Resource
{
    public function list(int $limit = 10): Collection
    {
        return $this->connector
            ->send(new ListCustomers($limit))
            ->dtoCollection();
    }

    public function get(string $id): CustomerDto
    {
        return $this->connector
            ->send(new GetCustomer($id))
            ->dto();
    }

    public function create(string $email, ?string $name = null): CustomerDto
    {
        return $this->connector
            ->send(new CreateCustomer($email, $name))
            ->dto();
    }
}

// Requests (Stripe uses form encoding)
#[Post, Form, Dto(CustomerDto::class)]
class CreateCustomer extends Request
{
    public function __construct(
        private readonly string $email,
        private readonly ?string $name = null,
    ) {}

    public function endpoint(): string
    {
        return '/customers';
    }

    public function body(): ?array
    {
        return array_filter([
            'email' => $this->email,
            'name' => $this->name,
        ]);
    }
}

#[Get, Form, Dto(CustomerDto::class)]
class GetCustomer extends Request
{
    public function __construct(
        private readonly string $id,
    ) {}

    public function endpoint(): string
    {
        return "/customers/{$this->id}";
    }
}

#[Get, Form, Dto(CustomerDto::class, dataKey: 'data')]
class ListCustomers extends Request
{
    public function __construct(
        private readonly int $limit = 10,
    ) {}

    public function endpoint(): string
    {
        return '/customers';
    }

    public function query(): ?array
    {
        return [
            'limit' => $this->limit,
        ];
    }
}

// Usage
$stripe = new StripeConnector($secretKey);
$customer = $stripe->customers()->create('john@example.com', 'John');
```

## Payment with Idempotency

```php
use Saloon\Attributes\{Post, Form, Dto, Idempotent};

#[Post, Form, Dto(ChargeDto::class), Idempotent(header: 'Idempotency-Key')]
class CreateCharge extends Request
{
    public function __construct(
        private readonly int $amount,
        private readonly string $currency,
        private readonly string $customerId,
    ) {}

    public function endpoint(): string
    {
        return '/charges';
    }

    public function body(): ?array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'customer' => $this->customerId,
        ];
    }
}

// Usage - safe to retry
$charge = $stripe->send(new CreateCharge(
    amount: 2000,
    currency: 'usd',
    customerId: 'cus_123',
));
```

## Testing the SDK

```php
use function Pest\expect;

it('creates a customer', function () {
    $connector = StripeConnector::fake([
        CreateCustomer::class => Response::make([
            'id' => 'cus_123',
            'email' => 'john@example.com',
            'name' => 'John',
        ]),
    ]);

    $customer = $connector->customers()->create('john@example.com', 'John');

    expect($customer)->toBeInstanceOf(CustomerDto::class);
    expect($customer->id)->toBe('cus_123');
    expect($customer->email)->toBe('john@example.com');

    $connector->assertSent(CreateCustomer::class);
});

it('handles stripe errors', function () {
    $connector = StripeConnector::fake([
        CreateCustomer::class => Response::make([
            'error' => [
                'type' => 'card_error',
                'message' => 'Card declined',
            ],
        ], 402),
    ]);

    $connector->customers()->create('john@example.com');
})->throws(ClientException::class);
```

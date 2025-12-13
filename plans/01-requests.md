# Requests

## Attributes

All request configuration via attributes — no inheritance hierarchy:

```php
// HTTP Method
#[Get], #[Post], #[Put], #[Patch], #[Delete], #[Head], #[Options]

// Content Type
#[Json]           // application/json
#[Form]           // application/x-www-form-urlencoded
#[Multipart]      // multipart/form-data
#[Xml]            // application/xml

// Protocol
#[JsonRpc]        // JSON-RPC 2.0
#[XmlRpc]         // XML-RPC
#[Soap]           // SOAP envelope
#[GraphQL]        // GraphQL query/mutation

// Behavior
#[ThrowOnError]   // Enable throwing on 4xx/5xx for this request
```

## Attribute Validation

Content type attributes are **mutually exclusive**. Using multiple throws at boot:

```php
// INVALID - throws AttributeConflictException
#[Post, Json, Xml]
class BadRequest extends Request { ... }

// INVALID - throws AttributeConflictException
#[Post, Form, Multipart]
class AlsoBadRequest extends Request { ... }

// VALID - only one content type
#[Post, Json]
class GoodRequest extends Request { ... }
```

Mutually exclusive groups:
- Content types: `#[Json]`, `#[Form]`, `#[Multipart]`, `#[Xml]`
- Protocols: `#[JsonRpc]`, `#[XmlRpc]`, `#[Soap]`, `#[GraphQL]`

## Usage Comparison

**Before (Saloon):**
```php
class CreateUser extends Request
{
    use HasJsonBody;
    use ThrowsOnError;

    protected Method $method = Method::POST;

    public function __construct(
        protected string $name,
        protected string $email,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/users';
    }

    public function defaultBody(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
        ];
    }
}
```

**After:**
```php
#[Post, Json]
class CreateUser extends Request
{
    public function __construct(
        private readonly string $name,
        private readonly string $email,
    ) {}

    public function endpoint(): string
    {
        return '/users';
    }

    public function body(): ?array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
        ];
    }
}
```

## Base Request Class

Single base class — attributes handle the rest:

```php
abstract class Request
{
    // Required
    abstract public function endpoint(): string;

    // Optional - defaults to null (no body/query/headers)
    public function body(): ?array { return null; }
    public function query(): ?array { return null; }
    public function headers(): ?array { return null; }

    // Lifecycle hooks
    protected function boot(): void {}
    protected function transformResponse(Response $response): Response
    {
        return $response;
    }

    // Clone request for modification/retry
    public function clone(): static
    {
        return clone $this;
    }
}
```

## Request Cloning

Clone requests to modify them without affecting the original:

```php
$request = new GetUser(1);

// Clone with modifications
$modifiedRequest = $request->clone()
    ->withHeader('X-Custom', 'value')
    ->withQuery(['include' => 'posts']);

// Original unchanged
$connector->send($request);

// Modified version
$connector->send($modifiedRequest);
```

### Clone for Retry with Different Parameters

```php
$request = new CreatePayment(amount: 1000, currency: 'usd');

try {
    $connector->send($request);
} catch (RateLimitException $e) {
    // Clone and retry with backoff
    sleep($e->retryAfter());
    $connector->send($request->clone());
}
```

### Clone with Overrides

```php
#[Post, Json]
class CreateUser extends Request
{
    public function __construct(
        private readonly string $name,
        private readonly string $email,
        private readonly ?string $role = null,
    ) {}

    public function withRole(string $role): static
    {
        $clone = $this->clone();
        // Use reflection or rebuild
        return new static($this->name, $this->email, $role);
    }
}

$adminRequest = $request->withRole('admin');
```

## GET Request Example

```php
#[Get, Json]
class GetUsers extends Request
{
    public function __construct(
        private readonly int $page = 1,
        private readonly int $perPage = 25,
        private readonly ?string $search = null,
    ) {}

    public function endpoint(): string
    {
        return '/users';
    }

    public function query(): ?array
    {
        return array_filter([
            'page' => $this->page,
            'per_page' => $this->perPage,
            'q' => $this->search,
        ]);
    }
}
```

## POST with Dynamic Body

```php
#[Post, Json]
class CreateOrder extends Request
{
    public function __construct(
        private readonly array $items,
        private readonly bool $sandbox = false,
    ) {}

    public function endpoint(): string
    {
        return $this->sandbox ? '/sandbox/orders' : '/orders';
    }

    public function body(): ?array
    {
        return [
            'items' => array_map(fn($item) => [
                'sku' => $item['sku'],
                'quantity' => $item['quantity'],
                'price' => $this->sandbox ? 0 : $item['price'],
            ], $this->items),
            'test_mode' => $this->sandbox,
        ];
    }
}
```

## GraphQL Example

```php
#[Post, GraphQL]
class GetUserQuery extends Request
{
    public function __construct(
        private readonly int $id,
    ) {}

    public function endpoint(): string
    {
        return '/graphql';
    }

    public function body(): ?array
    {
        return [
            'query' => '
                query GetUser($id: ID!) {
                    user(id: $id) {
                        id
                        name
                        email
                    }
                }
            ',
            'variables' => [
                'id' => $this->id,
            ],
        ];
    }
}
```

## JSON-RPC Example

```php
#[Post, JsonRpc]
class GetBalance extends Request
{
    public function __construct(
        private readonly string $address,
    ) {}

    public function endpoint(): string
    {
        return '/rpc';
    }

    public function body(): ?array
    {
        return [
            'method' => 'eth_getBalance',
            'params' => [$this->address, 'latest'],
        ];
    }
}
```

## Request Headers

### Per-Request Headers

```php
#[Get, Json]
class GetUser extends Request
{
    public function __construct(
        private readonly int $id,
        private readonly string $apiVersion = '2024-01',
    ) {}

    public function endpoint(): string
    {
        return "/users/{$this->id}";
    }

    public function headers(): ?array
    {
        return [
            'X-API-Version' => $this->apiVersion,
            'X-Request-Id' => Str::uuid()->toString(),
        ];
    }
}
```

### Merging with Connector Headers

```php
class ApiConnector extends Connector
{
    public function defaultHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'User-Agent' => 'MyApp/1.0',
        ];
    }
}

#[Get, Json]
class GetUser extends Request
{
    public function headers(): ?array
    {
        return [
            'X-Custom' => 'value',
        ];
    }
}

// Final headers: Accept, User-Agent, X-Custom (merged)
// Request headers override connector headers if same key
```

### Conditional Headers

```php
#[Post, Json]
class CreateResource extends Request
{
    public function __construct(
        private readonly array $data,
        private readonly ?string $idempotencyKey = null,
    ) {}

    public function headers(): ?array
    {
        return array_filter([
            'Idempotency-Key' => $this->idempotencyKey,
        ]);
    }

    public function body(): ?array
    {
        return $this->data;
    }
}
```

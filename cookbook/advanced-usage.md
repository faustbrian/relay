# Advanced Usage

This guide covers advanced Relay features including DTO mapping, GraphQL support, streaming, idempotency, custom protocols, and more.

## DTO Mapping

### Creating DTOs

DTOs must implement the `DataTransferObject` interface:

```php
use Cline\Relay\Support\Contracts\DataTransferObject;

class User implements DataTransferObject
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $avatar = null,
    ) {}

    public static function fromArray(array $data): static
    {
        return new self(
            id: $data['id'],
            name: $data['name'],
            email: $data['email'],
            avatar: $data['avatar'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar' => $this->avatar,
        ];
    }
}
```

### Automatic DTO Mapping with Attribute

```php
use Cline\Relay\Support\Attributes\Dto;
use Cline\Relay\Support\Attributes\Methods\Get;

#[Get]
#[Dto(User::class)]
class GetUserRequest extends Request
{
    public function endpoint(): string
    {
        return "/users/{$this->userId}";
    }
}

// Usage
$user = $connector->send(new GetUserRequest(1))->dto();
// Returns User instance
```

### DTO with Nested Data Key

```php
// API returns: {"data": {"user": {...}}}

#[Dto(User::class, dataKey: 'data.user')]
class GetUserRequest extends Request {}

// Automatically extracts from data.user
$user = $connector->send(new GetUserRequest(1))->dto();
```

### Manual DTO Mapping

```php
$response = $connector->send(new GetUserRequest(1));

// Single DTO
$user = $response->dto(User::class);

// With custom key
$user = $response->dto(User::class, 'data.user');

// Collection of DTOs
$users = $response->dtoCollection(User::class, 'data');
```

### ResponseSerializer

For more control over serialization:

```php
use Cline\Relay\Support\Serialization\ResponseSerializer;

$serializer = new ResponseSerializer();

// Single DTO
$user = $serializer->toDto($response, User::class);

// From specific key
$user = $serializer->toDtoFrom($response, 'data.user', User::class);

// Collection as array
$users = $serializer->toDtoCollection($response, User::class, 'data');

// Collection as Laravel Collection
$users = $serializer->toCollection($response, User::class, 'data');
```

## GraphQL Support

### GraphQL Request Base Class

```php
use Cline\Relay\Protocols\GraphQL\GraphQLRequest;

class GetUserQuery extends GraphQLRequest
{
    public function __construct(
        private readonly int $userId,
    ) {}

    public function graphqlQuery(): string
    {
        return <<<'GRAPHQL'
            query GetUser($id: ID!) {
                user(id: $id) {
                    id
                    name
                    email
                    avatar
                }
            }
        GRAPHQL;
    }

    public function variables(): array
    {
        return ['id' => $this->userId];
    }
}
```

### GraphQL Response

```php
use Cline\Relay\Protocols\GraphQL\GraphQLResponse;

$response = $connector->send(new GetUserQuery(1));
$graphql = new GraphQLResponse($response);

// Check for errors
if ($graphql->hasErrors()) {
    $errors = $graphql->errorMessages();
    $firstError = $graphql->firstError();
}

// Access data
$userData = $graphql->data('user');

// Check success
if ($graphql->successful()) {
    $user = $graphql->data('user');
}

// Get extensions (timing, tracing)
$extensions = $graphql->extensions();
```

### GraphQL Mutations

```php
class CreateUserMutation extends GraphQLRequest
{
    public function __construct(
        private readonly string $name,
        private readonly string $email,
    ) {}

    public function graphqlQuery(): string
    {
        return <<<'GRAPHQL'
            mutation CreateUser($input: CreateUserInput!) {
                createUser(input: $input) {
                    id
                    name
                    email
                }
            }
        GRAPHQL;
    }

    public function variables(): array
    {
        return [
            'input' => [
                'name' => $this->name,
                'email' => $this->email,
            ],
        ];
    }
}
```

### Custom GraphQL Endpoint

```php
class CustomGraphQLQuery extends GraphQLRequest
{
    protected string $graphqlEndpoint = '/api/v2/graphql';

    // Or dynamically
    public function endpoint(): string
    {
        return '/custom/graphql/endpoint';
    }
}

// Or fluently
$request = (new GetUserQuery(1))->withEndpoint('/v2/graphql');
```

### Multiple Operations

```php
class BatchOperations extends GraphQLRequest
{
    public function graphqlQuery(): string
    {
        return <<<'GRAPHQL'
            query GetUserAndOrders($userId: ID!) {
                user(id: $userId) {
                    id
                    name
                }
                orders(userId: $userId) {
                    id
                    total
                }
            }
        GRAPHQL;
    }

    public function operationName(): string
    {
        return 'GetUserAndOrders';
    }
}
```

## Response Streaming

### Enabling Streaming

```php
use Cline\Relay\Support\Attributes\Stream;

#[Get]
#[Stream]
class DownloadFileRequest extends Request
{
    public function endpoint(): string
    {
        return '/files/large-export.csv';
    }
}
```

### Saving Streamed Response

```php
$response = $connector->send(new DownloadFileRequest());

// Save to file
$response->saveTo('/path/to/file.csv');

// Stream to output
$response->streamTo($output);
```

### Chunked Processing

```php
$response = $connector->send(new DownloadFileRequest());

$response->chunks(1024, function (string $chunk) {
    // Process each 1KB chunk
    echo $chunk;
});
```

### Server-Sent Events (SSE)

```php
#[Get]
#[Stream]
class EventStreamRequest extends Request
{
    public function endpoint(): string
    {
        return '/events/stream';
    }
}

$response = $connector->send(new EventStreamRequest());

foreach ($response->lines() as $line) {
    if (str_starts_with($line, 'data:')) {
        $data = json_decode(substr($line, 5), true);
        handleEvent($data);
    }
}
```

## Idempotency

### Marking Requests as Idempotent

```php
use Cline\Relay\Support\Attributes\Idempotent;

#[Post]
#[Idempotent]
class CreatePaymentRequest extends Request
{
    public function endpoint(): string
    {
        return '/payments';
    }
}

// Automatically adds: Idempotency-Key: <generated-uuid>
```

### Custom Header Name

```php
#[Idempotent(header: 'X-Request-ID')]
class CreateOrderRequest extends Request {}
```

### Custom Key Generation

```php
#[Idempotent(keyMethod: 'generateKey')]
class CreateOrderRequest extends Request
{
    public function __construct(
        private readonly string $orderId,
    ) {}

    public function generateKey(): string
    {
        return hash('sha256', $this->orderId);
    }
}
```

### Manual Idempotency Key

```php
$request = new CreatePaymentRequest($data);
$request = $request->withIdempotencyKey('custom-key-123');

$response = $connector->send($request);
```

### IdempotencyManager

For full control over idempotency:

```php
use Cline\Relay\Support\Security\IdempotencyManager;

$manager = new IdempotencyManager(
    cache: $cache,
    headerName: 'Idempotency-Key',
    ttl: 86400, // 24 hours
);

// Generate key
$key = $manager->generateKey();

// Add to request
$request = $manager->addToRequest($request, $key);

// Check for cached response
if ($cached = $manager->getCachedResponse($key)) {
    return $cached;
}

// Cache response
$manager->cacheResponse($key, $response);

// Check if replay
if ($manager->isReplay($response)) {
    // This was a cached replay
}
```

## Immutable Request Building

### Request Cloning

```php
$baseRequest = new SearchRequest('products');

// Clone and modify
$page1 = $baseRequest->withQuery('page', 1);
$page2 = $baseRequest->withQuery('page', 2);

// Original is unchanged
```

### Fluent Request Building

```php
$request = (new CreateOrderRequest())
    ->withHeader('X-Custom-Header', 'value')
    ->withHeaders(['X-Another' => 'value'])
    ->withQuery('expand', 'customer')
    ->withBearerToken($token)
    ->withBasicAuth($username, $password)
    ->withIdempotencyKey($key);
```

### Cloning for Modification

```php
class GetProductsRequest extends Request
{
    public function paginated(int $page, int $perPage = 20): static
    {
        return $this->clone()
            ->withQuery('page', $page)
            ->withQuery('per_page', $perPage);
    }

    public function filtered(array $filters): static
    {
        $clone = $this->clone();

        foreach ($filters as $key => $value) {
            $clone = $clone->withQuery($key, $value);
        }

        return $clone;
    }
}

// Usage
$request = (new GetProductsRequest())
    ->paginated(2, 50)
    ->filtered(['category' => 'electronics']);
```

## Custom Protocols

### JSON-RPC

```php
use Cline\Relay\Support\Attributes\Protocols\JsonRpc;

#[JsonRpc]
class CalculateRequest extends Request
{
    public function endpoint(): string
    {
        return '/rpc';
    }

    public function body(): array
    {
        return [
            'method' => 'calculate',
            'params' => ['a' => 10, 'b' => 20],
        ];
    }
}
```

### SOAP

```php
use Cline\Relay\Support\Attributes\Protocols\Soap;

#[Soap(version: '1.2')]
class GetStockQuoteRequest extends Request
{
    public function endpoint(): string
    {
        return '/soap/StockQuote';
    }
}
```

### XML-RPC

```php
use Cline\Relay\Support\Attributes\Protocols\XmlRpc;

#[XmlRpc]
class RpcMethodRequest extends Request
{
    public function endpoint(): string
    {
        return '/xmlrpc';
    }
}
```

## Request Lifecycle Hooks

### Before Send

```php
class AuditedRequest extends Request
{
    public function beforeSend(): void
    {
        logger()->info('Sending request', [
            'endpoint' => $this->endpoint(),
            'method' => $this->method(),
        ]);
    }
}
```

### After Response

```php
class MetricsRequest extends Request
{
    public function afterResponse(Response $response): void
    {
        metrics()->timing('api.request', $response->duration());
        metrics()->increment("api.status.{$response->status()}");
    }
}
```

### On Error

```php
class AlertingRequest extends Request
{
    public function onError(RequestException $exception): void
    {
        if ($exception->status() >= 500) {
            alert()->critical('API Error', [
                'status' => $exception->status(),
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
```

## Response Transformation

### Custom Response Methods

```php
class OrderResponse
{
    public static function fromResponse(Response $response): self
    {
        return new self(
            order: Order::fromArray($response->json('data')),
            meta: $response->json('meta'),
        );
    }
}

// Transform in request
class GetOrderRequest extends Request
{
    public function transformResponse(Response $response): OrderResponse
    {
        return OrderResponse::fromResponse($response);
    }
}
```

### Response Wrapping

```php
$response = $connector->send(new GetUserRequest(1));

// Create modified response
$enhanced = $response
    ->withJson(['enhanced' => true, ...$response->json()])
    ->withHeader('X-Enhanced', 'true');
```

## Extending Connectors

### Abstract Base Connector

```php
abstract class ApiConnector extends Connector
{
    abstract protected function apiKey(): string;

    public function defaultHeaders(): array
    {
        return [
            'X-API-Key' => $this->apiKey(),
            'Accept' => 'application/json',
        ];
    }

    public function rateLimit(): ?RateLimitConfig
    {
        return new RateLimitConfig(
            maxAttempts: 100,
            decaySeconds: 60,
        );
    }
}

class ProductionApiConnector extends ApiConnector
{
    protected function apiKey(): string
    {
        return config('services.api.key');
    }

    public function baseUrl(): string
    {
        return 'https://api.production.example.com';
    }
}
```

### Connector Traits

```php
trait HasRetryPolicy
{
    public function defaultRetry(): Retry
    {
        return new Retry(
            times: 3,
            sleepMs: 500,
            multiplier: 2,
        );
    }
}

trait HasCircuitBreaker
{
    public function circuitBreakerConfig(): CircuitBreakerConfig
    {
        return new CircuitBreakerConfig(
            failureThreshold: 5,
            resetTimeout: 30,
        );
    }
}

class ResilientConnector extends Connector
{
    use HasRetryPolicy, HasCircuitBreaker;
}
```

## Full Example

Complete advanced connector:

```php
<?php

namespace App\Http\Connectors;

use Cline\Relay\Core\Connector;
use Cline\Relay\Protocols\GraphQL\GraphQLRequest;
use Cline\Relay\Protocols\GraphQL\GraphQLResponse;
use Cline\Relay\Features\Middleware\LoggingMiddleware;
use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;
use Cline\Relay\Support\Security\IdempotencyManager;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

class AdvancedConnector extends Connector
{
    private IdempotencyManager $idempotency;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly CacheInterface $cache,
    ) {
        $this->idempotency = new IdempotencyManager(
            cache: $cache,
            ttl: 86400,
        );
    }

    public function baseUrl(): string
    {
        return 'https://api.example.com/v1';
    }

    public function middleware(): array
    {
        return [
            new LoggingMiddleware($this->logger),
        ];
    }

    public function send(Request $request): Response
    {
        // Handle idempotent requests
        if ($request->isIdempotent()) {
            $key = $request->idempotencyKey() ?? $this->idempotency->generateKey();

            // Check for cached response
            if ($cached = $this->idempotency->getCachedResponse($key)) {
                return $this->idempotency->markAsReplay($cached);
            }

            $request = $this->idempotency->addToRequest($request, $key);
            $response = parent::send($request);

            // Cache successful responses
            if ($response->ok()) {
                $this->idempotency->cacheResponse($key, $response);
            }

            return $response;
        }

        return parent::send($request);
    }

    public function graphql(GraphQLRequest $request): GraphQLResponse
    {
        $response = $this->send($request);

        return new GraphQLResponse($response);
    }

    public function getUser(int $id): User
    {
        return $this->send(new GetUserRequest($id))->dto(User::class);
    }

    public function createPayment(array $data): Payment
    {
        $request = (new CreatePaymentRequest($data))
            ->withIdempotencyKey(hash('sha256', json_encode($data)));

        return $this->send($request)->dto(Payment::class);
    }

    public function searchProducts(string $query): ProductCollection
    {
        return $this->paginate(new SearchProductsRequest($query))
            ->collect()
            ->map(fn ($data) => Product::fromArray($data));
    }
}
```

Usage:

```php
$connector = new AdvancedConnector($logger, $cache);

// DTO mapping
$user = $connector->getUser(1);

// Idempotent request
$payment = $connector->createPayment([
    'amount' => 1000,
    'currency' => 'USD',
]);

// GraphQL
$graphql = $connector->graphql(new GetUserQuery(1));

if ($graphql->successful()) {
    $userData = $graphql->data('user');
}

// Pagination with DTOs
$products = $connector->searchProducts('laptop');
```

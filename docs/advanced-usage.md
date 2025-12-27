---
title: Advanced Usage
description: DTOs, GraphQL, streaming, idempotency, and custom protocols in Relay
---

This guide covers advanced Relay features including DTO mapping, GraphQL support, streaming, idempotency, and custom protocols.

## DTO Mapping

### Creating DTOs

```php
use Cline\Relay\Support\Contracts\DataTransferObject;

class User implements DataTransferObject
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
    ) {}

    public static function fromArray(array $data): static
    {
        return new self(
            id: $data['id'],
            name: $data['name'],
            email: $data['email'],
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
        ];
    }
}
```

### Automatic DTO Mapping

```php
use Cline\Relay\Support\Attributes\Dto;

#[Get]
#[Dto(User::class)]
class GetUserRequest extends Request
{
    public function endpoint(): string
    {
        return "/users/{$this->userId}";
    }
}

$user = $connector->send(new GetUserRequest(1))->dto();
```

### DTO with Nested Data Key

```php
#[Dto(User::class, dataKey: 'data.user')]
class GetUserRequest extends Request {}
```

### Manual DTO Mapping

```php
$response = $connector->send(new GetUserRequest(1));

$user = $response->dto(User::class);
$user = $response->dto(User::class, 'data.user');
$users = $response->dtoCollection(User::class, 'data');
```

## GraphQL Support

### GraphQL Request

```php
use Cline\Relay\Protocols\GraphQL\GraphQLRequest;

class GetUserQuery extends GraphQLRequest
{
    public function __construct(private readonly int $userId) {}

    public function graphqlQuery(): string
    {
        return <<<'GRAPHQL'
            query GetUser($id: ID!) {
                user(id: $id) {
                    id
                    name
                    email
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

if ($graphql->hasErrors()) {
    $errors = $graphql->errorMessages();
}

$userData = $graphql->data('user');
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
$response->saveTo('/path/to/file.csv');
```

### Chunked Processing

```php
$response->chunks(1024, function (string $chunk) {
    echo $chunk;
});
```

### Server-Sent Events (SSE)

```php
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
class CreatePaymentRequest extends Request {}
// Automatically adds: Idempotency-Key: <generated-uuid>
```

### Custom Key Generation

```php
#[Idempotent(keyMethod: 'generateKey')]
class CreateOrderRequest extends Request
{
    public function generateKey(): string
    {
        return hash('sha256', $this->orderId);
    }
}
```

### Manual Idempotency Key

```php
$request = (new CreatePaymentRequest($data))
    ->withIdempotencyKey('custom-key-123');
```

## Immutable Request Building

```php
$request = (new CreateOrderRequest())
    ->withHeader('X-Custom-Header', 'value')
    ->withQuery('expand', 'customer')
    ->withBearerToken($token)
    ->withIdempotencyKey($key);
```

## Custom Protocols

### JSON-RPC

```php
use Cline\Relay\Support\Attributes\Protocols\JsonRpc;

#[JsonRpc]
class CalculateRequest extends Request
{
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
class GetStockQuoteRequest extends Request {}
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
            ]);
        }
    }
}
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
        return new RateLimitConfig(maxAttempts: 100, decaySeconds: 60);
    }
}
```

### Connector Traits

```php
trait HasRetryPolicy
{
    public function defaultRetry(): Retry
    {
        return new Retry(times: 3, sleepMs: 500, multiplier: 2);
    }
}

class ResilientConnector extends Connector
{
    use HasRetryPolicy;
}
```

## Full Example

```php
class AdvancedConnector extends Connector
{
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

    public function graphql(GraphQLRequest $request): GraphQLResponse
    {
        return new GraphQLResponse($this->send($request));
    }
}
```

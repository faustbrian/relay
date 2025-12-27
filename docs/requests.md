---
title: Requests
description: Define API endpoints with HTTP methods, headers, query parameters, and request bodies
---

Requests represent individual API endpoint calls. They define the HTTP method, endpoint, headers, query parameters, and body.

## Creating a Request

Extend the `Request` class and add an HTTP method attribute:

```php
<?php

namespace App\Http\Requests;

use Cline\Relay\Support\Attributes\Methods\Get;
use Cline\Relay\Core\Request;

#[Get]
class GetUsersRequest extends Request
{
    public function endpoint(): string
    {
        return '/users';
    }
}
```

## HTTP Method Attributes

```php
use Cline\Relay\Support\Attributes\Methods\Get;
use Cline\Relay\Support\Attributes\Methods\Post;
use Cline\Relay\Support\Attributes\Methods\Put;
use Cline\Relay\Support\Attributes\Methods\Patch;
use Cline\Relay\Support\Attributes\Methods\Delete;
use Cline\Relay\Support\Attributes\Methods\Head;
use Cline\Relay\Support\Attributes\Methods\Options;

#[Get]
class GetUserRequest extends Request {}

#[Post]
class CreateUserRequest extends Request {}

#[Put]
class ReplaceUserRequest extends Request {}

#[Patch]
class UpdateUserRequest extends Request {}

#[Delete]
class DeleteUserRequest extends Request {}
```

## Dynamic Endpoints

```php
#[Get]
class GetUserRequest extends Request
{
    public function __construct(
        private readonly int $userId,
    ) {}

    public function endpoint(): string
    {
        return "/users/{$this->userId}";
    }
}

// Usage
$connector->send(new GetUserRequest(123));
```

### Multiple Parameters

```php
#[Get]
class GetRepositoryRequest extends Request
{
    public function __construct(
        private readonly string $owner,
        private readonly string $repo,
    ) {}

    public function endpoint(): string
    {
        return "/repos/{$this->owner}/{$this->repo}";
    }
}

$connector->send(new GetRepositoryRequest('laravel', 'laravel'));
```

## Query Parameters

```php
#[Get]
class ListUsersRequest extends Request
{
    public function __construct(
        private readonly int $page = 1,
        private readonly int $perPage = 20,
        private readonly ?string $sort = null,
    ) {}

    public function endpoint(): string
    {
        return '/users';
    }

    public function query(): array
    {
        return array_filter([
            'page' => $this->page,
            'per_page' => $this->perPage,
            'sort' => $this->sort,
        ]);
    }
}

// Sends GET /users?page=2&per_page=50
$connector->send(new ListUsersRequest(page: 2, perPage: 50));
```

### Adding Query Parameters Dynamically

```php
$request = new ListUsersRequest();
$request = $request->withQuery('filter', 'active');

$connector->send($request);
```

## Request Body

```php
use Cline\Relay\Support\Attributes\ContentTypes\Json;

#[Post]
#[Json]
class CreateUserRequest extends Request
{
    public function __construct(
        private readonly string $name,
        private readonly string $email,
        private readonly ?string $role = 'user',
    ) {}

    public function endpoint(): string
    {
        return '/users';
    }

    public function body(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
        ];
    }
}
```

## Content Type Attributes

### JSON

```php
use Cline\Relay\Support\Attributes\ContentTypes\Json;

#[Post]
#[Json]
class CreateUserRequest extends Request
{
    public function body(): array
    {
        return ['name' => 'John'];
    }
}
```

### Form Data

```php
use Cline\Relay\Support\Attributes\ContentTypes\Form;

#[Post]
#[Form]
class LoginRequest extends Request
{
    public function body(): array
    {
        return ['username' => $this->username, 'password' => $this->password];
    }
}
```

### Multipart Form Data

```php
use Cline\Relay\Support\Attributes\ContentTypes\Multipart;

#[Post]
#[Multipart]
class UploadFileRequest extends Request
{
    public function body(): array
    {
        return [
            'file' => fopen('/path/to/file.pdf', 'r'),
            'name' => 'document.pdf',
        ];
    }
}
```

### XML

```php
use Cline\Relay\Support\Attributes\ContentTypes\Xml;

#[Post]
#[Xml]
class CreateOrderRequest extends Request {}
```

## Headers

```php
#[Get]
class GetUserRequest extends Request
{
    public function headers(): array
    {
        return [
            'X-Custom-Header' => 'custom-value',
            'Accept-Language' => 'en-US',
        ];
    }
}
```

### Adding Headers Dynamically

```php
$request = new GetUserRequest(1);
$request = $request->withHeader('X-Request-ID', 'abc123');
$request = $request->withHeaders([
    'X-Trace-ID' => 'trace-123',
    'X-Span-ID' => 'span-456',
]);

$connector->send($request);
```

## Authentication Helpers

```php
// Bearer Token
$request = (new GetUserRequest(1))->withBearerToken('your-token');

// Basic Auth
$request = (new GetUserRequest(1))->withBasicAuth('username', 'password');
```

## Idempotency

```php
use Cline\Relay\Support\Attributes\Idempotent;

#[Post]
#[Json]
#[Idempotent]
class CreatePaymentRequest extends Request {}

// Custom header name
#[Idempotent(header: 'X-Request-ID')]
class CreatePaymentRequest extends Request {}

// Custom key generation
#[Idempotent(keyMethod: 'generateKey')]
class CreatePaymentRequest extends Request
{
    public function generateKey(): string
    {
        return hash('sha256', $this->orderId . $this->amount);
    }
}
```

### Manual Idempotency Keys

```php
$request = (new CreatePaymentRequest())
    ->withIdempotencyKey('unique-key-123');
```

## Lifecycle Hooks

```php
#[Get]
class GetUserRequest extends Request
{
    protected function boot(): void
    {
        // Called before the request is sent
    }
}
```

## Response Transformation

```php
use Cline\Relay\Core\Response;

#[Get]
class GetUserRequest extends Request
{
    public function transformResponse(Response $response): Response
    {
        return $response->withJsonKey('processed_at', now()->toIso8601String());
    }
}
```

## Accessing Resource and Connector

```php
#[Get]
class GetUserRequest extends Request
{
    protected function boot(): void
    {
        $resource = $this->resource();
        $connector = $this->connector();

        if ($connector) {
            $baseUrl = $connector->baseUrl();
        }
    }
}
```

## Cloning Requests

Requests are immutable:

```php
$request = new GetUserRequest(1);
$requestWithHeader = $request->withHeader('X-Custom', 'value');

// $request is unchanged
// $requestWithHeader has the new header
```

## Throw on Error

```php
use Cline\Relay\Support\Attributes\ThrowOnError;

#[Get]
#[ThrowOnError]
class GetUserRequest extends Request {}

// Or be specific
#[ThrowOnError(clientErrors: true, serverErrors: false)]
class GetUserRequest extends Request {}
```

## Accessing Attributes

```php
$request = new CreatePaymentRequest();

if ($request->hasAttribute(Idempotent::class)) {
    // ...
}

$idempotent = $request->getAttribute(Idempotent::class);
$method = $request->method();
$contentType = $request->contentType();
$isIdempotent = $request->isIdempotent();
```

## Debugging

```php
$request = new CreateUserRequest('John', 'john@example.com');

$request->dump(); // Dump and continue
$request->dd();   // Dump and die
```

## Macros

```php
use Cline\Relay\Core\Request;

Request::macro('withCorrelationId', function (string $id) {
    return $this->withHeader('X-Correlation-ID', $id);
});

$request = (new GetUserRequest(1))->withCorrelationId('abc-123');
```

## Full Example

```php
<?php

namespace App\Http\Requests;

use Cline\Relay\Support\Attributes\ContentTypes\Json;
use Cline\Relay\Support\Attributes\Idempotent;
use Cline\Relay\Support\Attributes\Methods\Post;
use Cline\Relay\Support\Attributes\ThrowOnError;
use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;

#[Post]
#[Json]
#[Idempotent(keyMethod: 'generateIdempotencyKey')]
#[ThrowOnError]
class CreateOrderRequest extends Request
{
    public function __construct(
        private readonly string $customerId,
        private readonly array $items,
        private readonly string $currency = 'USD',
    ) {}

    public function endpoint(): string
    {
        return "/customers/{$this->customerId}/orders";
    }

    public function headers(): array
    {
        return ['X-Api-Version' => '2024-01'];
    }

    public function body(): array
    {
        return [
            'items' => $this->items,
            'currency' => $this->currency,
            'created_at' => now()->toIso8601String(),
        ];
    }

    public function generateIdempotencyKey(): string
    {
        return hash('sha256', json_encode([
            $this->customerId,
            $this->items,
            date('Y-m-d'),
        ]));
    }

    protected function boot(): void
    {
        if (empty($this->items)) {
            throw new InvalidArgumentException('Order must have at least one item');
        }
    }

    public function transformResponse(Response $response): Response
    {
        return $response->withJsonKey('processed', true);
    }
}
```

# Requests

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

Use attributes to declare the HTTP method:

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

#[Head]
class HeadUserRequest extends Request {}

#[Options]
class OptionsUserRequest extends Request {}
```

## Dynamic Endpoints

Pass parameters to construct dynamic endpoints:

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

// Usage
$connector->send(new GetRepositoryRequest('laravel', 'laravel'));
```

## Query Parameters

Override the `query()` method to add query parameters:

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

// Usage - sends GET /users?page=2&per_page=50
$connector->send(new ListUsersRequest(page: 2, perPage: 50));
```

### Adding Query Parameters Dynamically

Use `withQuery()` to add parameters at runtime:

```php
$request = new ListUsersRequest();
$request = $request->withQuery('filter', 'active');

$connector->send($request);
```

## Request Body

Override the `body()` method for POST, PUT, PATCH requests:

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

Declare the content type with attributes:

### JSON (application/json)

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

### Form Data (application/x-www-form-urlencoded)

```php
use Cline\Relay\Support\Attributes\ContentTypes\Form;

#[Post]
#[Form]
class LoginRequest extends Request
{
    public function body(): array
    {
        return [
            'username' => $this->username,
            'password' => $this->password,
        ];
    }
}
```

### Multipart Form Data (multipart/form-data)

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

### XML (application/xml)

```php
use Cline\Relay\Support\Attributes\ContentTypes\Xml;

#[Post]
#[Xml]
class CreateOrderRequest extends Request
{
    // Body should return array that gets converted to XML
}
```

### YAML (application/x-yaml)

```php
use Cline\Relay\Support\Attributes\ContentTypes\Yaml;

#[Post]
#[Yaml]
class CreateConfigRequest extends Request {}

// Custom MIME type
#[Yaml('text/yaml')]
class CreateConfigRequest extends Request {}
```

## Headers

Override the `headers()` method to add request-specific headers:

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

Use `withHeader()` or `withHeaders()` at runtime:

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

Requests have built-in authentication helpers:

### Bearer Token

```php
$request = (new GetUserRequest(1))->withBearerToken('your-token');
// Adds: Authorization: Bearer your-token
```

### Basic Auth

```php
$request = (new GetUserRequest(1))->withBasicAuth('username', 'password');
// Adds: Authorization: Basic base64(username:password)
```

## Idempotency

Mark requests as idempotent to prevent duplicate processing:

```php
use Cline\Relay\Support\Attributes\Idempotent;

#[Post]
#[Json]
#[Idempotent]
class CreatePaymentRequest extends Request
{
    // A random idempotency key will be generated automatically
}

// Or specify a custom header name
#[Idempotent(header: 'X-Request-ID')]
class CreatePaymentRequest extends Request {}

// Or use a custom key method
#[Idempotent(keyMethod: 'generateKey')]
class CreatePaymentRequest extends Request
{
    public function generateKey(): string
    {
        return hash('sha256', $this->orderId . $this->amount);
    }
}
```

### Idempotency Key Generator Class

For reusable key generation logic, implement the `IdempotencyKeyGenerator` interface:

```php
use Cline\Relay\Support\Contracts\IdempotencyKeyGenerator;
use Cline\Relay\Core\Request;

class ContentBasedKeyGenerator implements IdempotencyKeyGenerator
{
    public function generate(Request $request): string
    {
        // Generate key based on request content
        return hash('sha256',
            $request->endpoint() .
            serialize($request->body() ?? [])
        );
    }
}

// Use the generator class
#[Idempotent(keyMethod: ContentBasedKeyGenerator::class)]
class CreateOrderRequest extends Request {}
```

### Manual Idempotency Keys

Set the key at runtime:

```php
$request = (new CreatePaymentRequest())
    ->withIdempotencyKey('unique-key-123');

$connector->send($request);
```

## Lifecycle Hook

Override the `boot()` method for initialization logic:

```php
#[Get]
class GetUserRequest extends Request
{
    protected function boot(): void
    {
        // Called before the request is sent
        // Use for validation, logging, etc.
    }
}
```

## Response Transformation

Transform the response after receiving:

```php
use Cline\Relay\Core\Response;

#[Get]
class GetUserRequest extends Request
{
    public function transformResponse(Response $response): Response
    {
        // Modify the response before it's returned
        return $response->withJsonKey('processed_at', now()->toIso8601String());
    }
}
```

## Accessing Resource and Connector

When a request is sent through a resource or connector, you can access them:

```php
#[Get]
class GetUserRequest extends Request
{
    protected function boot(): void
    {
        // Access the resource (if sent through one)
        $resource = $this->resource();

        // Access the connector
        $connector = $this->connector();

        // Use connector configuration
        if ($connector) {
            $baseUrl = $connector->baseUrl();
        }
    }
}
```

This is useful for:
- Accessing connector configuration within request lifecycle hooks
- Building dynamic behavior based on which connector is being used
- Accessing resource-specific methods or properties

**Note:** These methods return `null` if the request hasn't been sent yet.

## Cloning Requests

Requests are immutable. Modification methods return clones:

```php
$request = new GetUserRequest(1);
$requestWithHeader = $request->withHeader('X-Custom', 'value');

// $request is unchanged
// $requestWithHeader has the new header
```

Explicitly clone a request:

```php
$clone = $request->clone();
```

## Throw on Error

Mark a request to throw exceptions on error responses:

```php
use Cline\Relay\Support\Attributes\ThrowOnError;

#[Get]
#[ThrowOnError]
class GetUserRequest extends Request
{
    public function endpoint(): string
    {
        return '/users';
    }
}

// Or be specific
#[ThrowOnError(clientErrors: true, serverErrors: false)]
class GetUserRequest extends Request {}
```

## Accessing Attributes

Check and retrieve attributes programmatically:

```php
$request = new CreatePaymentRequest();

// Check if attribute exists
if ($request->hasAttribute(Idempotent::class)) {
    // ...
}

// Get attribute instance
$idempotent = $request->getAttribute(Idempotent::class);
if ($idempotent) {
    echo $idempotent->header; // 'Idempotency-Key'
}

// Built-in convenience methods
$method = $request->method();       // 'POST'
$contentType = $request->contentType(); // 'application/json'
$isIdempotent = $request->isIdempotent(); // true
```

## Debugging

Dump request details for debugging:

```php
$request = new CreateUserRequest('John', 'john@example.com');

// Dump and continue
$request->dump();

// Dump and die
$request->dd();
```

Output includes:
- Class name
- HTTP method
- Endpoint
- Content type
- Headers
- Query parameters
- Body

## Macros

Extend requests with macros:

```php
use Cline\Relay\Core\Request;

Request::macro('withCorrelationId', function (string $id) {
    return $this->withHeader('X-Correlation-ID', $id);
});

// Usage
$request = (new GetUserRequest(1))->withCorrelationId('abc-123');
```

## Full Example

Complete request with all features:

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
        return [
            'X-Api-Version' => '2024-01',
        ];
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

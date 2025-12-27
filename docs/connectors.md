---
title: Connectors
description: Configure API connections with base URLs, authentication, headers, timeouts, and more
---

Connectors represent an API service and define how requests are sent. They configure base URLs, authentication, headers, timeouts, and other connection settings.

## Creating a Connector

Extend the `Connector` class and implement the `baseUrl()` method:

```php
<?php

namespace App\Http\Connectors;

use Cline\Relay\Core\Connector;

class StripeConnector extends Connector
{
    public function baseUrl(): string
    {
        return 'https://api.stripe.com/v1';
    }
}
```

## Configuration Methods

### Base URL

```php
public function baseUrl(): string
{
    return 'https://api.example.com/v1';
}
```

For dynamic base URLs (e.g., multi-tenant), override `resolveBaseUrl()`:

```php
public function resolveBaseUrl(): string
{
    return match ($this->region) {
        'eu' => 'https://eu.api.example.com/v1',
        'us' => 'https://us.api.example.com/v1',
        default => 'https://api.example.com/v1',
    };
}
```

### Default Headers

```php
public function defaultHeaders(): array
{
    return [
        'Accept' => 'application/json',
        'X-Api-Version' => '2024-01',
        'User-Agent' => 'MyApp/1.0',
    ];
}
```

### Timeouts

```php
public function timeout(): int
{
    return 30; // Request timeout in seconds (default: 30)
}

public function connectTimeout(): int
{
    return 10; // Connection timeout in seconds (default: 10)
}
```

### Default Guzzle Configuration

```php
public function defaultConfig(): array
{
    return [
        'verify' => false, // Disable SSL verification
        'proxy' => 'http://proxy.example.com:8080',
        'debug' => true,
    ];
}
```

## Authentication

Implement the `authenticate()` method:

```php
use Cline\Relay\Features\Auth\BearerToken;
use Cline\Relay\Core\Request;

public function authenticate(Request $request): Request
{
    return (new BearerToken($this->token))->authenticate($request);
}
```

See **[Authentication](authentication)** for all available strategies.

## Sending Requests

### Using Request Objects

```php
$connector = new GitHubConnector($token);
$response = $connector->send(new GetRepositoryRequest('laravel', 'laravel'));
```

### Using Convenience Methods

```php
// GET with query parameters
$response = $connector->get('/users', ['per_page' => 10]);

// POST with body
$response = $connector->post('/users', [
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);

// PUT, PATCH, DELETE
$response = $connector->put('/users/1', ['name' => 'Jane Doe']);
$response = $connector->patch('/users/1', ['email' => 'jane@example.com']);
$response = $connector->delete('/users/1');
```

## Caching

```php
use Psr\SimpleCache\CacheInterface;
use Cline\Relay\Features\Caching\CacheConfig;

public function cache(): ?CacheInterface
{
    return app('cache')->store('redis');
}

public function cacheConfig(): ?CacheConfig
{
    return new CacheConfig(
        ttl: 3600,
        prefix: 'api_cache',
    );
}

public function cacheableMethods(): array
{
    return ['GET', 'HEAD'];
}
```

## Rate Limiting

```php
use Cline\Relay\Features\RateLimiting\RateLimitConfig;

public function rateLimit(): ?RateLimitConfig
{
    return new RateLimitConfig(
        maxAttempts: 100,
        decaySeconds: 60,
    );
}

public function concurrencyLimit(): ?int
{
    return 10;
}
```

## Middleware

```php
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;

public function middleware(): HandlerStack
{
    $stack = HandlerStack::create();

    $stack->push(Middleware::retry(
        decider: function ($retries, $request, $response, $exception) {
            return $retries < 3 && $response?->getStatusCode() >= 500;
        },
        delay: function ($retries) {
            return $retries * 1000;
        }
    ));

    return $stack;
}
```

## Request Pooling

```php
use Cline\Relay\Transport\Pool\Pool;

$responses = $connector->pool([
    'user1' => new GetUserRequest(1),
    'user2' => new GetUserRequest(2),
    'user3' => new GetUserRequest(3),
])
->concurrency(5)
->onResponse(fn ($response, $key) => logger()->info("Got response for {$key}"))
->send();

$user1 = $responses['user1']->json();
```

## Pagination

```php
$paginator = $connector->paginate(new GetUsersRequest());

foreach ($paginator as $response) {
    foreach ($response->json('data') as $user) {
        // Process each user
    }
}

// Or get all items at once
$allUsers = $paginator->collect('data');
```

## Error Handling

### ThrowOnError Attribute

```php
use Cline\Relay\Support\Attributes\ThrowOnError;

#[ThrowOnError(clientErrors: true, serverErrors: true)]
class GitHubConnector extends Connector
{
    // All 4xx and 5xx responses will throw exceptions
}
```

### Custom Exception Handling

```php
use Cline\Relay\Support\Exceptions\ClientException;
use Cline\Relay\Support\Exceptions\ServerException;

protected function createClientException(Request $request, Response $response): ClientException
{
    return match ($response->status()) {
        401 => new UnauthorizedException($request, $response),
        403 => new ForbiddenException($request, $response),
        404 => new NotFoundException($request, $response),
        429 => new RateLimitException::fromResponse($request, $response),
        default => new GenericClientException::fromResponse($request, $response),
    };
}
```

## Debugging

```php
$connector = new GitHubConnector();
$connector->debug();

$response = $connector->send(new GetUserRequest());
```

## Testing

```php
use Cline\Relay\Core\Response;

$connector = GitHubConnector::fake([
    Response::make(['id' => 1, 'name' => 'John']),
    Response::make(['id' => 2, 'name' => 'Jane']),
]);

$response1 = $connector->send(new GetUserRequest(1));
$response2 = $connector->send(new GetUserRequest(2));

$connector->assertSent(GetUserRequest::class);
$connector->assertSentCount(2);
```

## Macros

```php
use Cline\Relay\Core\Connector;

Connector::macro('withDebugHeaders', function () {
    return $this->send(
        (new GetStatusRequest())->withHeader('X-Debug', 'true')
    );
});

$connector->withDebugHeaders();
```

## Full Example

```php
<?php

namespace App\Http\Connectors;

use Cline\Relay\Support\Attributes\ThrowOnError;
use Cline\Relay\Features\Auth\BearerToken;
use Cline\Relay\Features\Caching\CacheConfig;
use Cline\Relay\Core\Connector;
use Cline\Relay\Features\RateLimiting\RateLimitConfig;
use Cline\Relay\Core\Request;
use Psr\SimpleCache\CacheInterface;

#[ThrowOnError(clientErrors: true, serverErrors: true)]
class GitHubConnector extends Connector
{
    public function __construct(
        private readonly string $token,
        private readonly string $apiVersion = '2022-11-28',
    ) {}

    public function baseUrl(): string
    {
        return 'https://api.github.com';
    }

    public function authenticate(Request $request): Request
    {
        return (new BearerToken($this->token))->authenticate($request);
    }

    public function defaultHeaders(): array
    {
        return [
            'Accept' => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => $this->apiVersion,
            'User-Agent' => 'MyApp/1.0',
        ];
    }

    public function timeout(): int
    {
        return 30;
    }

    public function cache(): ?CacheInterface
    {
        return app('cache')->store('redis');
    }

    public function cacheConfig(): ?CacheConfig
    {
        return new CacheConfig(ttl: 300, prefix: 'github');
    }

    public function rateLimit(): ?RateLimitConfig
    {
        return new RateLimitConfig(maxAttempts: 5000, decaySeconds: 3600);
    }
}
```

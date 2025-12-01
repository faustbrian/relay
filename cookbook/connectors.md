# Connectors

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

The `baseUrl()` method defines the API's base URL:

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

Add headers to every request:

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

Configure request and connection timeouts:

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

Pass additional configuration to Guzzle:

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

Implement the `authenticate()` method to add authentication:

```php
use Cline\Relay\Features\Auth\BearerToken;
use Cline\Relay\Core\Request;

public function authenticate(Request $request): Request
{
    return (new BearerToken($this->token))->authenticate($request);
}
```

See the [Authentication](authentication.md) guide for all available strategies.

## Sending Requests

### Using Request Objects

Send typed request objects:

```php
$connector = new GitHubConnector($token);
$response = $connector->send(new GetRepositoryRequest('laravel', 'laravel'));
```

### Using Convenience Methods

For simple requests, use convenience methods:

```php
// GET request with query parameters
$response = $connector->get('/users', ['per_page' => 10]);

// POST request with body
$response = $connector->post('/users', [
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);

// PUT request
$response = $connector->put('/users/1', ['name' => 'Jane Doe']);

// PATCH request
$response = $connector->patch('/users/1', ['email' => 'jane@example.com']);

// DELETE request
$response = $connector->delete('/users/1');
```

## Caching

Enable response caching:

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
        ttl: 3600,           // Cache for 1 hour
        prefix: 'api_cache', // Key prefix
    );
}

public function cacheTtl(): int
{
    return 3600; // Default TTL if not specified per-request
}

public function cacheKeyPrefix(): string
{
    return 'github_api';
}

public function cacheableMethods(): array
{
    return ['GET', 'HEAD']; // Only cache these methods
}
```

See the [Caching](caching.md) guide for more details.

## Rate Limiting

Configure client-side rate limiting:

```php
use Cline\Relay\Features\RateLimiting\RateLimitConfig;

public function rateLimit(): ?RateLimitConfig
{
    return new RateLimitConfig(
        maxAttempts: 100,    // Max requests
        decaySeconds: 60,    // Per time window
    );
}

public function concurrencyLimit(): ?int
{
    return 10; // Max concurrent requests
}
```

See the [Rate Limiting](rate-limiting.md) guide for more details.

## Middleware

Add custom middleware to the Guzzle handler stack:

```php
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;

public function middleware(): HandlerStack
{
    $stack = HandlerStack::create();

    // Add retry middleware
    $stack->push(Middleware::retry(
        decider: function ($retries, $request, $response, $exception) {
            return $retries < 3 && $response?->getStatusCode() >= 500;
        },
        delay: function ($retries) {
            return $retries * 1000; // Exponential backoff
        }
    ));

    // Add logging middleware
    $stack->push(Middleware::log($this->logger, new MessageFormatter()));

    return $stack;
}
```

See the [Middleware](middleware.md) guide for more details.

## Request Pooling

Send multiple requests concurrently:

```php
use Cline\Relay\Transport\Pool\Pool;

// Create a pool
$pool = $connector->pool([
    new GetUserRequest(1),
    new GetUserRequest(2),
    new GetUserRequest(3),
]);

// Or with keys
$pool = $connector->pool([
    'user1' => new GetUserRequest(1),
    'user2' => new GetUserRequest(2),
    'user3' => new GetUserRequest(3),
]);

// Configure and send
$responses = $pool
    ->concurrency(5)
    ->onResponse(fn ($response, $key) => logger()->info("Got response for {$key}"))
    ->onError(fn ($exception, $key) => logger()->error("Error for {$key}: {$exception->getMessage()}"))
    ->send();

// Access responses
$user1 = $responses['user1']->json();
```

See the [Pooling](pooling.md) guide for more details.

## Paginated Requests

Handle paginated APIs:

```php
use Cline\Relay\Features\Pagination\PaginatedResponse;

// Create paginated response
$paginator = $connector->paginate(new GetUsersRequest());

// Iterate through pages
foreach ($paginator as $response) {
    foreach ($response->json('data') as $user) {
        // Process each user
    }
}

// Or get all items at once
$allUsers = $paginator->collect('data');
```

See the [Pagination](pagination.md) guide for more details.

## Error Handling

### Throwing on Errors

Use the `#[ThrowOnError]` attribute on connectors:

```php
use Cline\Relay\Support\Attributes\ThrowOnError;

#[ThrowOnError(clientErrors: true, serverErrors: true)]
class GitHubConnector extends Connector
{
    // All 4xx and 5xx responses will throw exceptions
}
```

### Custom Exception Handling

Override exception creation methods:

```php
use Cline\Relay\Support\Exceptions\ClientException;
use Cline\Relay\Support\Exceptions\ServerException;

protected function createClientException(Request $request, Response $response): ClientException
{
    // Custom logic for client errors
    return match ($response->status()) {
        401 => new UnauthorizedException($request, $response),
        403 => new ForbiddenException($request, $response),
        404 => new NotFoundException($request, $response),
        429 => new RateLimitException::fromResponse($request, $response),
        default => new GenericClientException::fromResponse($request, $response),
    };
}

protected function createServerException(Request $request, Response $response): ServerException
{
    return match ($response->status()) {
        500 => new InternalServerException::fromResponse($request, $response),
        503 => new ServiceUnavailableException::fromResponse($request, $response),
        default => new GenericServerException::fromResponse($request, $response),
    };
}
```

## Debugging

Enable debugging for development:

```php
$connector = new GitHubConnector();
$connector->debug(); // Returns $this for chaining

// Now all requests will output debugging information
$response = $connector->send(new GetUserRequest());
```

See the [Debugging](debugging.md) guide for more details.

## Testing

Create mock connectors for testing:

```php
use Cline\Relay\Core\Response;

// Create a mock connector
$connector = GitHubConnector::fake([
    Response::make(['id' => 1, 'name' => 'John']),
    Response::make(['id' => 2, 'name' => 'Jane']),
]);

// Send requests - they return mock responses
$response1 = $connector->send(new GetUserRequest(1));
$response2 = $connector->send(new GetUserRequest(2));

// Assert requests were sent
$connector->assertSent(GetUserRequest::class);
$connector->assertSentCount(2);
```

See the [Testing](testing.md) guide for more details.

## Macros

Extend connectors with macros:

```php
use Cline\Relay\Core\Connector;

// Register a macro
Connector::macro('withDebugHeaders', function () {
    return $this->send(
        (new GetStatusRequest())->withHeader('X-Debug', 'true')
    );
});

// Use it
$connector->withDebugHeaders();
```

## Full Example

Here's a complete connector example:

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

    public function connectTimeout(): int
    {
        return 10;
    }

    public function cache(): ?CacheInterface
    {
        return app('cache')->store('redis');
    }

    public function cacheConfig(): ?CacheConfig
    {
        return new CacheConfig(
            ttl: 300,
            prefix: 'github',
        );
    }

    public function rateLimit(): ?RateLimitConfig
    {
        return new RateLimitConfig(
            maxAttempts: 5000,
            decaySeconds: 3600,
        );
    }
}
```

# Connectors

## Base Connector Class

```php
abstract class Connector
{
    abstract public function baseUrl(): string;

    // Or dynamic base URL
    public function resolveBaseUrl(): string
    {
        return $this->baseUrl();
    }

    // Optional overrides
    public function defaultHeaders(): array { return []; }
    public function defaultConfig(): array { return []; }  // Guzzle config
    public function authenticate(Request $request): void {}

    // Middleware stack (Guzzle handlers)
    public function middleware(): HandlerStack { return HandlerStack::create(); }

    // Send request
    public function send(Request $request): Response {}

    // Convenience methods (for simple one-off requests)
    public function get(string $endpoint, array $query = []): Response {}
    public function post(string $endpoint, array $data = []): Response {}
    public function put(string $endpoint, array $data = []): Response {}
    public function patch(string $endpoint, array $data = []): Response {}
    public function delete(string $endpoint): Response {}
}
```

## Example Connector

```php
class GitHubConnector extends Connector
{
    public function __construct(
        private readonly string $token,
    ) {}

    public function baseUrl(): string
    {
        return 'https://api.github.com';
    }

    public function defaultHeaders(): array
    {
        return [
            'Accept' => 'application/vnd.github.v3+json',
        ];
    }

    public function authenticate(Request $request): void
    {
        $request->withHeader('Authorization', "Bearer {$this->token}");
    }
}
```

## Error Handling

**Lenient by default** - many APIs don't follow HTTP status code conventions. Opt-in to throwing.

```php
// Default: returns Response, check status yourself
$response = $connector->send($request);

if ($response->failed()) {
    // Handle error
}

// Opt-in per request via attribute
#[Get, Json, ThrowOnError]
class GetUser extends Request { ... }

// Opt-in on connector level via attribute
#[ThrowOnError]
class ApiConnector extends Connector { ... }
```

## Exception Hierarchy

```php
RequestException
├── ClientException (4xx)
│   ├── NotFoundException (404)
│   ├── UnauthorizedException (401)
│   ├── ForbiddenException (403)
│   ├── ValidationException (422)
│   └── RateLimitException (429)
└── ServerException (5xx)
    ├── InternalServerException (500)
    └── ServiceUnavailableException (503)
```

All exceptions provide:
```php
$e->response();     // Response object
$e->request();      // Request object
$e->status();       // HTTP status code
```

## Dynamic Base URL

For APIs with different environments (staging, production, regional endpoints):

```php
class ApiConnector extends Connector
{
    public function __construct(
        private readonly string $environment = 'production',
    ) {}

    public function baseUrl(): string
    {
        return match ($this->environment) {
            'production' => 'https://api.example.com',
            'staging' => 'https://staging-api.example.com',
            'sandbox' => 'https://sandbox.example.com',
            default => throw new InvalidArgumentException("Unknown environment: {$this->environment}"),
        };
    }
}

// Usage
$connector = new ApiConnector('staging');
```

### Environment from Config

```php
class ApiConnector extends Connector
{
    public function baseUrl(): string
    {
        return config('services.api.base_url');
    }
}
```

### Per-Request Override

```php
class ApiConnector extends Connector
{
    private ?string $baseUrlOverride = null;

    public function baseUrl(): string
    {
        return 'https://api.example.com';
    }

    public function resolveBaseUrl(): string
    {
        return $this->baseUrlOverride ?? $this->baseUrl();
    }

    public function withBaseUrl(string $url): self
    {
        $clone = clone $this;
        $clone->baseUrlOverride = $url;
        return $clone;
    }
}

// Usage
$response = $connector
    ->withBaseUrl('https://eu.api.example.com')
    ->send($request);
```

### Regional Endpoints

```php
class ApiConnector extends Connector
{
    public function __construct(
        private readonly string $region = 'us',
    ) {}

    public function baseUrl(): string
    {
        return match ($this->region) {
            'us' => 'https://api.example.com',
            'eu' => 'https://eu.api.example.com',
            'ap' => 'https://ap.api.example.com',
            default => throw new InvalidArgumentException("Unknown region: {$this->region}"),
        };
    }
}
```

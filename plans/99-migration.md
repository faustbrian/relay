# Migration from Saloon

## Quick Reference

| Saloon | New Package |
|--------|-------------|
| `HasJsonBody` trait | `#[Json]` attribute |
| `HasFormBody` trait | `#[Form]` attribute |
| `HasXmlBody` trait | `#[Xml]` attribute |
| `HasMultipartBody` trait | `#[Multipart]` attribute |
| `ThrowsOnError` trait | `#[ThrowOnError]` attribute |
| `protected Method $method` | `#[Get]`, `#[Post]`, etc. |
| `resolveEndpoint()` | `endpoint()` |
| `defaultBody()` | `body()` (returns `?array`) |
| `defaultQuery()` | `query()` (returns `?array`) |
| `defaultHeaders()` | `headers()` (returns `?array`) |
| `AlwaysThrowOnErrors` trait | `#[ThrowOnError]` attribute on Connector |
| `AcceptsJson` trait | Default for `#[Json]` |
| `Authenticator` classes | Same concept, simpler interface |
| Inheritance hierarchy | Single `Request` base + attributes |
| `HasTimeout` trait | `#[Timeout]` attribute |
| `HasRetry` trait | `#[Retry]` attribute |
| `HasPagination` trait | `#[Pagination]` attribute |
| `Cacheable` trait | `#[Cache]` attribute |
| `HasRateLimiting` trait | `#[RateLimit]` attribute |
| `createDtoFromResponse()` | `#[Dto(Class::class)]` attribute |
| `boot()` method | `boot()` method (unchanged) |
| `Macroable` trait | `Macroable` trait (unchanged) |

## Before & After Examples

### Basic Request

**Saloon:**
```php
class GetUser extends Request
{
    use HasJsonBody;

    protected Method $method = Method::GET;

    public function __construct(
        protected int $id,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/users/{$this->id}";
    }
}
```

**New:**
```php
#[Get, Json]
class GetUser extends Request
{
    public function __construct(
        private readonly int $id,
    ) {}

    public function endpoint(): string
    {
        return "/users/{$this->id}";
    }
}
```

### POST with Body

**Saloon:**
```php
class CreateUser extends Request
{
    use HasJsonBody;

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

**New:**
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

### Connector with Auth

**Saloon:**
```php
class ApiConnector extends Connector
{
    use AlwaysThrowOnErrors;

    public function resolveBaseUrl(): string
    {
        return 'https://api.example.com';
    }

    protected function defaultAuth(): ?Authenticator
    {
        return new TokenAuthenticator($this->token);
    }
}
```

**New:**
```php
#[ThrowOnError]
class ApiConnector extends Connector
{
    public function __construct(
        private readonly string $token,
    ) {}

    public function baseUrl(): string
    {
        return 'https://api.example.com';
    }

    public function authenticate(Request $request): void
    {
        $request->withBearerToken($this->token);
    }
}
```

### DTO Mapping

**Saloon:**
```php
class GetUser extends Request
{
    // ...

    public function createDtoFromResponse(Response $response): UserDto
    {
        return new UserDto($response->json());
    }
}

$user = $connector->send(new GetUser(1))->dto();
```

**New:**
```php
#[Get, Json, Dto(UserDto::class)]
class GetUser extends Request
{
    // ...
}

$user = $connector->send(new GetUser(1))->dto();
```

### Pagination

**Saloon:**
```php
class GetUsers extends Request
{
    use HasPagination;

    protected function paginationConfig(): PagedPaginator
    {
        return new PagedPaginator(
            perPageKey: 'limit',
            pageKey: 'page',
        );
    }
}
```

**New:**
```php
#[Get, Json, Pagination(perPageKey: 'limit', pageKey: 'page')]
class GetUsers extends Request
{
    // ...
}
```

## New Features (No Saloon Equivalent)

These features are new and have no direct Saloon equivalent:

| Feature | Attribute/Config |
|---------|-----------------|
| Circuit Breaker | `#[CircuitBreaker]` |
| Request Signing (HMAC/AWS) | `#[HmacSignature]`, `#[AwsSignature]` |
| Idempotency Keys | `#[Idempotent]` |
| Per-Request Middleware | `#[Middleware]` |
| Request Tracing | `TracingConfig` |
| Conditional Requests (ETag) | `#[Conditional]` |
| DNS Configuration | `DnsConfig` |
| Connection Pooling | `ConnectionPoolConfig` |
| Response Hooks | `responseHooks()` |
| Request/Response Serialization | `serialize()` / `unserialize()` |

## Migration Steps

1. **Update base classes** - Change `extends SaloonRequest` to `extends Request`
2. **Replace traits with attributes** - Remove `use HasJsonBody` etc., add `#[Json]` etc.
3. **Rename methods** - `resolveEndpoint()` → `endpoint()`, `defaultBody()` → `body()`
4. **Update return types** - `body()`, `query()`, `headers()` now return `?array`
5. **Update connector** - `resolveBaseUrl()` → `baseUrl()`
6. **Update authentication** - Use `authenticate()` method or auth strategies
7. **Update tests** - Mock syntax is similar but check `fake()` method signature

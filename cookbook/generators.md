# Code Generators

Relay provides powerful Artisan commands to scaffold API integrations quickly. These generators create well-structured, type-safe code following best practices.

## Quick Start

```bash
# Create a complete integration in seconds
php artisan make:integration GitHub --oauth --resources=Users,Repositories

# Or build piece by piece
php artisan make:connector GitHub --bearer
php artisan make:resource Users GitHub --crud --requests
php artisan make:request GetUser GitHub --method=get
```

## Commands Overview

| Command | Description |
|---------|-------------|
| `make:integration` | Scaffold complete API integration |
| `make:connector` | Create connector class |
| `make:request` | Create request class |
| `make:resource` | Create resource class |

---

## make:integration

Creates a complete API integration with connector, resources, requests, and directory structure.

```bash
php artisan make:integration {name} [options]
```

### Options

| Option | Description |
|--------|-------------|
| `--oauth` | OAuth2 authentication |
| `--bearer` | Bearer token authentication |
| `--basic` | Basic authentication |
| `--api-key` | API key authentication |
| `--cache` | Enable caching |
| `--rate-limit` | Enable rate limiting |
| `--resilience` | Circuit breaker & retry |
| `--middleware` | Middleware pipeline |
| `--resources=` | Comma-separated resources |
| `--graphql` | GraphQL integration |
| `--jsonrpc` | JSON-RPC integration |
| `--soap` | SOAP integration |

### Examples

```bash
# Basic REST API
php artisan make:integration GitHub

# OAuth2 API with caching
php artisan make:integration Stripe --oauth --cache

# Full-featured integration
php artisan make:integration Twitter \
    --bearer \
    --cache \
    --rate-limit \
    --resources=Users,Tweets,DirectMessages

# GraphQL API
php artisan make:integration GitHub --graphql --bearer

# SOAP service
php artisan make:integration LegacyERP --soap --basic
```

### Generated Structure

```
app/Http/Integrations/GitHub/
├── GitHubConnector.php
├── Requests/
│   └── ExampleRequest.php
├── Resources/
│   └── BaseResource.php
└── Dto/
```

---

## make:connector

Creates a connector class that defines API configuration.

```bash
php artisan make:connector {name} [options]
```

### Options

| Option | Description |
|--------|-------------|
| `--oauth` | OAuth2 with AuthorizationCodeGrant |
| `--bearer` | Bearer token authentication |
| `--basic` | Basic (username/password) auth |
| `--api-key` | API key in header |
| `--cache` | Caching with CacheConfig |
| `--rate-limit` | Rate limiting with RateLimitConfig |
| `--resilience` | Circuit breaker & retry |
| `--middleware` | Guzzle middleware pipeline |
| `--resource` | Also create base resource |

### Examples

```bash
# Basic connector
php artisan make:connector GitHub

# With OAuth2
php artisan make:connector Stripe --oauth

# With Bearer token
php artisan make:connector OpenAI --bearer

# With API key
php artisan make:connector Weather --api-key

# With caching
php artisan make:connector CachedAPI --cache

# With rate limiting
php artisan make:connector RateLimited --rate-limit

# With resilience (circuit breaker + retry)
php artisan make:connector Resilient --resilience

# With middleware
php artisan make:connector Logged --middleware
```

### Generated Code Examples

**Basic Connector:**
```php
final class GitHubConnector extends Connector
{
    public function __construct(
        private readonly string $baseUrl = 'https://api.example.com',
    ) {}

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    public function defaultHeaders(): array
    {
        return [
            'Accept' => 'application/json',
        ];
    }
}
```

**OAuth Connector:**
```php
final class StripeConnector extends Connector
{
    use AuthorizationCodeGrant;

    public function oauthConfig(): OAuthConfig
    {
        return new OAuthConfig(
            clientId: $this->clientId,
            clientSecret: $this->clientSecret,
            redirectUri: $this->redirectUri,
            authorizeEndpoint: '/oauth/authorize',
            tokenEndpoint: '/oauth/token',
        );
    }
}
```

---

## make:request

Creates request classes for API endpoints.

```bash
php artisan make:request {name} {connector} [options]
```

### Options

| Option | Description |
|--------|-------------|
| `--method=` | HTTP method (get, post, put, patch, delete) |
| `--json` | JSON content type |
| `--form` | Form-urlencoded content type |
| `--multipart` | Multipart/form-data content type |
| `--xml` | XML content type |
| `--graphql` | GraphQL request |
| `--jsonrpc` | JSON-RPC request |
| `--soap` | SOAP request |
| `--paginate` | Page-based pagination |
| `--cursor` | Cursor-based pagination |
| `--offset` | Offset-based pagination |
| `--cache` | Add Cache attribute |
| `--retry` | Add Retry attribute |
| `--circuit` | Add CircuitBreaker attribute |
| `--idempotent` | Add Idempotent attribute |
| `--dto` | Add DTO mapping |
| `--throw` | Add ThrowOnError attribute |
| `--stream` | Create streaming request |

### Examples

```bash
# GET request
php artisan make:request GetUser GitHub

# POST request with JSON body
php artisan make:request CreateUser GitHub --method=post --json

# PUT request
php artisan make:request UpdateUser GitHub --method=put

# DELETE request
php artisan make:request DeleteUser GitHub --method=delete

# Paginated request
php artisan make:request ListUsers GitHub --paginate

# Cursor pagination
php artisan make:request ListItems GitHub --cursor

# With caching and retry
php artisan make:request GetProduct Shop --cache --retry

# GraphQL query
php artisan make:request GetUserQuery GitHub --graphql

# JSON-RPC method
php artisan make:request GetBalance Wallet --jsonrpc

# SOAP method
php artisan make:request GetWeather Weather --soap

# Streaming
php artisan make:request StreamEvents GitHub --stream
```

### Generated Code Examples

**GET Request:**
```php
#[Get(), Json()]
final class GetUserRequest extends Request
{
    public function __construct(
        private readonly int $id,
    ) {}

    public function endpoint(): string
    {
        return '/users/'.$this->id;
    }
}
```

**POST Request:**
```php
#[Post(), Json()]
final class CreateUserRequest extends Request
{
    public function __construct(
        private readonly array $data,
    ) {}

    public function endpoint(): string
    {
        return '/users';
    }

    public function body(): array
    {
        return $this->data;
    }
}
```

**Paginated Request:**
```php
#[Get(), Json()]
#[Pagination(
    pageParam: 'page',
    perPageParam: 'per_page',
    perPage: 25,
    totalKey: 'meta.total',
    lastPageKey: 'meta.last_page',
)]
final class ListUsersRequest extends Request
{
    public function __construct(
        private readonly int $page = 1,
        private readonly int $perPage = 25,
    ) {}

    public function endpoint(): string
    {
        return '/users';
    }

    public function query(): array
    {
        return [
            'page' => $this->page,
            'per_page' => $this->perPage,
        ];
    }
}
```

**GraphQL Request:**
```php
final class GetUserQueryRequest extends GraphQLRequest
{
    public function __construct(
        private readonly int $id,
    ) {}

    public function graphqlQuery(): string
    {
        return <<<'GRAPHQL'
            query GetResource($id: ID!) {
                resource(id: $id) {
                    id
                    name
                }
            }
            GRAPHQL;
    }

    public function variables(): ?array
    {
        return ['id' => $this->id];
    }
}
```

---

## make:resource

Creates resource classes that group related requests.

```bash
php artisan make:resource {name} {connector} [options]
```

### Options

| Option | Description |
|--------|-------------|
| `--crud` | Generate CRUD methods |
| `--paginate` | Add pagination support |
| `--requests` | Also create request classes |

### Examples

```bash
# Basic resource
php artisan make:resource Users GitHub

# With CRUD methods
php artisan make:resource Users GitHub --crud

# With pagination
php artisan make:resource Posts GitHub --paginate

# CRUD with all request classes
php artisan make:resource Users GitHub --crud --requests
```

### Generated Code Examples

**Basic Resource:**
```php
final class UsersResource extends Resource
{
    //
}
```

**CRUD Resource:**
```php
final class UsersResource extends Resource
{
    public function get(int|string $id): Response
    {
        return $this->send(new GetUsersRequest($id));
    }

    public function list(int $page = 1, int $perPage = 25): PaginatedResponse
    {
        return $this->connector()->paginate(
            new ListUserssRequest($page, $perPage),
        );
    }

    public function create(array $data): Response
    {
        return $this->send(new CreateUsersRequest($data));
    }

    public function update(int|string $id, array $data): Response
    {
        return $this->send(new UpdateUsersRequest($id, $data));
    }

    public function delete(int|string $id): Response
    {
        return $this->send(new DeleteUsersRequest($id));
    }
}
```

---

## Customizing Stubs

You can customize the generated code by publishing and editing the stubs:

```bash
# Publish stubs to your project
php artisan vendor:publish --tag=relay-stubs
```

This creates a `stubs/relay/` directory where you can modify any stub:

```
stubs/relay/
├── connector.stub
├── connector.oauth.stub
├── connector.bearer.stub
├── connector.cache.stub
├── request.stub
├── request.body.stub
├── request.graphql.stub
├── resource.stub
├── resource.crud.stub
└── ...
```

### Stub Placeholders

| Placeholder | Description |
|-------------|-------------|
| `{{ namespace }}` | Generated namespace |
| `{{ class }}` | Class name |
| `{{ connectorName }}` | Connector name |
| `{{ connector }}` | Parent connector |
| `{{ methodClass }}` | HTTP method class |
| `{{ contentType }}` | Content type class |
| `{{ attributes }}` | Additional attributes |
| `{{ resourceName }}` | Resource name |

---

## Common Workflows

### New API Integration

```bash
# 1. Create full integration
php artisan make:integration Stripe --oauth --rate-limit

# 2. Add resources
php artisan make:resource Customers Stripe --crud --requests
php artisan make:resource Payments Stripe --crud --requests

# 3. Add specialized requests
php artisan make:request RefundPayment Stripe --method=post --idempotent
```

### Adding to Existing Integration

```bash
# Add a new resource
php artisan make:resource Subscriptions Stripe --crud --requests

# Add individual requests
php artisan make:request CancelSubscription Stripe --method=post
php artisan make:request ListInvoices Stripe --paginate
```

### Protocol-Specific Integrations

```bash
# GraphQL API
php artisan make:integration GitHub --graphql --bearer
php artisan make:request GetViewer GitHub --graphql
php artisan make:request SearchRepositories GitHub --graphql

# JSON-RPC Service
php artisan make:integration Ethereum --jsonrpc
php artisan make:request GetBalance Ethereum --jsonrpc
php artisan make:request SendTransaction Ethereum --jsonrpc

# SOAP Service
php artisan make:integration LegacyERP --soap --basic
php artisan make:request GetInventory LegacyERP --soap
```

---

## Best Practices

1. **Use `make:integration` for new APIs** - It sets up the complete structure
2. **Use `--crud --requests` together** - Creates matching requests for resources
3. **Choose authentication upfront** - Harder to change later
4. **Add rate limiting for external APIs** - Prevents hitting API limits
5. **Use `--cache` for read-heavy endpoints** - Improves performance
6. **Use `--retry` for unreliable APIs** - Handles transient failures

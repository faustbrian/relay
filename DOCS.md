## Table of Contents

1. [MIGRATION FROM SALOON](#doc-docs-migration-from-saloon)
2. [Overview](#doc-docs-readme)
3. [Advanced Usage](#doc-docs-advanced-usage)
4. [Attributes](#doc-docs-attributes)
5. [Authentication](#doc-docs-authentication)
6. [Caching](#doc-docs-caching)
7. [Connectors](#doc-docs-connectors)
8. [Debugging](#doc-docs-debugging)
9. [Generators](#doc-docs-generators)
10. [Json Rpc Microservices](#doc-docs-json-rpc-microservices)
11. [Middleware](#doc-docs-middleware)
12. [Pagination](#doc-docs-pagination)
13. [Pooling](#doc-docs-pooling)
14. [Rate Limiting](#doc-docs-rate-limiting)
15. [Requests](#doc-docs-requests)
16. [Resilience](#doc-docs-resilience)
17. [Responses](#doc-docs-responses)
18. [Testing](#doc-docs-testing)
<a id="doc-docs-migration-from-saloon"></a>

# Migration Guide: Saloon v3 to Relay

This guide provides comprehensive examples for migrating from Saloon v3 to Relay, covering connectors, requests, resources, authentication (OAuth2, token-based, and header-based), and pagination.

## Table of Contents

1. [Core Concepts Comparison](#core-concepts-comparison)
2. [Connector Migration](#connector-migration)
3. [Request Migration](#request-migration)
4. [Resource Migration](#resource-migration)
5. [OAuth2 Authorization Code Flow (Fortnox Example)](#oauth2-authorization-code-flow-fortnox-example)
6. [OAuth2 Client Credentials Flow (Posti Example)](#oauth2-client-credentials-flow-posti-example)
7. [Token-Based Authentication (Ropo24 Example)](#token-based-authentication-ropo24-example)
8. [Pagination Migration](#pagination-migration)
9. [Testing Migration](#testing-migration)
10. [SoloRequest Migration](#solorequest-migration-standalone-requests)
11. [HasConnector Trait Migration](#hasconnector-trait-migration-self-contained-requests)
12. [Request Timeout Migration](#request-timeout-migration)
13. [DTO Response Mapping](#dto-response-mapping-createdtofromresponse)
14. [WithResponse / HasResponse DTO Migration](#withresponse--hasresponse-dto-migration)
15. [Resilience Features (Relay-Only)](#resilience-features-retry-circuit-breaker)
16. [Concurrent Request Pools](#concurrent-request-pools)
17. [Request Caching (Relay-Only)](#request-caching)
18. [Rate Limiting](#rate-limiting)
19. [Middleware](#middleware)

---

## Core Concepts Comparison

| Saloon | Relay | Notes |
|--------|-------|-------|
| `Connector` extends `Saloon\Http\Connector` | `Connector` extends `Cline\Relay\Core\Connector` | Similar structure |
| `Request` extends `Saloon\Http\Request` | `Request` extends `Cline\Relay\Core\Request` | Uses PHP attributes |
| `BaseResource` extends `Saloon\Http\BaseResource` | `Resource` extends `Cline\Relay\Core\Resource` | Simplified |
| `protected Method $method = Method::POST` | `#[Post]` attribute | Declarative |
| `use HasJsonBody` trait | `#[Json]` attribute | Declarative |
| `resolveEndpoint()` | `endpoint()` | Method name change |
| `resolveBaseUrl()` | `baseUrl()` or `resolveBaseUrl()` | Both supported |
| `use AlwaysThrowOnErrors` trait | `#[ThrowOnError]` attribute | Declarative |
| `use AcceptsJson` trait | Automatic via `#[Json]` | Sets Accept header |

---

## Connector Migration

### Saloon (Before)

```php
<?php declare(strict_types=1);

namespace Support\Saloon\Example;

use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;

final class ExampleConnector extends Connector
{
    use AlwaysThrowOnErrors;
    use AcceptsJson;

    public function resolveBaseUrl(): string
    {
        return (string) config('services.example.base_url');
    }

    protected function defaultHeaders(): array
    {
        return [
            'X-Custom-Header' => 'value',
        ];
    }
}
```

### Relay (After)

```php
<?php declare(strict_types=1);

namespace Support\Relay\Example;

use Cline\Relay\Core\Connector;
use Cline\Relay\Support\Attributes\ThrowOnError;

#[ThrowOnError]
final class ExampleConnector extends Connector
{
    public function baseUrl(): string
    {
        return (string) config('services.example.base_url');
    }

    public function defaultHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'X-Custom-Header' => 'value',
        ];
    }
}
```

---

## Request Migration

### Saloon (Before)

```php
<?php declare(strict_types=1);

namespace Support\Saloon\Example\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

final class CreateResourceRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        private readonly array $data,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/resources';
    }

    protected function defaultBody(): array
    {
        return $this->data;
    }
}
```

### Relay (After)

```php
<?php declare(strict_types=1);

namespace Support\Relay\Example\Requests;

use Cline\Relay\Core\Request;
use Cline\Relay\Support\Attributes\ContentTypes\Json;
use Cline\Relay\Support\Attributes\Methods\Post;

#[Post]
#[Json]
final class CreateResourceRequest extends Request
{
    public function __construct(
        private readonly array $data,
    ) {}

    public function endpoint(): string
    {
        return '/resources';
    }

    public function body(): ?array
    {
        return $this->data;
    }
}
```

### GET Request Comparison

#### Saloon

```php
final class GetResourceRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $id,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/resources/' . $this->id;
    }

    protected function defaultQuery(): array
    {
        return ['include' => 'details'];
    }
}
```

#### Relay

```php
use Cline\Relay\Support\Attributes\Methods\Get;

#[Get]
final class GetResourceRequest extends Request
{
    public function __construct(
        private readonly string $id,
    ) {}

    public function endpoint(): string
    {
        return '/resources/' . $this->id;
    }

    public function query(): ?array
    {
        return ['include' => 'details'];
    }
}
```

---

## Resource Migration

### Saloon (Before)

```php
<?php declare(strict_types=1);

namespace Support\Saloon\Fortnox\Resource;

use Saloon\Http\BaseResource;
use Saloon\Http\Response;

final class FortnoxInvoiceResource extends BaseResource
{
    public function retrieve(string $documentNumber): Response
    {
        return $this->connector->send(new RetrieveInvoiceRequest($documentNumber));
    }

    public function create(InvoiceData $data): Response
    {
        return $this->connector->send(new CreateInvoiceRequest($data));
    }
}
```

### Relay (After)

```php
<?php declare(strict_types=1);

namespace Support\Relay\Fortnox\Resource;

use Cline\Relay\Core\Resource;
use Cline\Relay\Core\Response;

final class FortnoxInvoiceResource extends Resource
{
    public function retrieve(string $documentNumber): Response
    {
        return $this->send(new RetrieveInvoiceRequest($documentNumber));
    }

    public function create(InvoiceData $data): Response
    {
        return $this->send(new CreateInvoiceRequest($data));
    }
}
```

**Key differences:**
- Use `$this->send()` instead of `$this->connector->send()`
- Relay's `Resource::send()` automatically sets the resource on the request

---

## OAuth2 Authorization Code Flow (Fortnox Example)

### Saloon (Before)

**FortnoxAuthConnector.php:**
```php
<?php declare(strict_types=1);

namespace Support\Saloon\Fortnox;

use Saloon\Helpers\OAuth2\OAuthConfig;
use Saloon\Http\Auth\BasicAuthenticator;
use Saloon\Http\Connector;
use Saloon\Traits\OAuth2\AuthorizationCodeGrant;

final class FortnoxAuthConnector extends Connector
{
    use AuthorizationCodeGrant;

    public function resolveBaseUrl(): string
    {
        return (string) config('services.fortnox.base_auth_url');
    }

    protected function defaultOauthConfig(): OAuthConfig
    {
        $scopes = Str::of(config('services.fortnox.scopes'))->explode(',')->toArray();

        return OAuthConfig::make()
            ->setClientId(config('services.fortnox.client_id'))
            ->setClientSecret(config('services.fortnox.client_secret'))
            ->setDefaultScopes($scopes)
            ->setAuthorizeEndpoint('/oauth-v1/auth')
            ->setTokenEndpoint('/oauth-v1/token')
            ->setRedirectUri(route('integrations:callback', [IntegrationType::FORTNOX]));
    }

    protected function defaultAuth(): BasicAuthenticator
    {
        return new BasicAuthenticator(
            $this->oauthConfig()->getClientId(),
            $this->oauthConfig()->getClientSecret()
        );
    }
}
```

**FortnoxApiConnector.php (with auto-refresh):**
```php
<?php declare(strict_types=1);

namespace Support\Saloon\Fortnox;

use Saloon\Http\Auth\AccessTokenAuthenticator;
use Saloon\Http\Connector;
use Saloon\Http\PendingRequest;

final class FortnoxApiConnector extends Connector
{
    use AlwaysThrowOnErrors;
    use AcceptsJson;

    public function resolveBaseUrl(): string
    {
        return (string) config('services.fortnox.base_url');
    }

    public function boot(PendingRequest $pendingRequest): void
    {
        if ($pendingRequest->getRequest() instanceof GetAccessTokenRequest) {
            return;
        }

        $integration = Integration::where('type', IntegrationType::FORTNOX)->firstOrFail();

        if ($integration->authenticator) {
            $authenticator = AccessTokenAuthenticator::unserialize($integration->authenticator);

            if ($authenticator->hasExpired()) {
                $connector = new FortnoxAuthConnector();
                $authenticator = $connector->refreshAccessToken($authenticator);

                $integration->update(['authenticator' => $authenticator->serialize()]);
            }

            $pendingRequest->authenticate($authenticator);
        }
    }
}
```

### Relay (After)

**FortnoxAuthConnector.php:**
```php
<?php declare(strict_types=1);

namespace Support\Relay\Fortnox;

use Cline\Relay\Core\Connector;
use Cline\Relay\Core\Request;
use Cline\Relay\Features\Auth\BasicAuth;
use Cline\Relay\Features\OAuth2\AuthorizationCodeGrant;
use Cline\Relay\Features\OAuth2\OAuthConfig;
use Cline\Relay\Support\Attributes\ThrowOnError;
use Illuminate\Support\Str;

#[ThrowOnError]
final class FortnoxAuthConnector extends Connector
{
    use AuthorizationCodeGrant;

    public function baseUrl(): string
    {
        return (string) config('services.fortnox.base_auth_url');
    }

    public function oauthConfig(): OAuthConfig
    {
        $scopes = Str::of(config('services.fortnox.scopes'))->explode(',')->toArray();

        return OAuthConfig::make()
            ->setClientId((string) config('services.fortnox.client_id'))
            ->setClientSecret((string) config('services.fortnox.client_secret'))
            ->setDefaultScopes($scopes)
            ->setAuthorizeEndpoint('/oauth-v1/auth')
            ->setTokenEndpoint('/oauth-v1/token')
            ->setRedirectUri(route('integrations:callback', [IntegrationType::FORTNOX]))
            ->setOnTokenRefreshed(function ($newToken, $oldToken) {
                // Automatically called when token is refreshed
                Integration::where('type', IntegrationType::FORTNOX)
                    ->update(['authenticator' => $newToken->serialize()]);
            });
    }

    public function authenticate(Request $request): void
    {
        (new BasicAuth(
            $this->oauthConfig()->getClientId(),
            $this->oauthConfig()->getClientSecret()
        ))->authenticate($request);
    }
}
```

**FortnoxApiConnector.php:**
```php
<?php declare(strict_types=1);

namespace Support\Relay\Fortnox;

use Cline\Relay\Core\Connector;
use Cline\Relay\Core\Request;
use Cline\Relay\Features\Auth\AccessTokenAuthenticator;
use Cline\Relay\Support\Attributes\ThrowOnError;

#[ThrowOnError]
final class FortnoxApiConnector extends Connector
{
    private ?AccessTokenAuthenticator $authenticator = null;

    public function baseUrl(): string
    {
        return (string) config('services.fortnox.base_url');
    }

    public function defaultHeaders(): array
    {
        return [
            'Accept' => 'application/json',
        ];
    }

    public function authenticate(Request $request): void
    {
        $integration = Integration::where('type', IntegrationType::FORTNOX)->firstOrFail();

        if (!$integration->authenticator) {
            return;
        }

        $authenticator = AccessTokenAuthenticator::unserialize($integration->authenticator);

        if ($authenticator->hasExpired()) {
            $authConnector = new FortnoxAuthConnector();
            $authenticator = $authConnector->refreshAccessToken($authenticator);

            $integration->update(['authenticator' => $authenticator->serialize()]);
        }

        $this->authenticator = $authenticator;
        $authenticator->authenticate($request);
    }

    // Resources
    public function invoice(): FortnoxInvoiceResource
    {
        return new FortnoxInvoiceResource($this);
    }

    public function customer(): FortnoxCustomerResource
    {
        return new FortnoxCustomerResource($this);
    }
}
```

**Usage in controller:**

```php
// Saloon
$connector = new FortnoxAuthConnector();
$authorizationUrl = $connector->getAuthorizationUrl();

// Handle callback
$authenticator = $connector->getAccessToken($code, $state);
$integration->update(['authenticator' => $authenticator->serialize()]);

// Relay (identical API)
$connector = new FortnoxAuthConnector();
$authorizationUrl = $connector->getAuthorizationUrl();

// Handle callback
$authenticator = $connector->getAccessToken($code, $state);
$integration->update(['authenticator' => $authenticator->serialize()]);
```

---

## OAuth2 Client Credentials Flow (Posti Example)

### Saloon (Before)

```php
<?php declare(strict_types=1);

namespace Support\Saloon\Posti;

use Saloon\Helpers\OAuth2\OAuthConfig;
use Saloon\Http\Auth\BasicAuthenticator;
use Saloon\Http\Connector;
use Saloon\Traits\OAuth2\ClientCredentialsGrant;

final class PostiAuthConnector extends Connector
{
    use ClientCredentialsGrant;
    use AcceptsJson;
    use AlwaysThrowOnErrors;

    public function resolveBaseUrl(): string
    {
        return (string) config('services.posti.base_auth_url');
    }

    protected function defaultOauthConfig(): OAuthConfig
    {
        return OAuthConfig::make()
            ->setClientId(config('services.posti.client_id'))
            ->setClientSecret(config('services.posti.client_secret'))
            ->setTokenEndpoint('/oauth/token');
    }

    protected function defaultAuth(): BasicAuthenticator
    {
        return new BasicAuthenticator(
            $this->oauthConfig()->getClientId(),
            $this->oauthConfig()->getClientSecret()
        );
    }
}
```

### Relay (After)

```php
<?php declare(strict_types=1);

namespace Support\Relay\Posti;

use Cline\Relay\Core\Connector;
use Cline\Relay\Core\Request;
use Cline\Relay\Features\Auth\BasicAuth;
use Cline\Relay\Features\OAuth2\ClientCredentialsGrant;
use Cline\Relay\Features\OAuth2\OAuthConfig;
use Cline\Relay\Support\Attributes\ThrowOnError;

#[ThrowOnError]
final class PostiAuthConnector extends Connector
{
    use ClientCredentialsGrant;

    public function baseUrl(): string
    {
        return (string) config('services.posti.base_auth_url');
    }

    public function oauthConfig(): OAuthConfig
    {
        return OAuthConfig::make()
            ->setClientId((string) config('services.posti.client_id'))
            ->setClientSecret((string) config('services.posti.client_secret'))
            ->setTokenEndpoint('/oauth/token');
    }

    public function authenticate(Request $request): void
    {
        (new BasicAuth(
            $this->oauthConfig()->getClientId(),
            $this->oauthConfig()->getClientSecret()
        ))->authenticate($request);
    }
}
```

**Usage:**

```php
// Saloon
$authConnector = new PostiAuthConnector();
$authenticator = $authConnector->getAccessToken();

// Relay (identical API)
$authConnector = new PostiAuthConnector();
$authenticator = $authConnector->getAccessToken();
```

---

## Token-Based Authentication (Ropo24 Example)

### Saloon (Before)

**Ropo24GetAccessTokenRequest.php:**
```php
<?php declare(strict_types=1);

namespace Support\Saloon\Ropo24\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

final class Ropo24GetAccessTokenRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        private readonly string $cid,
        private readonly string $apicode,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/rest/token';
    }

    protected function defaultBody(): array
    {
        return [
            'cid' => $this->cid,
            'apicode' => $this->apicode,
        ];
    }
}
```

**Ropo24Connector.php:**
```php
<?php declare(strict_types=1);

namespace Support\Saloon\Ropo24;

use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\Connector;
use Saloon\Http\PendingRequest;

final class Ropo24Connector extends Connector
{
    use AlwaysThrowOnErrors;
    use AcceptsJson;

    public function resolveBaseUrl(): string
    {
        return (string) config('services.ropo24.base_url');
    }

    public function boot(PendingRequest $pendingRequest): void
    {
        if ($pendingRequest->getRequest() instanceof Ropo24GetAccessTokenRequest) {
            return;
        }

        $authResponse = $this->send(new Ropo24GetAccessTokenRequest(
            config('services.ropo24.cid'),
            config('services.ropo24.apicode')
        ));

        $authenticator = new TokenAuthenticator($authResponse->json('token'));
        $pendingRequest->authenticate($authenticator);
    }
}
```

### Relay (After)

**Ropo24GetAccessTokenRequest.php:**
```php
<?php declare(strict_types=1);

namespace Support\Relay\Ropo24\Requests;

use Cline\Relay\Core\Request;
use Cline\Relay\Support\Attributes\ContentTypes\Json;
use Cline\Relay\Support\Attributes\Methods\Post;

#[Post]
#[Json]
final class Ropo24GetAccessTokenRequest extends Request
{
    public function __construct(
        private readonly string $cid,
        private readonly string $apicode,
    ) {}

    public function endpoint(): string
    {
        return '/rest/token';
    }

    public function body(): ?array
    {
        return [
            'cid' => $this->cid,
            'apicode' => $this->apicode,
        ];
    }
}
```

**Ropo24Connector.php:**
```php
<?php declare(strict_types=1);

namespace Support\Relay\Ropo24;

use Cline\Relay\Core\Connector;
use Cline\Relay\Core\Request;
use Cline\Relay\Features\Auth\BearerToken;
use Cline\Relay\Support\Attributes\ThrowOnError;

#[ThrowOnError]
final class Ropo24Connector extends Connector
{
    private ?string $token = null;

    public function baseUrl(): string
    {
        return (string) config('services.ropo24.base_url');
    }

    public function defaultHeaders(): array
    {
        return [
            'Accept' => 'application/json',
        ];
    }

    public function authenticate(Request $request): void
    {
        // Skip auth for the token request itself
        if ($request instanceof Ropo24GetAccessTokenRequest) {
            return;
        }

        // Fetch token if not cached
        if ($this->token === null) {
            $authResponse = $this->send(new Ropo24GetAccessTokenRequest(
                (string) config('services.ropo24.cid'),
                (string) config('services.ropo24.apicode')
            ));

            $this->token = $authResponse->json('token');
        }

        (new BearerToken($this->token))->authenticate($request);
    }
}
```

**Alternative: Using HeaderAuth for custom header tokens:**

```php
use Cline\Relay\Features\Auth\HeaderAuth;

public function authenticate(Request $request): void
{
    if ($request instanceof Ropo24GetAccessTokenRequest) {
        return;
    }

    if ($this->token === null) {
        $this->token = $this->fetchToken();
    }

    // For APIs using custom header names
    (new HeaderAuth('X-API-Token', $this->token))->authenticate($request);
}
```

---

## Pagination Migration

### Saloon (Before)

```php
<?php declare(strict_types=1);

namespace Support\Saloon\Fortnox;

use Saloon\Http\Connector;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\HasPagination;
use Saloon\PaginationPlugin\PagedPaginator;

final class FortnoxApiConnector extends Connector implements HasPagination
{
    public function paginate(Request $request): PagedPaginator
    {
        return new class($this, $request) extends PagedPaginator
        {
            protected function isLastPage(Response $response): bool
            {
                return $response->json('MetaInformation.@CurrentPage')
                    >= $response->json('MetaInformation.@TotalPages');
            }

            protected function getPageItems(Response $response, Request $request): array
            {
                return $response->json();
            }

            protected function applyPagination(Request $request): Request
            {
                $request->query()->add('page', $this->currentPage + 1);
                return $request;
            }
        };
    }
}
```

### Relay (After)

**Using attributes (preferred for standard pagination):**

```php
<?php declare(strict_types=1);

namespace Support\Relay\Fortnox\Requests;

use Cline\Relay\Core\Request;
use Cline\Relay\Support\Attributes\Methods\Get;
use Cline\Relay\Support\Attributes\Pagination\Pagination;

#[Get]
#[Pagination(
    pageParam: 'page',
    perPageParam: 'limit',
    perPage: 100,
    totalPath: 'MetaInformation.@TotalResources',
    totalPagesPath: 'MetaInformation.@TotalPages',
    currentPagePath: 'MetaInformation.@CurrentPage',
)]
final class ListInvoicesRequest extends Request
{
    public function endpoint(): string
    {
        return '/invoices';
    }
}

// Usage
$connector = new FortnoxApiConnector();
$paginated = $connector->paginate(new ListInvoicesRequest());

// Iterate all pages
foreach ($paginated as $page) {
    foreach ($page->json('Invoices') as $invoice) {
        // Process invoice
    }
}

// Or collect all items
$allInvoices = $paginated->collect('Invoices');
```

**Cursor pagination:**

```php
use Cline\Relay\Support\Attributes\Pagination\CursorPagination;

#[Get]
#[CursorPagination(
    cursorParam: 'cursor',
    nextCursorPath: 'meta.next_cursor',
    perPageParam: 'limit',
    perPage: 100,
)]
final class ListItemsRequest extends Request
{
    public function endpoint(): string
    {
        return '/items';
    }
}
```

**Offset pagination:**

```php
use Cline\Relay\Support\Attributes\Pagination\OffsetPagination;

#[Get]
#[OffsetPagination(
    offsetParam: 'offset',
    limitParam: 'limit',
    limit: 100,
    totalPath: 'total',
)]
final class SearchRequest extends Request
{
    public function endpoint(): string
    {
        return '/search';
    }
}
```

---

## Testing Migration

### Saloon (Before)

```php
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

$mockClient = new MockClient([
    GetInvoiceRequest::class => MockResponse::make(['id' => 1], 200),
]);

$connector = new FortnoxApiConnector();
$connector->withMockClient($mockClient);

$response = $connector->send(new GetInvoiceRequest('123'));
```

### Relay (After)

```php
use Cline\Relay\Testing\MockClient;
use Cline\Relay\Testing\MockResponse;

// Option 1: Using fake() factory
$connector = FortnoxApiConnector::fake([
    MockResponse::make(['id' => 1], 200),
]);

$response = $connector->send(new GetInvoiceRequest('123'));

// Option 2: Using withMockClient
$mockClient = new MockClient([
    GetInvoiceRequest::class => MockResponse::make(['id' => 1], 200),
]);

$connector = new FortnoxApiConnector();
$connector->withMockClient($mockClient);

// Option 3: Global mock (affects all connectors)
MockClient::global([
    MockResponse::make(['id' => 1], 200),
]);

// Option 4: Prevent stray requests in tests
MockClient::preventStrayRequests();
```

---

## Quick Reference: Authentication Methods

| Auth Type | Saloon | Relay |
|-----------|--------|-------|
| Bearer Token | `TokenAuthenticator` | `BearerToken` |
| Basic Auth | `BasicAuthenticator` | `BasicAuth` |
| Header | Manual in `defaultHeaders()` | `HeaderAuth` |
| Query | Manual in `defaultQuery()` | `QueryAuth` |
| OAuth2 Access Token | `AccessTokenAuthenticator` | `AccessTokenAuthenticator` |
| API Key | Manual | `ApiKeyAuth` |
| JWT | Manual | `JwtAuth` |
| Digest | Not built-in | `DigestAuth` |

---

## Quick Reference: Method Attributes

| HTTP Method | Saloon | Relay |
|-------------|--------|-------|
| GET | `protected Method $method = Method::GET` | `#[Get]` |
| POST | `protected Method $method = Method::POST` | `#[Post]` |
| PUT | `protected Method $method = Method::PUT` | `#[Put]` |
| PATCH | `protected Method $method = Method::PATCH` | `#[Patch]` |
| DELETE | `protected Method $method = Method::DELETE` | `#[Delete]` |
| HEAD | `protected Method $method = Method::HEAD` | `#[Head]` |
| OPTIONS | `protected Method $method = Method::OPTIONS` | `#[Options]` |

---

## Quick Reference: Content Type Attributes

| Content Type | Saloon | Relay |
|--------------|--------|-------|
| JSON | `use HasJsonBody` | `#[Json]` |
| Form | `use HasFormBody` | `#[Form]` |
| Multipart | `use HasMultipartBody` | `#[Multipart]` |
| XML | `use HasXmlBody` | `#[Xml]` |
| YAML | Custom | `#[Yaml]` |

---

## SoloRequest Migration (Standalone Requests)

Saloon provides `SoloRequest` for requests that don't need a connector. In Relay, use inline requests with `Connector::get()`, `post()`, etc., or create a minimal connector.

### Saloon (Before)

```php
<?php declare(strict_types=1);

namespace Support\Saloon\Matkahuolto\Requests;

use Saloon\Enums\Method;
use Saloon\Http\SoloRequest;

final class TrackingInfoRequest extends SoloRequest
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $parcelNumber,
    ) {}

    public function resolveEndpoint(): string
    {
        return sprintf('%s/search/trackingInfo', config('services.matkahuolto.base_url'));
    }

    protected function defaultQuery(): array
    {
        return [
            'language' => 'en',
            'parcelNumber' => $this->parcelNumber,
        ];
    }
}

// Usage
$request = new TrackingInfoRequest('123456');
$response = $request->send();
```

### Relay (After)

**Option 1: Minimal Connector (recommended for reusability):**

```php
<?php declare(strict_types=1);

namespace Support\Relay\Matkahuolto;

use Cline\Relay\Core\Connector;

final class MatkahuoltoConnector extends Connector
{
    public function baseUrl(): string
    {
        return (string) config('services.matkahuolto.base_url');
    }
}
```

```php
use Cline\Relay\Support\Attributes\Methods\Get;

#[Get]
final class TrackingInfoRequest extends Request
{
    public function __construct(
        private readonly string $parcelNumber,
    ) {}

    public function endpoint(): string
    {
        return '/search/trackingInfo';
    }

    public function query(): ?array
    {
        return [
            'language' => 'en',
            'parcelNumber' => $this->parcelNumber,
        ];
    }
}

// Usage
$connector = new MatkahuoltoConnector();
$response = $connector->send(new TrackingInfoRequest('123456'));
```

**Option 2: Inline request (for one-off requests):**

```php
$connector = new MatkahuoltoConnector();
$response = $connector->get('/search/trackingInfo', [
    'language' => 'en',
    'parcelNumber' => '123456',
]);
```

---

## HasConnector Trait Migration (Self-Contained Requests)

Saloon's `HasConnector` trait allows requests to resolve their own connector. In Relay, use dependency injection or create the connector in a factory method.

### Saloon (Before)

```php
<?php declare(strict_types=1);

namespace Support\Saloon\Europa\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Connector;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;
use Saloon\Traits\Request\HasConnector;

final class EuropaCheckVatNumberRequest extends Request implements HasBody
{
    use HasConnector;
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        private readonly string $countryCode,
        private readonly string $vatNumber,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/check-vat-number';
    }

    protected function resolveConnector(): Connector
    {
        return new EuropaConnector();
    }

    protected function defaultBody(): array
    {
        return [
            'countryCode' => $this->countryCode,
            'vatNumber' => $this->vatNumber,
        ];
    }
}

// Usage - request creates its own connector
$request = new EuropaCheckVatNumberRequest('FI', '12345678');
$response = $request->send();
```

### Relay (After)

```php
<?php declare(strict_types=1);

namespace Support\Relay\Europa\Requests;

use Cline\Relay\Core\Request;
use Cline\Relay\Support\Attributes\ContentTypes\Json;
use Cline\Relay\Support\Attributes\Methods\Post;

#[Post]
#[Json]
final class EuropaCheckVatNumberRequest extends Request
{
    public function __construct(
        private readonly string $countryCode,
        private readonly string $vatNumber,
    ) {}

    public function endpoint(): string
    {
        return '/check-vat-number';
    }

    public function body(): ?array
    {
        return [
            'countryCode' => $this->countryCode,
            'vatNumber' => $this->vatNumber,
        ];
    }

    /**
     * Convenience factory for self-contained usage.
     */
    public static function check(string $countryCode, string $vatNumber): Response
    {
        $connector = new EuropaConnector();
        return $connector->send(new self($countryCode, $vatNumber));
    }
}

// Usage Option 1: Via connector (recommended)
$connector = new EuropaConnector();
$response = $connector->send(new EuropaCheckVatNumberRequest('FI', '12345678'));

// Usage Option 2: Via static factory
$response = EuropaCheckVatNumberRequest::check('FI', '12345678');
```

---

## Request Timeout Migration

Saloon uses `HasTimeout` trait with `$connectTimeout` and `$requestTimeout` properties. Relay uses the `#[Timeout]` attribute.

### Saloon (Before)

```php
<?php declare(strict_types=1);

namespace Support\Saloon\Ropo24\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Plugins\HasTimeout;

final class Ropo24StatusUpdatesRequest extends Request
{
    use HasTimeout;

    protected Method $method = Method::GET;

    protected int $connectTimeout = 60;
    protected int $requestTimeout = 240;

    public function resolveEndpoint(): string
    {
        return '/rest/jobs/statusupdates';
    }
}
```

### Relay (After)

```php
<?php declare(strict_types=1);

namespace Support\Relay\Ropo24\Requests;

use Cline\Relay\Core\Request;
use Cline\Relay\Support\Attributes\Methods\Get;
use Cline\Relay\Support\Attributes\Resilience\Timeout;

#[Get]
#[Timeout(seconds: 240, connect: 60)]
final class Ropo24StatusUpdatesRequest extends Request
{
    public function endpoint(): string
    {
        return '/rest/jobs/statusupdates';
    }
}
```

**Connector-level timeout:**

```php
// Saloon
final class SlowApiConnector extends Connector
{
    protected int $connectTimeout = 30;
    protected int $requestTimeout = 120;
}

// Relay
final class SlowApiConnector extends Connector
{
    public function timeout(): int
    {
        return 120;
    }

    public function connectTimeout(): int
    {
        return 30;
    }
}
```

---

## DTO Response Mapping (createDtoFromResponse)

Both Saloon and Relay support mapping responses to DTOs via `createDtoFromResponse()`.

### Saloon (Before)

```php
<?php declare(strict_types=1);

namespace Support\Saloon\Ropo24\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

final class Ropo24StatusUpdatesRequest extends Request
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/rest/jobs/statusupdates';
    }

    public function createDtoFromResponse(Response $response): array
    {
        $data = $response->json('result.status', []);
        return Ropo24StatusData::collect($data);
    }
}

// Usage
$response = $connector->send(new Ropo24StatusUpdatesRequest());
$statuses = $response->dto(); // Returns array of Ropo24StatusData
```

### Relay (After)

```php
<?php declare(strict_types=1);

namespace Support\Relay\Ropo24\Requests;

use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;
use Cline\Relay\Support\Attributes\Methods\Get;

#[Get]
final class Ropo24StatusUpdatesRequest extends Request
{
    public function endpoint(): string
    {
        return '/rest/jobs/statusupdates';
    }

    public function createDtoFromResponse(Response $response): array
    {
        $data = $response->json('result.status', []);
        return Ropo24StatusData::collect($data);
    }
}

// Usage
$response = $connector->send(new Ropo24StatusUpdatesRequest());
$statuses = $response->toDto();      // Returns array of Ropo24StatusData
$statuses = $response->dtoOrFail();  // Throws if response failed
```

**Using Relay's built-in DTO helpers:**

```php
// Map to a single DTO
$invoice = $response->dto(InvoiceData::class);

// Map to a collection of DTOs
$invoices = $response->dtoCollection(InvoiceData::class, 'data.invoices');
```

---

## WithResponse / HasResponse DTO Migration

Saloon's `WithResponse` interface and `HasResponse` trait allow DTOs to carry their original response. Relay doesn't have a direct equivalent - store the response separately if needed.

### Saloon (Before)

```php
<?php declare(strict_types=1);

namespace Support\Saloon\Europa\Casts;

use Saloon\Contracts\DataObjects\WithResponse;
use Saloon\Http\Response;
use Saloon\Traits\Responses\HasResponse;
use Spatie\LaravelData\Data;

final class EuropaCheckVatNumberData extends Data implements WithResponse
{
    use HasResponse;

    public function __construct(
        public readonly string $countryCode,
        public readonly string $vatNumber,
        public readonly bool $valid,
    ) {}

    public static function fromResponse(Response $response): self
    {
        $instance = self::from($response->json());
        $instance->setResponse($response);
        return $instance;
    }
}

// Usage - DTO has access to response
$dto = $response->dto();
$originalResponse = $dto->getResponse();
```

### Relay (After)

```php
<?php declare(strict_types=1);

namespace Support\Relay\Europa\Data;

use Cline\Relay\Core\Response;
use Spatie\LaravelData\Data;

final class EuropaCheckVatNumberData extends Data
{
    public function __construct(
        public readonly string $countryCode,
        public readonly string $vatNumber,
        public readonly bool $valid,
        public readonly ?Response $response = null, // Store if needed
    ) {}

    public static function fromResponse(Response $response): self
    {
        $data = $response->json();
        return new self(
            countryCode: $data['countryCode'],
            vatNumber: $data['vatNumber'],
            valid: $data['valid'],
            response: $response,
        );
    }
}

// Or use request's createDtoFromResponse
#[Post]
#[Json]
final class EuropaCheckVatNumberRequest extends Request
{
    public function createDtoFromResponse(Response $response): EuropaCheckVatNumberData
    {
        return EuropaCheckVatNumberData::fromResponse($response);
    }
}
```

---

## Resilience Features (Retry, Circuit Breaker)

Relay provides resilience attributes not available in base Saloon.

### Relay-Only: Retry Attribute

```php
use Cline\Relay\Support\Attributes\Resilience\Retry;

#[Get]
#[Retry(
    times: 3,
    delay: 1000,           // ms
    multiplier: 2.0,       // exponential backoff
    retryOn: [500, 502, 503, 504],
)]
final class UnstableApiRequest extends Request
{
    public function endpoint(): string
    {
        return '/unstable-endpoint';
    }
}
```

### Relay-Only: Circuit Breaker Attribute

```php
use Cline\Relay\Support\Attributes\Resilience\CircuitBreaker;

#[Get]
#[CircuitBreaker(
    failureThreshold: 5,
    recoveryTime: 30,      // seconds
    sampleWindow: 60,      // seconds
)]
final class ExternalServiceRequest extends Request
{
    public function endpoint(): string
    {
        return '/external-service';
    }
}
```

---

## Concurrent Request Pools

Saloon provides request pooling via the `Pool` class. Relay has a similar but enhanced API.

### Saloon (Before)

```php
use Saloon\Http\Pool;

$connector = new FortnoxApiConnector();

$pool = $connector->pool([
    new GetInvoiceRequest('INV-001'),
    new GetInvoiceRequest('INV-002'),
    new GetInvoiceRequest('INV-003'),
]);

$pool->setConcurrency(5);

$pool->withResponseHandler(function (Response $response, int $index) {
    // Handle successful response
});

$pool->withExceptionHandler(function (Throwable $exception, int $index) {
    // Handle error
});

$responses = $pool->send();
```

### Relay (After)

```php
$connector = new FortnoxApiConnector();

// Create pool with requests
$pool = $connector->pool([
    'inv1' => new GetInvoiceRequest('INV-001'),
    'inv2' => new GetInvoiceRequest('INV-002'),
    'inv3' => new GetInvoiceRequest('INV-003'),
]);

// Configure and send
$responses = $pool
    ->concurrent(5)
    ->onResponse(function (Response $response, Request $request, int|string $key) {
        // Handle successful response
    })
    ->onError(function (RequestException $exception, Request $request, int|string $key) {
        // Handle error
    })
    ->send();

// Access responses by key
$invoice1 = $responses['inv1']->json();
```

**Lazy iteration for memory efficiency:**

```php
// Process responses as they complete (memory efficient)
$pool = $connector->pool($requests)->lazy();

foreach ($pool->iterate() as $key => $response) {
    processInvoice($response->json());
}

// Or use each() helper
$pool->each(function (Response $response, int|string $key) {
    processInvoice($response->json());
});
```

---

## Request Caching

Saloon doesn't have built-in caching. Relay provides attribute-based caching.

### Relay-Only: Request-Level Caching

```php
use Cline\Relay\Support\Attributes\Caching\Cache;
use Cline\Relay\Support\Attributes\Caching\InvalidatesCache;
use Cline\Relay\Support\Attributes\Caching\NoCache;

// Cache GET request for 1 hour
#[Get]
#[Cache(ttl: 3600, tags: ['invoices'])]
final class GetInvoiceRequest extends Request
{
    public function __construct(
        private readonly string $id,
    ) {}

    public function endpoint(): string
    {
        return '/invoices/' . $this->id;
    }
}

// Invalidate cache on mutation
#[Post]
#[Json]
#[InvalidatesCache(tags: ['invoices'])]
final class CreateInvoiceRequest extends Request
{
    // ...
}

// Skip caching for specific request
#[Get]
#[NoCache]
final class GetRealtimeDataRequest extends Request
{
    // ...
}
```

**Connector-level caching:**

```php
use Psr\SimpleCache\CacheInterface;

final class CachedApiConnector extends Connector
{
    public function __construct(
        private readonly CacheInterface $cacheStore,
    ) {}

    public function baseUrl(): string
    {
        return 'https://api.example.com';
    }

    // Enable caching for all requests
    public function cache(): ?CacheInterface
    {
        return $this->cacheStore;
    }

    // Default TTL for cached responses
    public function cacheTtl(): int
    {
        return 300; // 5 minutes
    }

    // Cache key prefix
    public function cacheKeyPrefix(): string
    {
        return 'api_cache_';
    }

    // Which methods to cache
    public function cacheableMethods(): array
    {
        return ['GET', 'HEAD'];
    }
}
```

**Manual cache control:**

```php
// Forget specific cached request
$connector->forgetCache(new GetInvoiceRequest('INV-001'));

// Invalidate by tags
$connector->invalidateCacheTags(['invoices']);

// Flush all cache
$connector->flushCache();
```

---

## Rate Limiting

Saloon has a rate limiting plugin. Relay provides attribute-based rate limiting with more features.

### Saloon (Before - with plugin)

```php
use Saloon\RateLimitPlugin\Contracts\RateLimitStore;
use Saloon\RateLimitPlugin\Limit;
use Saloon\RateLimitPlugin\Traits\HasRateLimits;

final class RateLimitedConnector extends Connector
{
    use HasRateLimits;

    protected function resolveLimits(): array
    {
        return [
            Limit::allow(100)->everyMinute(),
        ];
    }

    protected function resolveRateLimitStore(): RateLimitStore
    {
        return new LaravelCacheStore(cache()->store('redis'));
    }
}
```

### Relay (After)

**Connector-level rate limiting:**

```php
use Cline\Relay\Features\RateLimiting\RateLimitConfig;
use Cline\Relay\Support\Contracts\RateLimitStore;
use Cline\Relay\Features\RateLimiting\LaravelStore;

final class RateLimitedConnector extends Connector
{
    public function baseUrl(): string
    {
        return 'https://api.example.com';
    }

    // Configure rate limit
    public function rateLimit(): ?RateLimitConfig
    {
        return new RateLimitConfig(
            requests: 100,
            perSeconds: 60,
            retry: true,        // Auto-retry when limited
            maxRetries: 3,
            backoff: 'exponential',
        );
    }

    // Use Laravel cache for distributed rate limiting
    public function rateLimitStore(): RateLimitStore
    {
        return new LaravelStore(cache()->store('redis'));
    }
}
```

**Request-level rate limiting (attribute):**

```php
use Cline\Relay\Support\Attributes\RateLimiting\RateLimit;

// Stricter limit for expensive operations
#[Post]
#[Json]
#[RateLimit(
    requests: 10,
    perSeconds: 60,
    key: 'bulk_operations',  // Shared limiter key
    retry: true,
    maxRetries: 3,
    backoff: 'exponential',
)]
final class BulkImportRequest extends Request
{
    public function endpoint(): string
    {
        return '/bulk/import';
    }
}
```

**Reading rate limit info from response:**

```php
$response = $connector->send(new GetDataRequest());

// Get rate limit headers
$rateLimit = $response->rateLimit();

if ($rateLimit) {
    echo "Limit: {$rateLimit->limit}";
    echo "Remaining: {$rateLimit->remaining}";
    echo "Resets at: {$rateLimit->reset}";
}
```

---

## Middleware

Saloon uses Guzzle middleware via `HandlerStack`. Relay provides a cleaner middleware pipeline.

### Saloon (Before - Guzzle middleware)

```php
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;

final class LoggingConnector extends Connector
{
    public function resolveBaseUrl(): string
    {
        return 'https://api.example.com';
    }

    protected function defaultHandlers(): HandlerStack
    {
        $stack = HandlerStack::create();

        $stack->push(Middleware::log(
            logger: app('log'),
            formatter: new MessageFormatter('{method} {uri} - {code}'),
        ));

        return $stack;
    }
}
```

### Relay (After)

**Using built-in middleware:**

```php
use Cline\Relay\Features\Middleware\LoggingMiddleware;
use Cline\Relay\Features\Middleware\TimingMiddleware;
use GuzzleHttp\HandlerStack;

final class LoggingConnector extends Connector
{
    public function baseUrl(): string
    {
        return 'https://api.example.com';
    }

    // Still supports Guzzle HandlerStack for compatibility
    public function middleware(): HandlerStack
    {
        $stack = HandlerStack::create();
        // Add Guzzle middleware here
        return $stack;
    }
}

// Use LoggingMiddleware directly when sending
$logger = app(LoggerInterface::class);
$loggingMiddleware = new LoggingMiddleware(
    logger: $logger,
    logRequestBody: true,
    logResponseBody: true,
);
```

**Creating custom middleware:**

```php
use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;
use Cline\Relay\Support\Contracts\Middleware;
use Closure;

final readonly class AddCorrelationIdMiddleware implements Middleware
{
    public function __construct(
        private string $correlationId,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Modify request before sending
        $request = $request->withHeader('X-Correlation-ID', $this->correlationId);

        // Call next middleware
        $response = $next($request);

        // Optionally modify response
        return $response;
    }
}
```

**Using middleware pipeline:**

```php
use Cline\Relay\Features\Middleware\MiddlewarePipeline;

$pipeline = new MiddlewarePipeline();
$pipeline->push(new LoggingMiddleware($logger));
$pipeline->push(new TimingMiddleware());
$pipeline->push(new AddCorrelationIdMiddleware(Str::uuid()));

// Process request through pipeline
$response = $pipeline->process($request, fn (Request $req) => $connector->send($req));
```

**Closure-based middleware:**

```php
$pipeline = new MiddlewarePipeline();

// Simple closure middleware
$pipeline->push(function (Request $request, Closure $next): Response {
    // Add header
    $request = $request->withHeader('X-Request-Time', now()->toIso8601String());

    $response = $next($request);

    // Log after
    Log::info('Request completed', ['status' => $response->status()]);

    return $response;
});
```

---

## Migration Checklist

- [ ] Replace `Saloon\Http\Connector` with `Cline\Relay\Core\Connector`
- [ ] Replace `Saloon\Http\Request` with `Cline\Relay\Core\Request`
- [ ] Replace `Saloon\Http\BaseResource` with `Cline\Relay\Core\Resource`
- [ ] Replace `resolveEndpoint()` with `endpoint()`
- [ ] Replace `resolveBaseUrl()` with `baseUrl()`
- [ ] Replace `protected Method $method` with HTTP method attributes (`#[Get]`, `#[Post]`, etc.)
- [ ] Replace `use HasJsonBody` with `#[Json]` attribute
- [ ] Replace `use AlwaysThrowOnErrors` with `#[ThrowOnError]` attribute
- [ ] Replace `defaultBody()` with `body()`
- [ ] Replace `defaultQuery()` with `query()`
- [ ] Replace `defaultHeaders()` with `headers()` (for request-level) or keep `defaultHeaders()` (for connector-level)
- [ ] Replace `defaultOauthConfig()` with `oauthConfig()`
- [ ] Replace `boot(PendingRequest)` with `authenticate(Request)`
- [ ] Update OAuth traits import paths
- [ ] Update authentication class imports
- [ ] Update pagination attributes
- [ ] Update test mocking approach
- [ ] Replace `SoloRequest` with connector + request pattern
- [ ] Replace `HasConnector` trait with static factory methods
- [ ] Replace `HasTimeout` trait with `#[Timeout]` attribute
- [ ] Replace `$response->dto()` with `$response->toDto()` or `$response->dtoOrFail()`
- [ ] Update `WithResponse` / `HasResponse` DTOs (store response manually if needed)
- [ ] Consider adding `#[Retry]` and `#[CircuitBreaker]` for resilience
- [ ] Update request pool usage (`pool()->concurrent()->send()`)
- [ ] Consider adding `#[Cache]` attributes for cacheable requests
- [ ] Replace `HasRateLimits` trait with `rateLimit()` method or `#[RateLimit]` attribute
- [ ] Update Guzzle middleware to Relay middleware if needed

<a id="doc-docs-readme"></a>

Relay is a powerful PHP 8.4+ attribute-driven HTTP client for building elegant API SDKs. This guide covers installation, configuration, and creating your first API connector.

## Installation

Install via Composer:

```bash
composer require cline/relay
```

## Requirements

- PHP 8.4+
- Laravel 11+ (optional, for Laravel integration)

## Laravel Integration

If using Laravel, Relay auto-registers its service provider. Optionally publish the configuration:

```bash
php artisan vendor:publish --tag=relay-config
```

## Quick Start with Generators

The fastest way to create an API integration:

```bash
# Create a complete GitHub integration with OAuth
php artisan make:integration GitHub --oauth --resources=Users,Repositories

# Or build piece by piece
php artisan make:connector GitHub --bearer
php artisan make:resource Users GitHub --crud --requests
php artisan make:request GetRepository GitHub --method=get
```

See **[Generators](generators)** for all available options.

## Core Concepts

Relay uses three main building blocks:

1. **Connector** - Represents an API service (e.g., GitHub, Stripe, Twilio)
2. **Request** - Represents a single API endpoint call
3. **Response** - Wraps the HTTP response with typed accessors

## Your First Connector

Create a connector for the JSONPlaceholder API:

```php
<?php

namespace App\Http\Connectors;

use Cline\Relay\Core\Connector;

class JsonPlaceholderConnector extends Connector
{
    public function baseUrl(): string
    {
        return 'https://jsonplaceholder.typicode.com';
    }
}
```

## Your First Request

Create a request to fetch posts:

```php
<?php

namespace App\Http\Requests;

use Cline\Relay\Support\Attributes\Methods\Get;
use Cline\Relay\Core\Request;

#[Get]
class GetPostsRequest extends Request
{
    public function endpoint(): string
    {
        return '/posts';
    }
}
```

## Sending a Request

```php
use App\Http\Connectors\JsonPlaceholderConnector;
use App\Http\Requests\GetPostsRequest;

$connector = new JsonPlaceholderConnector();
$response = $connector->send(new GetPostsRequest());

// Get the response as an array
$posts = $response->json();

// Get specific key
$firstPostTitle = $response->json('0.title');

// Get as collection
$posts = $response->collect();
```

## Requests with Parameters

```php
<?php

namespace App\Http\Requests;

use Cline\Relay\Support\Attributes\Methods\Get;
use Cline\Relay\Core\Request;

#[Get]
class GetPostRequest extends Request
{
    public function __construct(
        private readonly int $postId,
    ) {}

    public function endpoint(): string
    {
        return "/posts/{$this->postId}";
    }
}
```

Usage:

```php
$response = $connector->send(new GetPostRequest(1));
$post = $response->json();
```

## POST Requests with Body

```php
<?php

namespace App\Http\Requests;

use Cline\Relay\Support\Attributes\ContentTypes\Json;
use Cline\Relay\Support\Attributes\Methods\Post;
use Cline\Relay\Core\Request;

#[Post]
#[Json]
class CreatePostRequest extends Request
{
    public function __construct(
        private readonly string $title,
        private readonly string $body,
        private readonly int $userId,
    ) {}

    public function endpoint(): string
    {
        return '/posts';
    }

    public function body(): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'userId' => $this->userId,
        ];
    }
}
```

## Query Parameters

```php
<?php

namespace App\Http\Requests;

use Cline\Relay\Support\Attributes\Methods\Get;
use Cline\Relay\Core\Request;

#[Get]
class GetPostsRequest extends Request
{
    public function __construct(
        private readonly ?int $userId = null,
        private readonly int $limit = 10,
    ) {}

    public function endpoint(): string
    {
        return '/posts';
    }

    public function query(): array
    {
        return array_filter([
            '_limit' => $this->limit,
            'userId' => $this->userId,
        ]);
    }
}
```

## Response Handling

```php
$response = $connector->send(new GetPostsRequest());

// Check status
if ($response->ok()) {
    // 2xx response
}

if ($response->failed()) {
    // 4xx or 5xx response
}

// Get data in different formats
$array = $response->json();           // As array
$object = $response->object();        // As stdClass
$collection = $response->collect();   // As Laravel Collection

// Get specific keys with dot notation
$title = $response->json('data.title');

// Get headers
$contentType = $response->header('Content-Type');
$allHeaders = $response->headers();

// Get status code
$status = $response->status();
```

## Error Handling

```php
use Cline\Relay\Support\Exceptions\RequestException;

try {
    $response = $connector->send(new GetPostRequest(9999));
} catch (RequestException $e) {
    echo "Request failed: " . $e->getMessage();
    echo "Status: " . $e->status();
    echo "Response: " . $e->response()?->body();
}

// Or throw on errors explicitly
$response = $connector->send(new GetPostRequest(1));
$response->throw(); // Throws if response is 4xx or 5xx
```

## Convenience Methods

```php
// GET request
$response = $connector->get('/posts', ['_limit' => 5]);

// POST request
$response = $connector->post('/posts', [
    'title' => 'New Post',
    'body' => 'Content here',
    'userId' => 1,
]);

// PUT request
$response = $connector->put('/posts/1', ['title' => 'Updated Title']);

// PATCH request
$response = $connector->patch('/posts/1', ['title' => 'Patched Title']);

// DELETE request
$response = $connector->delete('/posts/1');
```

## Adding Authentication

```php
<?php

namespace App\Http\Connectors;

use Cline\Relay\Features\Auth\BearerToken;
use Cline\Relay\Core\Connector;
use Cline\Relay\Core\Request;

class GitHubConnector extends Connector
{
    public function __construct(
        private readonly string $token,
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
            'X-GitHub-Api-Version' => '2022-11-28',
        ];
    }
}
```

## Mapping to DTOs

```php
<?php

namespace App\DTOs;

class Post
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $body,
        public readonly int $userId,
    ) {}
}
```

```php
// Map single response to DTO
$post = $response->dto(Post::class);

// Map collection to DTOs
$posts = $response->dtoCollection(Post::class);

// Map nested data
$posts = $response->dtoCollection(Post::class, 'data.posts');
```

## Next Steps

- **[Generators](generators)** - Scaffold integrations quickly
- **[Connectors](connectors)** - Connector configuration
- **[Requests](requests)** - Request options
- **[Responses](responses)** - Response handling and transformation
- **[Authentication](authentication)** - Authentication strategies
- **[Attributes](attributes)** - All available attributes
- **[Middleware](middleware)** - Request/response pipeline
- **[Caching](caching)** - Cache responses
- **[Rate Limiting](rate-limiting)** - Handle API rate limits
- **[Resilience](resilience)** - Retries and circuit breakers
- **[Pagination](pagination)** - Paginated API responses
- **[Pooling](pooling)** - Concurrent requests
- **[Testing](testing)** - Mock connectors for testing
- **[Debugging](debugging)** - Debug tools and dumpers
- **[Advanced Usage](advanced-usage)** - DTOs, GraphQL, streaming, and more

<a id="doc-docs-advanced-usage"></a>

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

<a id="doc-docs-attributes"></a>

Relay uses PHP 8 attributes to configure requests declaratively. This guide covers all available attributes.

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
class GetUsersRequest extends Request {}

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

### Form

```php
use Cline\Relay\Support\Attributes\ContentTypes\Form;

#[Post]
#[Form]
class LoginRequest extends Request {}
```

### Multipart

```php
use Cline\Relay\Support\Attributes\ContentTypes\Multipart;

#[Post]
#[Multipart]
class UploadRequest extends Request {}
```

### XML

```php
use Cline\Relay\Support\Attributes\ContentTypes\Xml;

#[Post]
#[Xml]
class CreateOrderRequest extends Request {}
```

### YAML

```php
use Cline\Relay\Support\Attributes\ContentTypes\Yaml;

#[Post]
#[Yaml]
class CreateConfigRequest extends Request {}

#[Yaml('text/yaml')]
class CreateConfigRequest extends Request {}
```

## Protocol Attributes

### GraphQL

```php
use Cline\Relay\Support\Attributes\Protocols\GraphQL;

#[GraphQL]
class GetUserQuery extends Request
{
    public function body(): array
    {
        return [
            'query' => '...',
            'variables' => ['id' => $this->userId],
        ];
    }
}
```

### JSON-RPC

```php
use Cline\Relay\Support\Attributes\Protocols\JsonRpc;

#[JsonRpc]
class CallMethodRequest extends Request {}

#[JsonRpc(version: '1.0')]
class LegacyCallRequest extends Request {}
```

### SOAP

```php
use Cline\Relay\Support\Attributes\Protocols\Soap;

#[Soap]
class SoapRequest extends Request {}

#[Soap(version: '1.2')]
class Soap12Request extends Request {}
```

### XML-RPC

```php
use Cline\Relay\Support\Attributes\Protocols\XmlRpc;

#[XmlRpc]
class XmlRpcRequest extends Request {}
```

## Error Handling Attributes

### ThrowOnError

```php
use Cline\Relay\Support\Attributes\ThrowOnError;

#[Get]
#[ThrowOnError]
class GetUserRequest extends Request {}

#[ThrowOnError(clientErrors: true, serverErrors: false)]
class GetUserRequest extends Request {}

#[ThrowOnError(clientErrors: false, serverErrors: true)]
class GetUserRequest extends Request {}

// Also works on connectors
#[ThrowOnError]
class StrictConnector extends Connector {}
```

## Caching Attributes

### Cache

```php
use Cline\Relay\Support\Attributes\Caching\Cache;

#[Get]
#[Cache(ttl: 3600)]
class GetUsersRequest extends Request {}

#[Cache(ttl: 3600, tags: ['users', 'list'])]
class GetUsersRequest extends Request {}
```

### NoCache

```php
use Cline\Relay\Support\Attributes\Caching\NoCache;

#[Get]
#[NoCache]
class GetCurrentUserRequest extends Request {}
```

### InvalidatesCache

```php
use Cline\Relay\Support\Attributes\Caching\InvalidatesCache;

#[Post]
#[InvalidatesCache(tags: ['users', 'list'])]
class CreateUserRequest extends Request {}
```

## Rate Limiting Attributes

### RateLimit

```php
use Cline\Relay\Support\Attributes\RateLimiting\RateLimit;

#[Get]
#[RateLimit(maxAttempts: 10, decaySeconds: 60)]
class SearchRequest extends Request {}
```

### ConcurrencyLimit

```php
use Cline\Relay\Support\Attributes\RateLimiting\ConcurrencyLimit;

#[Get]
#[ConcurrencyLimit(max: 5)]
class HeavyRequest extends Request {}
```

## Resilience Attributes

### Retry

```php
use Cline\Relay\Support\Attributes\Resilience\Retry;

#[Get]
#[Retry(times: 3)]
class GetDataRequest extends Request {}

#[Retry(times: 3, delay: 100)]
class GetDataRequest extends Request {}

#[Retry(times: 3, delay: 100, multiplier: 2.0, maxDelay: 30000)]
class GetDataRequest extends Request {}

#[Retry(times: 3, when: [429, 503])]
class GetDataRequest extends Request {}

#[Retry(times: 3, callback: 'shouldRetry')]
class GetDataRequest extends Request
{
    public function shouldRetry(Response $response): bool
    {
        return $response->status() >= 500;
    }
}
```

### Timeout

```php
use Cline\Relay\Support\Attributes\Resilience\Timeout;

#[Get]
#[Timeout(seconds: 5)]
class QuickRequest extends Request {}

#[Timeout(seconds: 30, connectSeconds: 5)]
class SlowRequest extends Request {}
```

### CircuitBreaker

```php
use Cline\Relay\Support\Attributes\Resilience\CircuitBreaker;

#[Get]
#[CircuitBreaker(
    failureThreshold: 5,
    resetTimeout: 30,
    successThreshold: 2,
)]
class UnreliableApiRequest extends Request {}
```

## Pagination Attributes

### Page-Based

```php
use Cline\Relay\Support\Attributes\Pagination\Pagination;

#[Get]
#[Pagination(
    page: 'page',
    perPage: 'per_page',
    dataKey: 'data',
    totalPagesKey: 'meta.last_page',
    totalKey: 'meta.total',
)]
class GetUsersRequest extends Request {}
```

### Simple Pagination

```php
use Cline\Relay\Support\Attributes\Pagination\SimplePagination;

#[Get]
#[SimplePagination(
    page: 'page',
    perPage: 'per_page',
    dataKey: 'data',
    hasMoreKey: 'meta.has_more',
)]
class GetPostsRequest extends Request {}
```

### Cursor Pagination

```php
use Cline\Relay\Support\Attributes\Pagination\CursorPagination;

#[Get]
#[CursorPagination(
    cursor: 'cursor',
    perPage: 'per_page',
    nextKey: 'meta.next_cursor',
    dataKey: 'data',
)]
class GetFeedRequest extends Request {}
```

### Offset Pagination

```php
use Cline\Relay\Support\Attributes\Pagination\OffsetPagination;

#[Get]
#[OffsetPagination(
    offset: 'offset',
    limit: 'limit',
    dataKey: 'data',
    totalKey: 'meta.total',
)]
class SearchRequest extends Request {}
```

### Link Pagination

```php
use Cline\Relay\Support\Attributes\Pagination\LinkPagination;

#[Get]
#[LinkPagination]
class GetRepositoriesRequest extends Request {}
```

## Network Attributes

### Proxy

```php
use Cline\Relay\Support\Attributes\Network\Proxy;

#[Get]
#[Proxy(
    http: 'http://proxy.example.com:8080',
    https: 'https://proxy.example.com:8080',
)]
class ProxiedRequest extends Request {}

#[Proxy(http: 'http://user:pass@proxy.example.com:8080')]
class AuthenticatedProxyRequest extends Request {}
```

### SSL

```php
use Cline\Relay\Support\Attributes\Network\Ssl;

#[Get]
#[Ssl(verify: false)]
class InsecureRequest extends Request {}

#[Ssl(verify: '/path/to/ca-bundle.crt')]
class CustomCertRequest extends Request {}
```

### ForceIpResolve

```php
use Cline\Relay\Support\Attributes\Network\ForceIpResolve;

#[Get]
#[ForceIpResolve(version: 'v4')]
class IPv4OnlyRequest extends Request {}

#[Get]
#[ForceIpResolve(version: 'v6')]
class IPv6OnlyRequest extends Request {}
```

## Idempotency Attribute

```php
use Cline\Relay\Support\Attributes\Idempotent;

#[Post]
#[Idempotent]
class CreatePaymentRequest extends Request {}

#[Idempotent(header: 'X-Request-ID')]
class CreateOrderRequest extends Request {}

#[Idempotent(keyMethod: 'generateKey')]
class CreateOrderRequest extends Request
{
    public function generateKey(): string
    {
        return hash('sha256', $this->orderId);
    }
}
```

## Stream Attribute

```php
use Cline\Relay\Support\Attributes\Stream;

#[Get]
#[Stream]
class DownloadFileRequest extends Request {}

#[Stream(bufferSize: 16384)]
class LargeDownloadRequest extends Request {}
```

## DTO Mapping Attribute

```php
use Cline\Relay\Support\Attributes\Dto;

#[Get]
#[Dto(User::class)]
class GetUserRequest extends Request {}

#[Dto(User::class, dataKey: 'data.user')]
class GetUserRequest extends Request {}
```

## Combining Attributes

```php
use Cline\Relay\Support\Attributes\Caching\Cache;
use Cline\Relay\Support\Attributes\ContentTypes\Json;
use Cline\Relay\Support\Attributes\Methods\Post;
use Cline\Relay\Support\Attributes\Resilience\Retry;
use Cline\Relay\Support\Attributes\Resilience\Timeout;
use Cline\Relay\Support\Attributes\ThrowOnError;

#[Post]
#[Json]
#[Timeout(seconds: 10)]
#[Retry(times: 3, sleepMs: 500, when: [429, 503])]
#[ThrowOnError]
class CreatePaymentRequest extends Request
{
    public function __construct(
        private readonly string $customerId,
        private readonly int $amount,
    ) {}

    public function endpoint(): string
    {
        return '/payments';
    }

    public function body(): array
    {
        return [
            'customer_id' => $this->customerId,
            'amount' => $this->amount,
        ];
    }
}
```

## Accessing Attributes Programmatically

```php
$request = new CreatePaymentRequest('cust_123', 1000);

if ($request->hasAttribute(Retry::class)) {
    // Has retry configured
}

$retry = $request->getAttribute(Retry::class);
if ($retry) {
    echo $retry->times;
    echo $retry->sleepMs;
}

$method = $request->method();
$contentType = $request->contentType();
$isIdempotent = $request->isIdempotent();
```

<a id="doc-docs-authentication"></a>

Relay provides multiple authentication strategies for different API requirements. All authenticators implement the `Authenticator` contract.

## Using Authentication

Implement the `authenticate()` method in your connector:

```php
use Cline\Relay\Features\Auth\BearerToken;
use Cline\Relay\Core\Connector;
use Cline\Relay\Core\Request;

class GitHubConnector extends Connector
{
    public function __construct(
        private readonly string $token,
    ) {}

    public function baseUrl(): string
    {
        return 'https://api.github.com';
    }

    public function authenticate(Request $request): Request
    {
        return (new BearerToken($this->token))->authenticate($request);
    }
}
```

## Bearer Token

```php
use Cline\Relay\Features\Auth\BearerToken;

public function authenticate(Request $request): Request
{
    return (new BearerToken($this->token))->authenticate($request);
}

// Adds: Authorization: Bearer <token>
```

## Basic Authentication

```php
use Cline\Relay\Features\Auth\BasicAuth;

public function authenticate(Request $request): Request
{
    return (new BasicAuth(
        username: $this->username,
        password: $this->password,
    ))->authenticate($request);
}

// Adds: Authorization: Basic base64(username:password)
```

## API Key Authentication

### In Header

```php
use Cline\Relay\Features\Auth\ApiKeyAuth;

public function authenticate(Request $request): Request
{
    return ApiKeyAuth::inHeader(
        key: $this->apiKey,
        headerName: 'X-API-Key',
    )->authenticate($request);
}

// Adds: X-API-Key: <your-api-key>
```

### In Query Parameter

```php
public function authenticate(Request $request): Request
{
    return ApiKeyAuth::inQuery(
        key: $this->apiKey,
        paramName: 'api_key',
    )->authenticate($request);
}

// Adds: ?api_key=<your-api-key>
```

## Header Authentication

```php
use Cline\Relay\Features\Auth\HeaderAuth;

public function authenticate(Request $request): Request
{
    return (new HeaderAuth(
        header: 'X-Auth-Token',
        value: $this->token,
    ))->authenticate($request);
}
```

## Query Authentication

```php
use Cline\Relay\Features\Auth\QueryAuth;

public function authenticate(Request $request): Request
{
    return (new QueryAuth(
        parameter: 'access_token',
        value: $this->token,
    ))->authenticate($request);
}
```

## JWT Authentication

### Static Token

```php
use Cline\Relay\Features\Auth\JwtAuth;

public function authenticate(Request $request): Request
{
    return JwtAuth::token(
        token: $this->jwtToken,
        expiresAt: $this->tokenExpiresAt,
    )->authenticate($request);
}
```

### Dynamic Token Provider

```php
public function authenticate(Request $request): Request
{
    return JwtAuth::withProvider(function () {
        if ($this->isTokenExpired()) {
            $this->refreshToken();
        }
        return $this->currentToken;
    })->authenticate($request);
}
```

### Token Expiry Checking

```php
$jwt = JwtAuth::token($token, $expiresAt);

if ($jwt->hasExpired()) {
    // Token has expired
}

if ($jwt->isValid()) {
    // Token is still valid
}

$expiresAt = $jwt->getExpiresAt();
$jwt->setToken($newToken, $newExpiresAt);
```

## Digest Authentication

```php
use Cline\Relay\Features\Auth\DigestAuth;

class MyConnector extends Connector
{
    private DigestAuth $digestAuth;

    public function __construct(string $username, string $password)
    {
        $this->digestAuth = new DigestAuth($username, $password);
    }

    public function defaultConfig(): array
    {
        return [
            'auth' => $this->digestAuth->toGuzzleAuth(),
        ];
    }
}
```

## Callable Authentication

```php
use Cline\Relay\Features\Auth\CallableAuth;

public function authenticate(Request $request): Request
{
    return (new CallableAuth(function (Request $request) {
        $signature = $this->generateSignature($request);

        return $request
            ->withHeader('X-Signature', $signature)
            ->withHeader('X-Timestamp', (string) time());
    }))->authenticate($request);
}
```

## Access Token Authenticator

```php
use Cline\Relay\Features\Auth\AccessTokenAuthenticator;
use DateTimeImmutable;

$auth = new AccessTokenAuthenticator(
    accessToken: 'your-access-token',
    refreshToken: 'your-refresh-token',
    expiresAt: new DateTimeImmutable('+1 hour'),
);

public function authenticate(Request $request): Request
{
    return $this->auth->authenticate($request);
}
```

### Token Information

```php
$accessToken = $auth->getAccessToken();
$refreshToken = $auth->getRefreshToken();
$expiresAt = $auth->getExpiresAt();

if ($auth->hasExpired()) { /* Token expired */ }
if ($auth->hasNotExpired()) { /* Token valid */ }
if ($auth->isRefreshable()) { /* Has refresh token */ }
```

### Serialization

```php
$serialized = $auth->serialize();
cache()->put('oauth_token', $serialized, 3600);

$serialized = cache()->get('oauth_token');
$auth = AccessTokenAuthenticator::unserialize($serialized);
```

## Auto-Refresh Authenticator

```php
use Cline\Relay\Features\Auth\AutoRefreshAuthenticator;
use Cline\Relay\Features\OAuth2\AuthorizationCodeGrant;

class MyConnector extends Connector
{
    use AuthorizationCodeGrant;

    private AutoRefreshAuthenticator $auth;

    public function setAuthenticator(OAuthAuthenticator $authenticator): void
    {
        $this->auth = new AutoRefreshAuthenticator(
            connector: $this,
            authenticator: $authenticator,
            onRefresh: fn (OAuthAuthenticator $new) => $this->saveToken($new),
        );
    }

    public function authenticate(Request $request): Request
    {
        return $this->auth->authenticate($request);
    }
}
```

## Request-Level Authentication

```php
$request = (new GetUserRequest(1))
    ->withBearerToken('token')
    ->withBasicAuth('user', 'pass');

$response = $connector->send(
    (new GetUserRequest(1))->withHeader('Authorization', 'Custom xyz')
);
```

## Multiple Authentication Methods

```php
public function authenticate(Request $request): Request
{
    $request = (new BearerToken($this->token))->authenticate($request);
    $request = (new HeaderAuth('X-Api-Key', $this->apiKey))->authenticate($request);

    return $request;
}
```

## Custom Authenticator

```php
<?php

namespace App\Auth;

use Cline\Relay\Support\Contracts\Authenticator;
use Cline\Relay\Core\Request;

class HmacAuth implements Authenticator
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $secretKey,
    ) {}

    public function authenticate(Request $request): Request
    {
        $timestamp = time();
        $signature = $this->generateSignature($request, $timestamp);

        return $request
            ->withHeader('X-Api-Key', $this->apiKey)
            ->withHeader('X-Timestamp', (string) $timestamp)
            ->withHeader('X-Signature', $signature);
    }

    private function generateSignature(Request $request, int $timestamp): string
    {
        $payload = implode("\n", [
            $request->method(),
            $request->endpoint(),
            $timestamp,
        ]);

        return hash_hmac('sha256', $payload, $this->secretKey);
    }
}
```

## Full Example

```php
<?php

namespace App\Http\Connectors;

use Cline\Relay\Features\Auth\ApiKeyAuth;
use Cline\Relay\Features\Auth\AutoRefreshAuthenticator;
use Cline\Relay\Core\Connector;
use Cline\Relay\Support\Contracts\OAuthAuthenticator;
use Cline\Relay\Features\OAuth2\ClientCredentialsGrant;
use Cline\Relay\Features\OAuth2\OAuthConfig;
use Cline\Relay\Core\Request;

class MultiAuthConnector extends Connector
{
    use ClientCredentialsGrant;

    private ?AutoRefreshAuthenticator $auth = null;

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $apiKey,
    ) {}

    public function baseUrl(): string
    {
        return 'https://api.example.com/v1';
    }

    public function oauthConfig(): OAuthConfig
    {
        return new OAuthConfig(
            clientId: $this->clientId,
            clientSecret: $this->clientSecret,
            tokenEndpoint: '/oauth/token',
        );
    }

    public function authenticate(Request $request): Request
    {
        if ($this->auth === null) {
            $authenticator = $this->getAccessToken();
            $this->auth = new AutoRefreshAuthenticator(
                connector: $this,
                authenticator: $authenticator,
                onRefresh: fn (OAuthAuthenticator $new) => $this->saveToken($new),
            );
        }

        $request = $this->auth->authenticate($request);
        $request = ApiKeyAuth::inHeader($this->apiKey)->authenticate($request);

        return $request;
    }

    private function saveToken(OAuthAuthenticator $auth): void
    {
        cache()->put('oauth_token', $auth->serialize(), 3600);
    }
}
```

<a id="doc-docs-caching"></a>

Relay supports response caching to reduce API calls and improve performance.

## Overview

Caching in Relay:
- Uses PSR-16 Simple Cache interface
- Supports any Laravel cache store (Redis, Memcached, File, etc.)
- Allows per-request TTL and cache key customization
- Supports cache tags for selective invalidation
- Only caches successful responses (2xx status codes)

## Enabling Caching

```php
use Cline\Relay\Features\Caching\CacheConfig;
use Cline\Relay\Core\Connector;
use Psr\SimpleCache\CacheInterface;

class GitHubConnector extends Connector
{
    public function baseUrl(): string
    {
        return 'https://api.github.com';
    }

    public function cache(): ?CacheInterface
    {
        return app('cache')->store('redis');
    }

    public function cacheTtl(): int
    {
        return 300; // 5 minutes default
    }

    public function cacheKeyPrefix(): string
    {
        return 'github_api';
    }

    public function cacheableMethods(): array
    {
        return ['GET', 'HEAD'];
    }
}
```

## Cache Configuration

```php
use Cline\Relay\Features\Caching\CacheConfig;

public function cacheConfig(): ?CacheConfig
{
    return new CacheConfig(
        store: app('cache')->store('redis'),
        hashAlgorithm: 'md5',
        maxKeyLength: null,
        includeHeaders: false,
        prefix: 'api_cache',
        defaultTtl: 3600,
        cacheableMethods: ['GET', 'HEAD'],
    );
}
```

## Request-Level Caching

### Enable Cache

```php
use Cline\Relay\Support\Attributes\Caching\Cache;
use Cline\Relay\Support\Attributes\Methods\Get;

#[Get]
#[Cache(ttl: 3600)]
class GetUserRequest extends Request {}
```

### Cache with Tags

```php
#[Get]
#[Cache(ttl: 3600, tags: ['users', 'profile'])]
class GetUserProfileRequest extends Request {}
```

### Disable Cache

```php
use Cline\Relay\Support\Attributes\Caching\NoCache;

#[Get]
#[NoCache]
class GetCurrentStatusRequest extends Request {}
```

### Invalidate Cache

```php
use Cline\Relay\Support\Attributes\Caching\InvalidatesCache;
use Cline\Relay\Support\Attributes\Methods\Post;

#[Post]
#[InvalidatesCache(tags: ['users'])]
class CreateUserRequest extends Request {}
```

## Custom Cache Keys

```php
#[Cache(keyResolver: 'cacheKey')]
class GetUserRequest extends Request
{
    public function cacheKey(): string
    {
        return "user_{$this->userId}";
    }
}
```

### Cache Key Resolver Class

```php
use Cline\Relay\Support\Contracts\CacheKeyResolver;
use Cline\Relay\Core\Request;

class UserCacheKeyResolver implements CacheKeyResolver
{
    public function resolve(Request $request): string
    {
        $userId = $request->userId ?? 'anonymous';
        $endpoint = $request->endpoint();

        return "user:{$userId}:{$endpoint}";
    }
}

#[Cache(ttl: 3600, keyResolver: UserCacheKeyResolver::class)]
class GetUserDashboardRequest extends Request {}
```

## Checking Cache Status

```php
$response = $connector->send(new GetUsersRequest());

if ($response->fromCache()) {
    // Response was served from cache
}
```

## Cache Management

```php
// Forget specific request
$request = new GetUserRequest(123);
$connector->forgetCache($request);

// Flush all cache
$connector->flushCache();

// Invalidate by tags
$connector->invalidateCacheTags(['users', 'profile']);
```

## Conditional Caching

```php
public function cacheConfig(): ?CacheConfig
{
    return new CacheConfig(
        ttl: 3600,
        shouldCache: fn (Response $response) => $response->ok(),
    );
}

// Based on content
public function cacheConfig(): ?CacheConfig
{
    return new CacheConfig(
        ttl: 3600,
        shouldCache: fn (Response $response) =>
            $response->ok() && !$response->json('is_dynamic'),
    );
}
```

## Conditional Requests

```php
$response = $connector->send(new GetUserRequest(1));

$etag = $response->etag();
$lastModified = $response->lastModified();

if ($response->wasNotModified()) {
    // Use cached version
}
```

## Cache Stores

```php
// Redis (Recommended)
public function cache(): ?CacheInterface
{
    return app('cache')->store('redis');
}

// Memcached
public function cache(): ?CacheInterface
{
    return app('cache')->store('memcached');
}

// File
public function cache(): ?CacheInterface
{
    return app('cache')->store('file');
}

// Array (In-Memory, for testing)
public function cache(): ?CacheInterface
{
    return app('cache')->store('array');
}
```

## Best Practices

### Choose Appropriate TTLs

```php
// Static data - long TTL
#[Cache(ttl: 86400)]
class GetCountriesRequest extends Request {}

// User data - medium TTL
#[Cache(ttl: 3600)]
class GetUserRequest extends Request {}

// Frequently changing - short TTL
#[Cache(ttl: 60)]
class GetPricesRequest extends Request {}
```

### Use Tags for Related Data

```php
#[Cache(ttl: 3600, tags: ['users'])]
class GetUsersRequest extends Request {}

#[Cache(ttl: 3600, tags: ['users', 'user-profile'])]
class GetUserProfileRequest extends Request {}

#[Post]
#[InvalidatesCache(tags: ['users'])]
class UpdateUserRequest extends Request {}
```

### Don't Cache Sensitive Data

```php
#[NoCache]
class GetAccessTokenRequest extends Request {}

#[NoCache]
class GetPaymentDetailsRequest extends Request {}
```

## Full Example

```php
<?php

namespace App\Http\Connectors;

use Cline\Relay\Features\Caching\CacheConfig;
use Cline\Relay\Core\Connector;
use Cline\Relay\Core\Response;
use Psr\SimpleCache\CacheInterface;

class CachedApiConnector extends Connector
{
    public function baseUrl(): string
    {
        return 'https://api.example.com/v1';
    }

    public function cache(): ?CacheInterface
    {
        return app('cache')->store('redis');
    }

    public function cacheConfig(): ?CacheConfig
    {
        return new CacheConfig(
            ttl: 1800,
            prefix: 'api_v1',
            shouldCache: fn (Response $response) =>
                $response->ok() &&
                !$response->header('X-No-Cache'),
        );
    }

    public function cacheableMethods(): array
    {
        return ['GET', 'HEAD'];
    }
}
```

Usage:

```php
$connector = new CachedApiConnector();

// First call - hits API, caches response
$response1 = $connector->send(new GetProductsRequest());
echo $response1->fromCache(); // false

// Second call - returns cached response
$response2 = $connector->send(new GetProductsRequest());
echo $response2->fromCache(); // true

// Create product - invalidates cache
$connector->send(new CreateProductRequest(['name' => 'New Product']));

// Third call - hits API again
$response3 = $connector->send(new GetProductsRequest());
echo $response3->fromCache(); // false
```

<a id="doc-docs-connectors"></a>

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

<a id="doc-docs-debugging"></a>

Relay provides comprehensive debugging tools to help you understand, troubleshoot, and diagnose issues with your API integrations.

## Overview

Debugging features in Relay:
- Built-in `debug()`, `debugRequest()`, `debugResponse()` methods
- `Wiretap` for debugging all requests across connectors
- `Debugger` for formatted request/response output
- `CurlDumper` to convert requests to curl commands
- `HurlDumper` to convert requests to Hurl format
- `LoggingMiddleware` for request/response logging
- Sensitive data redaction

## Quick Start

### Connector-Level Debugging

```php
// Debug both request and response
$connector->debug()->send($request);

// Debug only the request
$connector->debugRequest()->send($request);

// Debug only the response
$connector->debugResponse()->send($request);

// Debug and terminate after response
$connector->debug(die: true)->send($request);
```

### Request-Level Debugging

```php
// Debug a specific request (takes precedence over connector)
$connector->send($request->debug());

// Debug only this request's response
$connector->send($request->debugResponse());
```

### Global Debugging

Debug all requests across all connectors:

```php
use Cline\Relay\Observability\Debugging\Wiretap;

// Debug all requests globally
Wiretap::enable();

// Stop debugging
Wiretap::disable();
```

## Custom Debug Handlers

### With Ray

```php
use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

// Debug requests with Ray
$connector->debugRequest(function (Request $request, RequestInterface $psrRequest) {
    ray($psrRequest);
});

// Debug responses with Ray
$connector->debugResponse(function (Response $response, ResponseInterface $psrResponse) {
    ray($psrResponse);
});
```

### Global Custom Handlers

```php
use Cline\Relay\Observability\Debugging\Wiretap;

// Debug all requests with Ray
Wiretap::requests(function (Request $request, RequestInterface $psrRequest) {
    ray('Request', [
        'method' => $psrRequest->getMethod(),
        'uri' => (string) $psrRequest->getUri(),
        'headers' => $psrRequest->getHeaders(),
    ]);
});

// Debug all responses with Ray
Wiretap::responses(function (Response $response, ResponseInterface $psrResponse) {
    ray('Response', [
        'status' => $response->status(),
        'body' => $response->json(),
        'duration' => $response->duration(),
    ]);
});
```

## Debugger Class

### Formatting Requests

```php
use Cline\Relay\Observability\Debugging\Debugger;

$debugger = new Debugger();
$request = new CreateUserRequest('john@example.com', 'John Doe');

echo $debugger->formatRequest($request, 'https://api.example.com');
```

Output:
```
┌─ Request ─────────────────────────────────────
│ POST https://api.example.com/users
│ Query: page=1&limit=10
│
│ Headers:
│   Content-Type: application/json
│   Authorization: ***REDACTED***
│
│ Body:
│   {
│       "email": "john@example.com",
│       "name": "John Doe"
│   }
└───────────────────────────────────────────────
```

### Formatting Responses

```php
$response = $connector->send(new GetUserRequest(1));

echo $debugger->formatResponse($response);
```

### Sensitive Data Redaction

Debugger automatically redacts sensitive information:

```php
$debugger = new Debugger();

// Default sensitive headers: Authorization, X-API-Key, Cookie, Set-Cookie
// Default sensitive body keys: password, secret, token, api_key, access_token, credit_card, cvv, ssn
```

### Custom Sensitive Fields

```php
$debugger = new Debugger();

$debugger->setSensitiveHeaders([
    'Authorization',
    'X-Custom-Secret',
]);

$debugger->setSensitiveBodyKeys([
    'password',
    'pin',
    'social_security',
]);
```

## CurlDumper

Convert requests to curl commands for testing in terminal.

```php
use Cline\Relay\Observability\Debugging\CurlDumper;

$dumper = new CurlDumper();
$curlCommand = $dumper->dump($request, 'https://api.example.com');

echo $curlCommand;
// curl -H 'Accept: application/json' 'https://api.example.com/users/1'
```

### CurlDumper Options

```php
$curlCommand = (new CurlDumper())
    ->verbose()
    ->compressed()
    ->insecure()
    ->followRedirects()
    ->timeout(60)
    ->dump($request, 'https://api.example.com');
```

## HurlDumper

Convert requests to [Hurl](https://hurl.dev/) format for testing.

```php
use Cline\Relay\Observability\Debugging\HurlDumper;

$dumper = new HurlDumper();
$hurlContent = $dumper->dump($request, 'https://api.example.com');
```

## LoggingMiddleware

Log all requests and responses using PSR-3 loggers.

```php
use Cline\Relay\Features\Middleware\LoggingMiddleware;

class MyConnector extends Connector
{
    public function middleware(): array
    {
        return [
            new LoggingMiddleware(
                logger: $this->logger,
                logRequestBody: config('app.debug'),
                logResponseBody: config('app.debug'),
            ),
        ];
    }
}
```

## Best Practices

1. **Never log sensitive data in production** - Use environment checks
2. **Use Debugger redaction** - Configure sensitive headers and body keys
3. **Use Wiretap for development** - Enable globally in local environment
4. **Export to curl for manual testing** - Debug external issues

<a id="doc-docs-generators"></a>

Relay provides powerful Artisan commands to scaffold API integrations quickly.

## Quick Start

```bash
# Create a complete integration
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
| `--resources=` | Comma-separated resources |
| `--graphql` | GraphQL integration |
| `--jsonrpc` | JSON-RPC integration |

### Examples

```bash
php artisan make:integration Stripe --oauth --cache
php artisan make:integration Twitter --bearer --rate-limit --resources=Users,Tweets
php artisan make:integration GitHub --graphql --bearer
```

## make:connector

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

## make:request

```bash
php artisan make:request {name} {connector} [options]
```

### Options

| Option | Description |
|--------|-------------|
| `--method=` | HTTP method (get, post, put, patch, delete) |
| `--json` | JSON content type |
| `--paginate` | Page-based pagination |
| `--cursor` | Cursor-based pagination |
| `--cache` | Add Cache attribute |
| `--retry` | Add Retry attribute |
| `--graphql` | GraphQL request |
| `--jsonrpc` | JSON-RPC request |

### Examples

```bash
php artisan make:request GetUser GitHub
php artisan make:request CreateUser GitHub --method=post --json
php artisan make:request ListUsers GitHub --paginate
php artisan make:request GetUserQuery GitHub --graphql
```

## make:resource

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
php artisan make:resource Users GitHub --crud --requests
php artisan make:resource Posts GitHub --paginate
```

## Customizing Stubs

```bash
php artisan vendor:publish --tag=relay-stubs
```

This creates a `stubs/relay/` directory where you can modify the templates.

## Best Practices

1. **Use `make:integration` for new APIs** - Sets up the complete structure
2. **Use `--crud --requests` together** - Creates matching requests for resources
3. **Choose authentication upfront** - Harder to change later
4. **Add rate limiting for external APIs** - Prevents hitting API limits

<a id="doc-docs-json-rpc-microservices"></a>

Relay provides specialized base classes for internal microservice communication using JSON-RPC. These classes handle common patterns like configuration lookup, bearer token authentication, and Spatie Data integration.

## Overview

The JSON-RPC microservice classes provide:
- Automatic configuration lookup via `services.microservices.{name}.{key}`
- Bearer token authentication from config
- Built-in retry logic (3 attempts, 500ms interval)
- JSON-RPC error detection
- Automatic service name derivation
- Spatie Laravel Data integration with null filtering

## JsonRpcMicroserviceConnector

Base connector for internal microservices with automatic configuration.

```php
use Cline\Relay\Core\JsonRpc\JsonRpcMicroserviceConnector;

class PostalConnector extends JsonRpcMicroserviceConnector
{
    // Service name derived automatically: "postal"
    // Base URL from: config('services.microservices.postal.base_url')
    // Token from: config('services.microservices.postal.token')
}
```

### Configuration

Add your microservice configuration to `config/services.php`:

```php
return [
    'microservices' => [
        'postal' => [
            'base_url' => env('POSTAL_SERVICE_URL', 'https://postal.internal'),
            'token' => env('POSTAL_SERVICE_TOKEN'),
        ],
    ],
];
```

### Service Name Derivation

The service name is automatically derived from the connector's namespace:

| Connector Class | Derived Service Name |
|----------------|---------------------|
| `App\Postal\Connectors\ServiceConnector` | `postal` |
| `Clients\PaymentGateway\Connectors\ApiConnector` | `payment_gateway` |
| `Integrations\UserAuth\Connectors\AuthConnector` | `user_auth` |

### Customizing Behavior

```php
class RobustConnector extends JsonRpcMicroserviceConnector
{
    // Customize retry behavior
    protected int $tries = 5;
    protected int $retryInterval = 1000;

    // Override default config
    public function defaultConfig(): array
    {
        return [
            'timeout' => 60,
            'connect_timeout' => 10,
        ];
    }

    // Custom authentication
    public function authenticate(Request $request): void
    {
        $apiKey = $this->configByKey('api_key');
        $request->withHeader('X-API-Key', $apiKey);
    }
}
```

### Error Detection

The connector automatically detects JSON-RPC errors:

```php
public function hasRequestFailed(Response $response): bool
{
    // HTTP errors (4xx, 5xx)
    if ($response->clientError() || $response->serverError()) {
        return true;
    }

    // JSON-RPC error field present
    return $response->json('error') !== null;
}
```

## JsonRpcMicroserviceRequest

Base request class with automatic data serialization for Spatie Laravel Data objects.

```php
use Cline\Relay\Core\JsonRpc\JsonRpcMicroserviceRequest;
use Spatie\LaravelData\Data;

class QueryCoordinatesRequest extends JsonRpcMicroserviceRequest
{
    public function __construct(
        public readonly QueryCoordinatesData $data,
    ) {}
}
```

### Automatic Data Serialization

The request automatically handles the `$data` property:

```php
// With Spatie Data object
$request = new QueryCoordinatesRequest(
    new QueryCoordinatesData(
        countryCode: 'FI',
        postalCode: '00100',
        locale: 'fi',
        locality: null, // Filtered out
    ),
);

// Produces JSON-RPC body:
{
    "jsonrpc": "2.0",
    "method": "app.queryCoordinates",
    "params": {
        "data": {
            "countryCode": "FI",
            "postalCode": "00100",
            "locale": "fi"
        }
    },
    "id": "uuid-here"
}
```

### Null Filtering

Null values are automatically filtered from nested arrays:

```php
class AddressData extends Data
{
    public function __construct(
        public readonly string $street,
        public readonly ?string $apartment, // null - will be filtered
        public readonly string $city,
        public readonly ?string $state, // null - will be filtered
    ) {}
}
```

### Method Prefix

Customize the JSON-RPC method prefix:

```php
class InternalRequest extends JsonRpcMicroserviceRequest
{
    protected ?string $methodPrefix = 'internal'; // "internal.methodName"
}

class ExternalRequest extends JsonRpcMicroserviceRequest
{
    protected ?string $methodPrefix = null; // Just "methodName"
}
```

## Full Example

### Data Transfer Objects

```php
use Spatie\LaravelData\Data;

class QueryCoordinatesData extends Data
{
    public function __construct(
        public readonly string $countryCode,
        public readonly string $postalCode,
        public readonly string $locale,
        public readonly ?string $locality = null,
    ) {}
}
```

### Request Classes

```php
use Cline\Relay\Core\JsonRpc\JsonRpcMicroserviceRequest;

class QueryCoordinatesRequest extends JsonRpcMicroserviceRequest
{
    public function __construct(
        public readonly QueryCoordinatesData $data,
    ) {}
}

class RetrieveCountryInfoRequest extends JsonRpcMicroserviceRequest
{
    public function __construct(
        public readonly RetrieveCountryInfoData $data,
    ) {}
}
```

### Connector with Convenience Methods

```php
use Cline\Relay\Core\JsonRpc\JsonRpcMicroserviceConnector;
use Cline\Relay\Core\Response;

class ServiceConnector extends JsonRpcMicroserviceConnector
{
    public function queryCoordinates(QueryCoordinatesData $data): Response
    {
        return $this->send(new QueryCoordinatesRequest($data));
    }

    public function retrieveCountryInfo(RetrieveCountryInfoData $data): Response
    {
        return $this->send(new RetrieveCountryInfoRequest($data));
    }
}
```

### Usage

```php
$connector = new ServiceConnector();

$response = $connector->queryCoordinates(
    new QueryCoordinatesData(
        countryCode: 'FI',
        postalCode: '00100',
        locale: 'fi',
        locality: 'Helsinki',
    ),
);

if ($response->successful()) {
    $lat = $response->json('result.data.attributes.latitude');
    $lng = $response->json('result.data.attributes.longitude');
}
```

## Testing

Use the standard Relay testing utilities with microservice connectors:

```php
use Cline\Relay\Testing\MockClient;

test('queries coordinates', function () {
    $connector = (new ServiceConnector())
        ->withMockClient(new MockClient([
            new MockResponse(['result' => ['data' => ['attributes' => [
                'latitude' => 60.1699,
                'longitude' => 24.9384,
            ]]]]),
        ]));

    $response = $connector->queryCoordinates(
        new QueryCoordinatesData(
            countryCode: 'FI',
            postalCode: '00100',
            locale: 'fi',
        ),
    );

    expect($response->json('result.data.attributes.latitude'))->toBe(60.1699);
});
```

For debugging during development:

```php
$connector = (new ServiceConnector())->debug();
$response = $connector->queryCoordinates($data);
// Prints formatted request and response
```

<a id="doc-docs-middleware"></a>

Relay supports Guzzle middleware for intercepting and modifying requests and responses. Middleware can add headers, log requests, track timing, and more.

## Overview

Middleware wraps request/response processing, allowing you to:
- Modify requests before they're sent
- Modify responses after they're received
- Log request/response data
- Track timing and metrics
- Handle errors and retries

## Built-in Middleware

### Header Middleware

```php
use Cline\Relay\Features\Middleware\HeaderMiddleware;
use GuzzleHttp\HandlerStack;

class MyConnector extends Connector
{
    public function middleware(): HandlerStack
    {
        $stack = HandlerStack::create();

        $stack->push(new HeaderMiddleware([
            'X-Api-Version' => '2024-01',
            'X-Client-Name' => 'MyApp',
        ]));

        return $stack;
    }
}
```

### Logging Middleware

```php
use Cline\Relay\Features\Middleware\LoggingMiddleware;
use Psr\Log\LoggerInterface;

class MyConnector extends Connector
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function middleware(): HandlerStack
    {
        $stack = HandlerStack::create();
        $stack->push(new LoggingMiddleware($this->logger));

        return $stack;
    }
}
```

### Timing Middleware

```php
use Cline\Relay\Features\Middleware\TimingMiddleware;

$stack->push(new TimingMiddleware(function (float $duration, $request, $response) {
    metrics()->timing('api.request', $duration);
}));
```

## Middleware Pipeline

```php
use Cline\Relay\Features\Middleware\MiddlewarePipeline;
use Cline\Relay\Features\Middleware\HeaderMiddleware;
use Cline\Relay\Features\Middleware\LoggingMiddleware;
use Cline\Relay\Features\Middleware\TimingMiddleware;

class MyConnector extends Connector
{
    public function middleware(): HandlerStack
    {
        $pipeline = new MiddlewarePipeline();

        $pipeline->push(new HeaderMiddleware([
            'X-Request-ID' => fn () => uniqid(),
        ]));
        $pipeline->push(new LoggingMiddleware($this->logger));
        $pipeline->push(new TimingMiddleware(function ($duration) {
            $this->recordTiming($duration);
        }));

        return $pipeline->toHandlerStack();
    }
}
```

## Guzzle Middleware

### Retry Middleware

```php
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;

public function middleware(): HandlerStack
{
    $stack = HandlerStack::create();

    $stack->push(Middleware::retry(
        decider: function ($retries, $request, $response, $exception) {
            if ($exception instanceof ConnectException) {
                return $retries < 3;
            }

            if ($response && $response->getStatusCode() >= 500) {
                return $retries < 3;
            }

            return false;
        },
        delay: function ($retries) {
            return $retries * 1000;
        }
    ));

    return $stack;
}
```

### History Middleware

```php
use GuzzleHttp\Middleware;

$history = [];

$stack->push(Middleware::history($this->history));

public function getHistory(): array
{
    return $this->history;
}
```

### Map Request Middleware

```php
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;

$stack->push(Middleware::mapRequest(function (RequestInterface $request) {
    return $request->withHeader('X-Timestamp', (string) time());
}));
```

### Map Response Middleware

```php
use GuzzleHttp\Middleware;
use Psr\Http\Message\ResponseInterface;

$stack->push(Middleware::mapResponse(function (ResponseInterface $response) {
    return $response->withHeader('X-Processed', 'true');
}));
```

## Custom Middleware

```php
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Promise\PromiseInterface;

class SignatureMiddleware
{
    public function __construct(
        private readonly string $secretKey,
    ) {}

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
            $signature = $this->sign($request);
            $request = $request->withHeader('X-Signature', $signature);

            return $handler($request, $options)->then(
                function (ResponseInterface $response) use ($request) {
                    return $response->withHeader(
                        'X-Request-ID',
                        $request->getHeaderLine('X-Request-ID')
                    );
                }
            );
        };
    }

    private function sign(RequestInterface $request): string
    {
        $payload = $request->getMethod() . $request->getUri()->getPath();
        return hash_hmac('sha256', $payload, $this->secretKey);
    }
}

$stack->push(new SignatureMiddleware('secret-key'));
```

## Middleware Order

```php
$stack = HandlerStack::create();

// 1. First: Add headers
$stack->push(new HeaderMiddleware(['X-Api-Key' => $apiKey]));

// 2. Second: Sign request
$stack->push(new SignatureMiddleware($secret));

// 3. Third: Log the final request
$stack->push(new LoggingMiddleware($logger));

// 4. Fourth: Track timing
$stack->push(new TimingMiddleware($callback));

// Execution order:
// Request:  Headers -> Sign -> Log -> Timing -> [HTTP]
// Response: <- Timing <- Log <- Sign <- Headers
```

Use named middleware for insertion control:

```php
$stack->push(new LoggingMiddleware($logger), 'logging');
$stack->push(new TimingMiddleware($callback), 'timing');

$stack->before('logging', new HeaderMiddleware($headers), 'headers');
$stack->after('headers', new SignatureMiddleware($secret), 'signature');
```

## Common Patterns

### Request ID Tracking

```php
class RequestIdMiddleware
{
    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            $requestId = uniqid('req_', true);
            $request = $request->withHeader('X-Request-ID', $requestId);

            return $handler($request, $options)->then(
                function (ResponseInterface $response) use ($requestId) {
                    return $response->withHeader('X-Request-ID', $requestId);
                }
            );
        };
    }
}
```

### Rate Limit Tracking

```php
class RateLimitMiddleware
{
    private int $remaining = 0;
    private int $reset = 0;

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            return $handler($request, $options)->then(
                function (ResponseInterface $response) {
                    $this->remaining = (int) $response->getHeaderLine('X-RateLimit-Remaining');
                    $this->reset = (int) $response->getHeaderLine('X-RateLimit-Reset');

                    if ($this->remaining < 10) {
                        logger()->warning('Rate limit running low');
                    }

                    return $response;
                }
            );
        };
    }
}
```

### Error Transformation

```php
class ErrorMiddleware
{
    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            return $handler($request, $options)->then(
                function (ResponseInterface $response) use ($request) {
                    if ($response->getStatusCode() >= 400) {
                        $body = json_decode($response->getBody()->getContents(), true);

                        throw new ApiException(
                            $body['error']['message'] ?? 'Unknown error',
                            $response->getStatusCode()
                        );
                    }

                    return $response;
                }
            );
        };
    }
}
```

## Full Example

```php
<?php

namespace App\Http\Connectors;

use Cline\Relay\Core\Connector;
use Cline\Relay\Features\Middleware\HeaderMiddleware;
use Cline\Relay\Features\Middleware\LoggingMiddleware;
use Cline\Relay\Features\Middleware\TimingMiddleware;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Log\LoggerInterface;

class ApiConnector extends Connector
{
    private array $history = [];

    public function __construct(
        private readonly string $apiKey,
        private readonly LoggerInterface $logger,
    ) {}

    public function baseUrl(): string
    {
        return 'https://api.example.com/v1';
    }

    public function middleware(): HandlerStack
    {
        $stack = HandlerStack::create();

        $stack->push(new HeaderMiddleware([
            'X-Api-Key' => $this->apiKey,
            'X-Client-Version' => '1.0.0',
        ]), 'headers');

        $stack->push(function (callable $handler) {
            return function ($request, $options) use ($handler) {
                $request = $request->withHeader('X-Request-ID', uniqid('req_'));
                return $handler($request, $options);
            };
        }, 'request_id');

        $stack->push(new LoggingMiddleware($this->logger), 'logging');

        $stack->push(new TimingMiddleware(function ($duration) {
            $this->logger->debug("Request took {$duration}ms");
        }), 'timing');

        $stack->push(Middleware::retry(
            function ($retries, $request, $response, $exception) {
                return $retries < 3 && (
                    $exception !== null ||
                    ($response && $response->getStatusCode() >= 500)
                );
            },
            function ($retries) {
                return $retries * 500;
            }
        ), 'retry');

        $stack->push(Middleware::history($this->history), 'history');

        return $stack;
    }

    public function getRequestHistory(): array
    {
        return $this->history;
    }
}
```

<a id="doc-docs-pagination"></a>

Relay provides flexible pagination support for iterating through API results with multiple strategies.

## Pagination Strategies

### Page-Based Pagination

```php
use Cline\Relay\Support\Attributes\Pagination\Pagination;

#[Get]
#[Pagination(
    page: 'page',
    perPage: 'per_page',
    dataKey: 'data',
    totalPagesKey: 'meta.last_page',
    totalKey: 'meta.total',
)]
class GetUsersRequest extends Request
{
    public function endpoint(): string
    {
        return '/users';
    }
}
```

### Cursor-Based Pagination

For APIs like Twitter, Stripe:

```php
use Cline\Relay\Support\Attributes\Pagination\CursorPagination;

#[Get]
#[CursorPagination(
    cursor: 'cursor',
    perPage: 'per_page',
    nextKey: 'meta.next_cursor',
    dataKey: 'data',
)]
class GetTimelineRequest extends Request
{
    public function endpoint(): string
    {
        return '/timeline';
    }
}
```

### Offset-Based Pagination

```php
use Cline\Relay\Support\Attributes\Pagination\OffsetPagination;

#[Get]
#[OffsetPagination(
    offset: 'offset',
    limit: 'limit',
    dataKey: 'data',
    totalKey: 'meta.total',
)]
class SearchRequest extends Request
{
    public function endpoint(): string
    {
        return '/search';
    }
}
```

### Link Header Pagination

For APIs following RFC 5988 (like GitHub):

```php
use Cline\Relay\Support\Attributes\Pagination\LinkPagination;

#[Get]
#[LinkPagination(dataKey: '')]
class GetRepositoriesRequest extends Request
{
    public function endpoint(): string
    {
        return '/repos';
    }
}
```

## Using Paginated Responses

### Fetching Paginated Data

```php
$connector = new GitHubConnector();
$paginated = $connector->paginate(new GetUsersRequest());

// Get items from first page
$users = $paginated->items();

// Check if more pages exist
if ($paginated->hasMore()) {
    // More pages available
}
```

### Iterating All Pages

```php
$paginated = $connector->paginate(new GetUsersRequest());

// Iterate through ALL items from ALL pages
foreach ($paginated as $user) {
    processUser($user);
}
```

### Limiting Pages

```php
// Only fetch first 5 pages
$paginated = $connector->paginate(new GetUsersRequest())
    ->take(5);

foreach ($paginated as $user) {
    // Maximum 5 pages of users
}
```

### Collecting All Items

```php
$allUsers = $connector->paginate(new GetUsersRequest())
    ->collect();

$activeUsers = $allUsers->where('active', true);
```

### Memory-Efficient Iteration

```php
$connector->paginate(new GetUsersRequest())
    ->lazy()
    ->filter(fn ($user) => $user['active'])
    ->each(function ($user) {
        processUser($user);
    });
```

## Converting to Laravel Paginators

### LengthAwarePaginator

```php
$laravelPaginator = $paginated->toLaravelPaginator(
    perPage: 15,
    pageName: 'page',
    page: 1,
);

return view('users.index', ['users' => $laravelPaginator]);
```

### SimplePaginator

```php
$simplePaginator = $paginated->toLaravelSimplePaginator(
    perPage: 15,
    pageName: 'page',
);
```

## Custom Paginators

Implement the `Paginator` contract for custom pagination strategies:

```php
use Cline\Relay\Support\Contracts\Paginator;

class KeysetPaginator implements Paginator
{
    public function getNextPage(Response $response): ?array
    {
        $items = $this->getItems($response);
        if ($items === []) {
            return null;
        }
        $lastItem = end($items);
        return ['after' => $lastItem['id'] ?? null];
    }

    public function getItems(Response $response): array
    {
        return $response->json('data') ?? [];
    }

    public function hasMorePages(Response $response): bool
    {
        return count($this->getItems($response)) >= 20;
    }
}

// Usage
$paginated = $connector->paginateWith(
    new GetItemsRequest(),
    new KeysetPaginator(),
);
```

## Best Practices

1. **Use appropriate strategy** - Page/offset for stable data, cursor for real-time feeds
2. **Limit pages for large datasets** - Use `->take(100)` to prevent runaway pagination
3. **Use lazy collections** - Memory efficient for large datasets
4. **Handle empty pages** - Check `$paginated->items() === []` for no results

<a id="doc-docs-pooling"></a>

Relay supports concurrent request execution through request pooling, significantly improving performance when dealing with multiple API calls.

## Basic Usage

### Creating a Pool

```php
$connector = new ApiConnector();

$responses = $connector->pool([
    new GetUserRequest(1),
    new GetUserRequest(2),
    new GetUserRequest(3),
])->send();

// Access responses by index
$user1 = $responses[0]->json();
$user2 = $responses[1]->json();
```

### Keyed Requests

Use associative arrays for easier response access:

```php
$responses = $connector->pool([
    'user' => new GetUserRequest(1),
    'orders' => new GetOrdersRequest(1),
    'profile' => new GetProfileRequest(1),
])->send();

$user = $responses['user']->json();
$orders = $responses['orders']->json();
```

## Concurrency Control

```php
// Execute max 10 requests at a time
$responses = $connector->pool($requests)
    ->concurrent(10)
    ->send();
```

The default concurrency is 5. Adjust based on API rate limits and server capacity.

## Response Handling

### Response Callback

Process responses as they complete:

```php
$connector->pool($requests)
    ->onResponse(function (Response $response, Request $request, int|string $key) {
        logger()->info("Request {$key} completed", [
            'status' => $response->status(),
        ]);
        cache()->put("user_{$key}", $response->json());
    })
    ->send();
```

### Error Callback

Handle errors for individual requests:

```php
$connector->pool($requests)
    ->onError(function (RequestException $exception, Request $request, int|string $key) {
        logger()->error("Request {$key} failed", [
            'status' => $exception->status(),
        ]);
    })
    ->send();
```

### Combined Callbacks

```php
$results = [];
$errors = [];

$connector->pool($requests)
    ->concurrent(10)
    ->onResponse(function ($response, $request, $key) use (&$results) {
        $results[$key] = $response->json();
    })
    ->onError(function ($exception, $request, $key) use (&$errors) {
        $errors[$key] = $exception->getMessage();
    })
    ->send();
```

## Memory-Efficient Processing

### Lazy Iteration

```php
$pool = $connector->pool($requests)
    ->concurrent(10)
    ->lazy();

foreach ($pool->iterate() as $key => $response) {
    processResponse($response);
}
```

### Each Method

```php
$connector->pool($requests)
    ->concurrent(5)
    ->each(function (Response $response, int|string $key) {
        $user = User::fromApi($response->json());
        $user->save();
    });
```

## Common Patterns

### Batch Processing

```php
$userIds = range(1, 1000);

foreach (array_chunk($userIds, 100) as $batch) {
    $requests = array_map(
        fn ($id) => new GetUserRequest($id),
        $batch,
    );

    $responses = $connector->pool($requests)
        ->concurrent(20)
        ->send();

    foreach ($responses as $response) {
        processUser($response->json());
    }
}
```

### Parallel API Aggregation

```php
$responses = $connector->pool([
    'user' => new GetUserRequest($userId),
    'orders' => new GetUserOrdersRequest($userId),
    'notifications' => new GetUserNotificationsRequest($userId),
])
->concurrent(4)
->send();

return [
    'user' => $responses['user']->json(),
    'orders' => $responses['orders']->json(),
    'notifications' => $responses['notifications']->json(),
];
```

### Retry Failed Requests

```php
$failedRequests = [];

$responses = $connector->pool($requests)
    ->onError(function ($exception, $request, $key) use (&$failedRequests) {
        if ($exception->status() >= 500) {
            $failedRequests[$key] = $request;
        }
    })
    ->send();

if ($failedRequests !== []) {
    sleep(5);
    $retryResponses = $connector->pool($failedRequests)
        ->concurrent(2)
        ->send();
}
```

### Progress Tracking

```php
$total = count($requests);
$completed = 0;

$connector->pool($requests)
    ->concurrent(10)
    ->onResponse(function () use (&$completed, $total) {
        $completed++;
        echo "\rProgress: " . round(($completed / $total) * 100) . "%";
    })
    ->send();
```

## Best Practices

1. **Size concurrency appropriately** - Consider API rate limits
2. **Use keys for correlation** - Easier to match requests to responses
3. **Handle all error cases** - Both response errors and exceptions
4. **Batch large request sets** - Process in manageable chunks

<a id="doc-docs-rate-limiting"></a>

Relay provides client-side rate limiting to prevent exceeding API quotas and handles server-side 429 responses gracefully.

## Connector-Level Rate Limiting

```php
use Cline\Relay\Core\Connector;
use Cline\Relay\Features\RateLimiting\RateLimitConfig;

class TwitterConnector extends Connector
{
    public function baseUrl(): string
    {
        return 'https://api.twitter.com/2';
    }

    public function rateLimit(): ?RateLimitConfig
    {
        return new RateLimitConfig(
            requests: 300,
            perSeconds: 900,  // 15 minute window
        );
    }
}
```

## Rate Limit Configuration

```php
$config = new RateLimitConfig(
    requests: 100,
    perSeconds: 60,
    retry: false,
    maxRetries: 3,
    backoff: 'exponential',
);
```

### Concurrency Limiting

```php
public function concurrencyLimit(): ?int
{
    return 10; // Max 10 concurrent requests
}
```

## Rate Limit Stores

### Memory Store (Default)

```php
use Cline\Relay\Features\RateLimiting\MemoryStore;

public function rateLimitStore(): RateLimitStore
{
    return new MemoryStore();
}
```

### Cache Store

```php
use Cline\Relay\Features\RateLimiting\CacheStore;

public function rateLimitStore(): RateLimitStore
{
    return new CacheStore(
        cache: app('cache')->store('redis'),
        prefix: 'rate_limit',
    );
}
```

## Request-Level Rate Limiting

```php
use Cline\Relay\Support\Attributes\RateLimiting\RateLimit;
use Cline\Relay\Support\Attributes\RateLimiting\ConcurrencyLimit;

#[Get]
#[RateLimit(maxAttempts: 10, decaySeconds: 60)]
#[ConcurrencyLimit(max: 5)]
class SearchRequest extends Request
{
    public function endpoint(): string
    {
        return '/search';
    }
}
```

## Handling Rate Limit Exceptions

### Client-Side Rate Limit

```php
use Cline\Relay\Support\Exceptions\Client\RateLimitException;

try {
    $response = $connector->send(new SearchRequest());
} catch (RateLimitException $e) {
    if ($e->isClientSide()) {
        $retryAfter = $e->retryAfter();
        $remaining = $e->remaining();
    }
}
```

### Server-Side Rate Limit (429)

```php
try {
    $response = $connector->send(new SearchRequest());
} catch (RateLimitException $e) {
    if ($e->isServerSide()) {
        $retryAfter = $e->retryAfter(); // From Retry-After header
    }
}
```

## Reading Rate Limit Headers

```php
$response = $connector->send(new GetUsersRequest());

$rateLimit = $response->rateLimit();

if ($rateLimit) {
    echo "Limit: {$rateLimit->limit}";
    echo "Remaining: {$rateLimit->remaining}";
    echo "Reset: {$rateLimit->reset}";
}
```

## Rate Limit with Retry

```php
#[Get]
#[RateLimit(maxAttempts: 100, decaySeconds: 60)]
#[Retry(times: 3, sleepMs: 1000, when: [429])]
class SearchRequest extends Request {}
```

## Custom Backoff Strategy

```php
use Cline\Relay\Support\Contracts\BackoffStrategy;

class FibonacciBackoffStrategy implements BackoffStrategy
{
    public function calculateDelay(Request $request, int $attempt, int $retryAfter = 0): int
    {
        if ($retryAfter > 0) {
            return $retryAfter * 1_000;
        }
        return $this->fibonacci($attempt) * 1_000;
    }

    private function fibonacci(int $n): int
    {
        if ($n <= 2) return 1;
        $a = 1; $b = 1;
        for ($i = 3; $i <= $n; $i++) {
            $c = $a + $b;
            $a = $b;
            $b = $c;
        }
        return $b;
    }
}

#[RateLimit(requests: 100, perSeconds: 60, backoff: FibonacciBackoffStrategy::class)]
class ApiRequest extends Request {}
```

## Rate Limit Buckets

Use different buckets for different endpoints:

```php
#[Get]
#[RateLimit(maxAttempts: 10, decaySeconds: 60, key: 'search')]
class SearchRequest extends Request {}

#[Get]
#[RateLimit(maxAttempts: 100, decaySeconds: 60, key: 'read')]
class GetDataRequest extends Request {}
```

## Best Practices

1. **Use persistent storage** - CacheStore for distributed systems
2. **Set conservative limits** - Stay below API limits
3. **Combine with retry** - Auto-retry on 429 responses
4. **Monitor remaining quota** - Check headers and log warnings

<a id="doc-docs-requests"></a>

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

<a id="doc-docs-resilience"></a>

Relay provides resilience patterns to handle transient failures gracefully.

## Retry Configuration

### Basic Retry

```php
use Cline\Relay\Support\Attributes\Resilience\Retry;

#[Get]
#[Retry(times: 3)]
class GetDataRequest extends Request
{
    public function endpoint(): string
    {
        return '/data';
    }
}
```

### Retry with Delay

```php
#[Retry(times: 3, delay: 1000)] // 1 second between retries
class GetDataRequest extends Request {}
```

### Exponential Backoff

```php
#[Retry(times: 3, delay: 100, multiplier: 2.0, maxDelay: 30000)]
// Retry 1: wait 100ms
// Retry 2: wait 200ms
// Retry 3: wait 400ms
class GetDataRequest extends Request {}
```

### Retry on Specific Status Codes

```php
#[Retry(times: 3, delay: 500, when: [429, 500, 502, 503, 504])]
class GetDataRequest extends Request {}
```

### Custom Retry Condition

```php
#[Retry(times: 3, callback: 'shouldRetry')]
class GetDataRequest extends Request
{
    public function shouldRetry(Response $response, int $attempt): bool
    {
        if ($response->serverError()) {
            return true;
        }
        $errorCode = $response->json('error.code');
        return in_array($errorCode, ['TEMPORARY_ERROR', 'SERVICE_BUSY']);
    }
}
```

## Timeout Configuration

### Request Timeout

```php
use Cline\Relay\Support\Attributes\Resilience\Timeout;

#[Get]
#[Timeout(seconds: 5)]
class QuickCheckRequest extends Request {}
```

### Connection and Request Timeout

```php
#[Timeout(seconds: 30, connectSeconds: 5)]
class SlowEndpointRequest extends Request {}
```

### Connector-Level Timeout

```php
class MyConnector extends Connector
{
    public function timeout(): int
    {
        return 30;
    }

    public function connectTimeout(): int
    {
        return 10;
    }
}
```

## Circuit Breaker

Prevents repeated calls to a failing service.

### Basic Circuit Breaker

```php
use Cline\Relay\Support\Attributes\Resilience\CircuitBreaker;

#[Get]
#[CircuitBreaker(
    failureThreshold: 5,
    resetTimeout: 30,
)]
class UnreliableApiRequest extends Request {}
```

### Circuit Breaker with Success Threshold

```php
#[CircuitBreaker(
    failureThreshold: 5,
    resetTimeout: 30,
    successThreshold: 3,  // Require 3 successes to close
)]
class UnreliableApiRequest extends Request {}
```

### Circuit Breaker States

1. **Closed** - Normal operation, requests flow through
2. **Open** - Requests fail immediately without calling the API
3. **Half-Open** - Limited requests allowed to test if service recovered

### Circuit Breaker Policy

```php
use Cline\Relay\Support\Contracts\CircuitBreakerPolicy;

class ApiCircuitBreakerPolicy implements CircuitBreakerPolicy
{
    public function failureThreshold(): int { return 5; }
    public function resetTimeout(): int { return 30; }
    public function successThreshold(): int { return 2; }

    public function isFailure(Request $request, Response $response): bool
    {
        return $response->serverError();
    }

    public function onOpen(string $key): void
    {
        logger()->warning("Circuit opened: {$key}");
    }
}

#[CircuitBreaker(policy: ApiCircuitBreakerPolicy::class)]
class ApiRequest extends Request {}
```

## Combining Resilience Patterns

```php
#[Get]
#[Timeout(seconds: 10)]
#[Retry(times: 3, sleepMs: 500, multiplier: 2, when: [500, 502, 503])]
#[CircuitBreaker(failureThreshold: 5, resetTimeout: 60)]
class ResilientRequest extends Request {}
```

Execution order:
1. **Timeout** - Each attempt has a 10-second limit
2. **Retry** - If timeout or 5xx error, retry up to 3 times with backoff
3. **Circuit Breaker** - If 5 consecutive failures, open circuit

## Error Handling Patterns

### Graceful Degradation

```php
try {
    $response = $connector->send(new GetProductsRequest());
    cache()->put('products', $response->json(), 3600);
    return $response->json();
} catch (RequestException $e) {
    if ($cached = cache()->get('products')) {
        return $cached;
    }
    return ['products' => [], 'error' => 'Service temporarily unavailable'];
}
```

### Bulkhead Pattern

Isolate failures by using separate connectors:

```php
class PaymentConnector extends Connector
{
    public function rateLimit(): ?RateLimitConfig
    {
        return new RateLimitConfig(maxAttempts: 100, decaySeconds: 60);
    }
}

class InventoryConnector extends Connector
{
    // Independent from payment service failures
    public function rateLimit(): ?RateLimitConfig
    {
        return new RateLimitConfig(maxAttempts: 500, decaySeconds: 60);
    }
}
```

## Best Practices

1. **Combine patterns** - Timeout + Retry + Circuit Breaker
2. **Use exponential backoff** - Avoid thundering herd
3. **Set appropriate thresholds** - Balance availability and protection
4. **Log circuit state changes** - Monitor service health

<a id="doc-docs-responses"></a>

The Response class wraps HTTP responses with typed accessors and convenience methods for parsing, validation, and transformation.

## Basic Response Handling

```php
$response = $connector->send(new GetUserRequest(1));
```

## Status Checking

```php
$status = $response->status(); // 200, 404, 500, etc.

$response->ok();          // true if 2xx
$response->successful();  // alias for ok()
$response->redirect();    // true if 3xx
$response->clientError(); // true if 4xx
$response->serverError(); // true if 5xx
$response->failed();      // true if 4xx or 5xx
```

## Parsing Response Body

### As JSON Array

```php
$data = $response->json();
$name = $response->json('user.name');
$email = $response->json('data.0.email');
$role = $response->json('user.role') ?? 'guest';
```

### As Object

```php
$object = $response->object();
echo $object->name;
```

### As Collection

```php
$collection = $response->collect();
$users = $response->collect('data.users');

$activeUsers = $response->collect('users')
    ->where('active', true)
    ->pluck('email');
```

### As Raw String

```php
$body = $response->body();
```

## Mapping to DTOs

### Single DTO

```php
class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
    ) {}
}

$user = $response->dto(User::class);
```

### Collection of DTOs

```php
$users = $response->dtoCollection(User::class);
$users = $response->dtoCollection(User::class, 'data.users');
```

## Headers

```php
$contentType = $response->header('Content-Type');
$rateLimit = $response->header('X-RateLimit-Remaining');
$headers = $response->headers();
```

## Error Handling

### Throw on Failure

```php
$response->throw(); // Throws if 4xx or 5xx

$data = $response->throw()->json();
```

### Check Before Processing

```php
if ($response->failed()) {
    $error = $response->json('error.message');
    throw new ApiException($error, $response->status());
}
```

## Conditional Requests

```php
$etag = $response->etag();
$lastModified = $response->lastModified();

if ($response->wasNotModified()) {
    // Use cached version
}
```

## Caching Information

```php
if ($response->fromCache()) {
    // Response was served from cache
}
```

## Timing

```php
$durationMs = $response->duration();
```

## Rate Limit Information

```php
$rateLimit = $response->rateLimit();

if ($rateLimit) {
    echo $rateLimit->limit;
    echo $rateLimit->remaining;
    echo $rateLimit->reset;
}
```

## Tracing

```php
$traceId = $response->traceId();
$spanId = $response->spanId();
```

## Idempotency

```php
$key = $response->idempotencyKey();

if ($response->wasIdempotentReplay()) {
    // This response was a replay
}
```

## File Downloads

### Save to File

```php
$response->saveTo('/path/to/file.pdf');
```

### Stream with Progress

```php
$response->streamTo('/path/to/large-file.zip', function ($downloaded, $total) {
    $percent = $total > 0 ? ($downloaded / $total) * 100 : 0;
    echo "Downloaded: {$percent}%\n";
});
```

### Get Filename

```php
$filename = $response->filename();

if ($response->isDownload()) {
    $response->saveTo('/downloads/' . $response->filename());
}
```

### Get as Base64

```php
$base64 = $response->base64();
```

### Iterate Chunks

```php
foreach ($response->chunks(8192) as $chunk) {
    fwrite($handle, $chunk);
}
```

### Get as Stream

```php
$stream = $response->stream();
```

## Response Transformation

### Modify JSON Body

```php
$newResponse = $response->withJson(['modified' => true]);
$newResponse = $response->withJsonKey('processed_at', now()->toIso8601String());
```

### Modify Raw Body

```php
$newResponse = $response->withBody('new raw content');
```

### Modify Headers

```php
$newResponse = $response->withHeaders(['X-Custom' => 'value']);
$newResponse = $response->withHeader('X-Custom', 'value');
```

### Modify Status

```php
$newResponse = $response->withStatus(201);
```

## Accessing Original Request

```php
$request = $response->request();
echo $request->endpoint();
echo $request->method();
```

## PSR-7 Response

```php
$psrResponse = $response->toPsrResponse();
```

## Creating Responses

```php
use Cline\Relay\Core\Response;

$response = Response::make(
    data: ['id' => 1, 'name' => 'John'],
    status: 200,
    headers: ['X-Custom' => 'value'],
);

$response->json('name'); // 'John'
$response->status();     // 200
```

## Debugging

```php
$response->dump(); // Dump and continue
$response->dd();   // Dump and die
```

## Macros

```php
use Cline\Relay\Core\Response;

Response::macro('isRateLimited', function () {
    return $this->status() === 429;
});

if ($response->isRateLimited()) {
    $retryAfter = $response->header('Retry-After');
    sleep((int) $retryAfter);
}
```

## Full Example

```php
$response = $connector->send(new GetOrderRequest($orderId));

if ($response->failed()) {
    if ($response->status() === 404) {
        throw new OrderNotFoundException($orderId);
    }

    throw new ApiException(
        $response->json('error.message'),
        $response->status()
    );
}

logger()->info('API call completed', [
    'duration' => $response->duration(),
    'cached' => $response->fromCache(),
    'trace_id' => $response->traceId(),
]);

$rateLimit = $response->rateLimit();
if ($rateLimit && $rateLimit->remaining < 10) {
    logger()->warning('Rate limit running low', [
        'remaining' => $rateLimit->remaining,
        'reset' => $rateLimit->reset,
    ]);
}

$order = $response->dto(Order::class);
$items = $response->dtoCollection(OrderItem::class, 'items');

if ($response->json('has_attachment')) {
    $attachmentResponse = $connector->send(new GetOrderAttachmentRequest($orderId));

    if ($attachmentResponse->isDownload()) {
        $attachmentResponse->saveTo(
            storage_path('orders/' . $attachmentResponse->filename())
        );
    }
}
```

<a id="doc-docs-testing"></a>

Relay provides comprehensive testing utilities for writing reliable tests.

## MockClient

### Global Mocking

```php
use Cline\Relay\Testing\MockClient;
use Cline\Relay\Testing\MockResponse;

beforeEach(function () {
    MockClient::destroyGlobal();
});

it('mocks API calls globally', function () {
    MockClient::global([
        MockResponse::json(['id' => 1]),
        MockResponse::json(['id' => 2]),
    ]);

    $result = MyService::fetchData();

    expect($result->id)->toBe(1);
    MockClient::getGlobal()->assertSent('/users');
});
```

### URL Pattern Matching

```php
$mockClient = new MockClient([
    'https://api.example.com/users' => MockResponse::json(['users' => []]),
    'https://api.example.com/users/*/orders' => MockResponse::json(['orders' => []]),
    '*/api/v1/*' => MockResponse::json(['version' => 'v1']),
]);
```

### Request Class Mapping

```php
$mockClient = new MockClient([
    GetUserRequest::class => MockResponse::json(['id' => 1, 'name' => 'John']),
    CreateOrderRequest::class => MockResponse::json(['order_id' => 123], 201),
]);
```

### Dynamic Responses

```php
$mockClient = new MockClient([
    GetUserRequest::class => function (Request $request): Response {
        return MockResponse::json([
            'user_id' => $request->query('id'),
        ]);
    },
]);
```

### Assertions

```php
$mockClient->assertSent(GetUserRequest::class);
$mockClient->assertSent('/users');
$mockClient->assertNotSent('/admin');
$mockClient->assertSentCount(3);
$mockClient->assertNothingSent();
```

## MockConnector

### Basic Usage

```php
use Cline\Relay\Testing\MockConnector;

it('fetches user data', function () {
    $connector = new MockConnector();

    $connector->addResponse(MockResponse::json([
        'id' => 1,
        'name' => 'John Doe',
    ]));

    $response = $connector->send(new GetUserRequest(1));

    expect($response->json('name'))->toBe('John Doe');
});
```

### Sequential Responses

```php
$connector = new MockConnector();

$connector->addResponses([
    MockResponse::json(['id' => 1]),
    MockResponse::json(['id' => 2]),
    MockResponse::json(['id' => 3]),
]);
```

### Fixed Response

```php
$connector->alwaysReturn(MockResponse::json(['status' => 'ok']));
```

### Dynamic Responses

```php
$connector->addResponse(function (Request $request) {
    return MockResponse::json([
        'id' => $request->query('id'),
    ]);
});
```

## MockResponse Factory

### JSON Responses

```php
MockResponse::json(['key' => 'value']);
MockResponse::json(['error' => 'Not found'], 404);
MockResponse::json(['data' => []], 200, ['X-Request-Id' => 'abc123']);
```

### Common HTTP Responses

```php
MockResponse::empty();                    // 204
MockResponse::notFound();                 // 404
MockResponse::unauthorized();             // 401
MockResponse::forbidden();                // 403
MockResponse::validationError(['email' => ['Required']]); // 422
MockResponse::rateLimited(60);            // 429
MockResponse::serverError();              // 500
MockResponse::serviceUnavailable();       // 503
```

### Paginated Responses

```php
MockResponse::paginated(
    items: [['id' => 1], ['id' => 2]],
    page: 1,
    perPage: 15,
    total: 100,
);
```

## Fixtures

Record and replay real API responses. Fixtures store API responses as JSON files that can be replayed in tests.

### Basic Usage

```php
use Cline\Relay\Testing\Fixture;
use Cline\Relay\Testing\MockClient;

$mockClient = new MockClient([
    GetUserRequest::class => Fixture::make('users/get-user-1'),
]);
```

### Fixture Recording

Fixtures support automatic recording - on the first test run, a real API request is made and the response is saved. Subsequent runs replay from the saved file.

```php
use Cline\Relay\Testing\Fixture;
use Cline\Relay\Testing\MockConfig;

// Disable throw on missing fixtures to enable recording
MockConfig::throwOnMissingFixtures(false);

// When using a connector with MockClient, recording is automatic
$connector = new ApiConnector();
$connector->withMockClient(new MockClient([
    GetUserRequest::class => Fixture::make('users/get-user-1'),
]));

// First run: makes real API call, stores response in tests/Fixtures/Saloon/users/get-user-1.json
// Subsequent runs: replays from the stored file
$response = $connector->send(new GetUserRequest(1));
```

### Custom Fixture Path

```php
use Cline\Relay\Testing\Fixture;

// Set custom path for all fixtures
Fixture::setFixturePath('tests/Fixtures/Api');

// Fixture will be stored at: tests/Fixtures/Api/users/list.json
$fixture = Fixture::make('users/list');
```

### Redacting Sensitive Data

Protect sensitive information when recording fixtures:

```php
$fixture = Fixture::make('users/auth')
    ->withSensitiveHeaders([
        'Authorization' => '[REDACTED]',
        'X-Api-Key' => '[API_KEY]',
    ])
    ->withSensitiveJsonParameters([
        'password' => '[HIDDEN]',
        'token' => fn () => '[DYNAMIC_TOKEN]',
    ])
    ->withSensitiveRegexPatterns([
        '/sk-[a-zA-Z0-9]+/' => '[API_KEY]',
        '/\d{16}/' => '[CARD_NUMBER]',
    ]);
```

### Fixture File Format

Fixtures are stored as JSON with this structure:

```json
{
    "status": 200,
    "headers": {
        "Content-Type": "application/json"
    },
    "body": {
        "id": 1,
        "name": "John Doe"
    }
}
```

### Configuration Options

```php
use Cline\Relay\Testing\MockConfig;

// Throw exception when fixture file is missing (default: false)
MockConfig::throwOnMissingFixtures(true);

// Set fixture storage path
MockConfig::setFixturePath('tests/Fixtures/Custom');

// Reset all mock configuration
MockConfig::reset();
```

## Request Assertions

```php
$connector->assertSent('/users');
$connector->assertSent('/users', 'POST');
$connector->assertNotSent('/admin');
$connector->assertSentCount(3);

$lastRequest = $connector->lastRequest();
expect($lastRequest->endpoint())->toBe('/users');
expect($lastRequest->method())->toBe('POST');
expect($lastRequest->body())->toBe(['email' => 'john@example.com']);
```

## Testing Patterns

### Testing Service Classes

```php
it('creates a user', function () {
    $connector = new MockConnector();
    $connector->addResponse(MockResponse::json(['id' => 1], 201));

    $service = new UserService($connector);
    $user = $service->createUser(['name' => 'John']);

    expect($user->id)->toBe(1);
    $connector->assertSent('/users', 'POST');
});
```

### Testing Error Handling

```php
it('handles 404 errors', function () {
    $connector = new MockConnector();
    $connector->addResponse(MockResponse::notFound());

    expect(fn () => $service->getUser(999))
        ->toThrow(UserNotFoundException::class);
});
```

### Testing Pagination

```php
it('fetches all pages', function () {
    $connector = new MockConnector();

    $connector->addResponses([
        MockResponse::json(['data' => [['id' => 1]], 'meta' => ['next_cursor' => 'abc']]),
        MockResponse::json(['data' => [['id' => 2]], 'meta' => ['next_cursor' => null]]),
    ]);

    $items = $connector->paginate(new GetItemsRequest())->collect();

    expect($items)->toHaveCount(2);
    $connector->assertSentCount(2);
});
```

### Laravel Feature Tests

```php
it('displays user profile', function () {
    $mockConnector = new MockConnector();
    $mockConnector->addResponse(MockResponse::json(['id' => 1, 'name' => 'John']));

    $this->app->bind(ApiConnector::class, fn () => $mockConnector);

    $response = $this->get('/users/1');

    $response->assertOk();
    $response->assertSee('John');
});
```

## Best Practices

1. **Use specific mock responses** - Match real API structure
2. **Test error cases** - 404, 401, 429, 500 scenarios
3. **Verify request contents** - Check body, headers, query params
4. **Reset between tests** - Use `beforeEach`/`afterEach`

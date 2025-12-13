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

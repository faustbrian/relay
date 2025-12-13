# Attributes

Relay uses PHP 8 attributes to configure requests declaratively. This guide covers all available attributes.

## HTTP Method Attributes

Define the HTTP method for a request:

### GET

```php
use Cline\Relay\Support\Attributes\Methods\Get;

#[Get]
class GetUsersRequest extends Request
{
    public function endpoint(): string
    {
        return '/users';
    }
}
```

### POST

```php
use Cline\Relay\Support\Attributes\Methods\Post;

#[Post]
class CreateUserRequest extends Request {}
```

### PUT

```php
use Cline\Relay\Support\Attributes\Methods\Put;

#[Put]
class ReplaceUserRequest extends Request {}
```

### PATCH

```php
use Cline\Relay\Support\Attributes\Methods\Patch;

#[Patch]
class UpdateUserRequest extends Request {}
```

### DELETE

```php
use Cline\Relay\Support\Attributes\Methods\Delete;

#[Delete]
class DeleteUserRequest extends Request {}
```

### HEAD

```php
use Cline\Relay\Support\Attributes\Methods\Head;

#[Head]
class HeadUserRequest extends Request {}
```

### OPTIONS

```php
use Cline\Relay\Support\Attributes\Methods\Options;

#[Options]
class OptionsUserRequest extends Request {}
```

## Content Type Attributes

Declare the request body content type:

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

### Form (application/x-www-form-urlencoded)

```php
use Cline\Relay\Support\Attributes\ContentTypes\Form;

#[Post]
#[Form]
class LoginRequest extends Request
{
    public function body(): array
    {
        return ['username' => 'john', 'password' => 'secret'];
    }
}
```

### Multipart (multipart/form-data)

```php
use Cline\Relay\Support\Attributes\ContentTypes\Multipart;

#[Post]
#[Multipart]
class UploadRequest extends Request
{
    public function body(): array
    {
        return [
            'file' => fopen('/path/to/file', 'r'),
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
class CreateOrderRequest extends Request {}
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

## Protocol Attributes

For specialized API protocols:

### GraphQL

```php
use Cline\Relay\Support\Attributes\Protocols\GraphQL;

#[GraphQL]
class GetUserQuery extends Request
{
    public function body(): array
    {
        return [
            'query' => '
                query GetUser($id: ID!) {
                    user(id: $id) {
                        id
                        name
                        email
                    }
                }
            ',
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

// Custom version
#[JsonRpc(version: '1.0')]
class LegacyCallRequest extends Request {}
```

### SOAP

```php
use Cline\Relay\Support\Attributes\Protocols\Soap;

#[Soap]
class SoapRequest extends Request {}

// SOAP 1.2
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

Automatically throw exceptions for error responses:

```php
use Cline\Relay\Support\Attributes\ThrowOnError;

// Throw on both client (4xx) and server (5xx) errors
#[Get]
#[ThrowOnError]
class GetUserRequest extends Request {}

// Throw only on client errors
#[ThrowOnError(clientErrors: true, serverErrors: false)]
class GetUserRequest extends Request {}

// Throw only on server errors
#[ThrowOnError(clientErrors: false, serverErrors: true)]
class GetUserRequest extends Request {}
```

Can also be applied to connectors:

```php
#[ThrowOnError]
class StrictConnector extends Connector {}
```

## Caching Attributes

### Cache

Enable caching for a request:

```php
use Cline\Relay\Support\Attributes\Caching\Cache;

#[Get]
#[Cache(ttl: 3600)] // Cache for 1 hour
class GetUsersRequest extends Request {}

// With cache tags
#[Cache(ttl: 3600, tags: ['users', 'list'])]
class GetUsersRequest extends Request {}
```

### NoCache

Disable caching for a specific request:

```php
use Cline\Relay\Support\Attributes\Caching\NoCache;

#[Get]
#[NoCache]
class GetCurrentUserRequest extends Request {}
```

### InvalidatesCache

Invalidate cache tags after a request:

```php
use Cline\Relay\Support\Attributes\Caching\InvalidatesCache;

#[Post]
#[InvalidatesCache(tags: ['users', 'list'])]
class CreateUserRequest extends Request {}
```

## Rate Limiting Attributes

### RateLimit

Apply request-level rate limiting:

```php
use Cline\Relay\Support\Attributes\RateLimiting\RateLimit;

#[Get]
#[RateLimit(maxAttempts: 10, decaySeconds: 60)]
class SearchRequest extends Request {}
```

### ConcurrencyLimit

Limit concurrent requests:

```php
use Cline\Relay\Support\Attributes\RateLimiting\ConcurrencyLimit;

#[Get]
#[ConcurrencyLimit(max: 5)]
class HeavyRequest extends Request {}
```

## Resilience Attributes

### Retry

Configure automatic retries:

```php
use Cline\Relay\Support\Attributes\Resilience\Retry;

#[Get]
#[Retry(times: 3, delay: 100)]
class GetDataRequest extends Request {}

// With exponential backoff
#[Retry(times: 3, delay: 100, multiplier: 2.0, maxDelay: 30000)]
class GetDataRequest extends Request {}

// Retry on specific status codes
#[Retry(times: 3, when: [429, 503])]
class GetDataRequest extends Request {}

// Retry with custom condition
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

Set request timeout:

```php
use Cline\Relay\Support\Attributes\Resilience\Timeout;

#[Get]
#[Timeout(seconds: 5)]
class QuickRequest extends Request {}

// With connect timeout
#[Timeout(seconds: 30, connectSeconds: 5)]
class SlowRequest extends Request {}
```

### CircuitBreaker

Apply circuit breaker pattern:

```php
use Cline\Relay\Support\Attributes\Resilience\CircuitBreaker;

#[Get]
#[CircuitBreaker(
    failureThreshold: 5,      // Open after 5 failures
    resetTimeout: 30,         // Try again after 30 seconds
    successThreshold: 2,      // Close after 2 successes
)]
class UnreliableApiRequest extends Request {}
```

## Pagination Attributes

### Pagination (Page-Based)

```php
use Cline\Relay\Support\Attributes\Pagination\Pagination;

#[Get]
#[Pagination(
    page: 'page',             // Query param for page number
    perPage: 'per_page',      // Query param for items per page
    dataKey: 'data',          // Response key containing items
    totalPagesKey: 'meta.last_page', // Response key for total pages
    totalKey: 'meta.total',   // Response key for total items
)]
class GetUsersRequest extends Request {}
```

### SimplePagination

Simple page-based pagination with has_more indicator:

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

### CursorPagination

Cursor-based pagination:

```php
use Cline\Relay\Support\Attributes\Pagination\CursorPagination;

#[Get]
#[CursorPagination(
    cursor: 'cursor',           // Query param for cursor
    perPage: 'per_page',        // Query param for items per page
    nextKey: 'meta.next_cursor', // Response key for next cursor
    dataKey: 'data',            // Response key containing items
)]
class GetFeedRequest extends Request {}
```

### OffsetPagination

Offset-based pagination:

```php
use Cline\Relay\Support\Attributes\Pagination\OffsetPagination;

#[Get]
#[OffsetPagination(
    offset: 'offset',      // Query param for offset
    limit: 'limit',        // Query param for limit
    dataKey: 'data',       // Response key containing items
    totalKey: 'meta.total', // Response key for total items
)]
class SearchRequest extends Request {}
```

### LinkPagination

Link header-based pagination (RFC 5988):

```php
use Cline\Relay\Support\Attributes\Pagination\LinkPagination;

#[Get]
#[LinkPagination]
class GetRepositoriesRequest extends Request {}
```

## Network Attributes

### Proxy

Configure proxy settings:

```php
use Cline\Relay\Support\Attributes\Network\Proxy;

#[Get]
#[Proxy(
    http: 'http://proxy.example.com:8080',
    https: 'https://proxy.example.com:8080',
)]
class ProxiedRequest extends Request {}

// With authentication
#[Proxy(http: 'http://user:pass@proxy.example.com:8080')]
class AuthenticatedProxyRequest extends Request {}
```

### SSL

Configure SSL/TLS settings:

```php
use Cline\Relay\Support\Attributes\Network\Ssl;

#[Get]
#[Ssl(verify: false)] // Disable SSL verification (not recommended for production)
class InsecureRequest extends Request {}

// With custom certificate
#[Ssl(verify: '/path/to/ca-bundle.crt')]
class CustomCertRequest extends Request {}
```

### ForceIpResolve

Force IPv4 or IPv6:

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

Mark requests as idempotent:

```php
use Cline\Relay\Support\Attributes\Idempotent;

#[Post]
#[Idempotent]
class CreatePaymentRequest extends Request {}

// Custom header name
#[Idempotent(header: 'X-Request-ID')]
class CreateOrderRequest extends Request {}

// Custom key generation method
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

Enable response streaming:

```php
use Cline\Relay\Support\Attributes\Stream;

#[Get]
#[Stream]
class DownloadFileRequest extends Request {}

// With custom buffer size
#[Stream(bufferSize: 16384)]
class LargeDownloadRequest extends Request {}
```

## DTO Mapping Attribute

Automatically map responses to DTOs:

```php
use Cline\Relay\Support\Attributes\Dto;

#[Get]
#[Dto(User::class)]
class GetUserRequest extends Request {}

// With nested data key
#[Dto(User::class, dataKey: 'data.user')]
class GetUserRequest extends Request {}
```

## Combining Attributes

Attributes can be combined for complex configurations:

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

Check and retrieve attributes at runtime:

```php
$request = new CreatePaymentRequest('cust_123', 1000);

// Check if attribute exists
if ($request->hasAttribute(Retry::class)) {
    // Has retry configured
}

// Get attribute instance
$retry = $request->getAttribute(Retry::class);
if ($retry) {
    echo $retry->times;     // 3
    echo $retry->sleepMs;   // 500
}

// Built-in convenience methods
$method = $request->method();           // 'POST'
$contentType = $request->contentType(); // 'application/json'
$isIdempotent = $request->isIdempotent();
```

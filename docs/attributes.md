---
title: Attributes
description: Configure requests declaratively with PHP 8 attributes for HTTP methods, content types, and more
---

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

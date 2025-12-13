# Overview

## Goals

1. **Lightweight** - Minimal abstraction over Guzzle
2. **Less boilerplate** - Attributes over traits, single base class
3. **Lenient by default** - Opt-in error throwing via `#[ThrowOnError]`
4. **Type-safe** - Full PHP 8.x support with generics where possible
5. **Explicit** - No magic; `body()` and `query()` are always defined

## Core Principles

- Guzzle is the engine, we're just the steering wheel
- Attributes for declaration, methods for logic
- Sensible defaults that match 90% of use cases
- Easy to extend for the 10%

## Architecture Overview

```
Connector (base URL, auth, middleware)
    └── Request (endpoint, method, body format via attributes)
            └── Response (typed, validated)
```

## Documentation Index

### Core
- [01-requests.md](01-requests.md) - Request classes, attributes, examples
- [02-connectors.md](02-connectors.md) - Connector configuration
- [03-responses.md](03-responses.md) - Response handling and DTO mapping
- [04-authentication.md](04-authentication.md) - Auth strategies and OAuth2

### Features
- [05-pagination.md](05-pagination.md) - Pagination strategies
- [06-caching.md](06-caching.md) - Caching and invalidation
- [07-rate-limiting.md](07-rate-limiting.md) - Rate limiting
- [08-resilience.md](08-resilience.md) - Retry, timeout, circuit breaker
- [09-pooling.md](09-pooling.md) - Request pooling
- [10-debugging.md](10-debugging.md) - Debugging and logging
- [11-files.md](11-files.md) - Multipart uploads and streaming

### Network
- [12-proxy.md](12-proxy.md) - Proxy configuration
- [13-http-clients.md](13-http-clients.md) - HTTP client abstraction
- [14-connection-pooling.md](14-connection-pooling.md) - Connection pooling
- [15-dns.md](15-dns.md) - DNS configuration

### Observability
- [16-tracing.md](16-tracing.md) - Request tracing and OpenTelemetry
- [17-telemetry.md](17-telemetry.md) - Metrics and event hooks
- [18-response-hooks.md](18-response-hooks.md) - Response transformers
- [19-conditional-requests.md](19-conditional-requests.md) - ETag/Last-Modified support

### Infrastructure
- [20-testing.md](20-testing.md) - Fakes, mocks, record/replay, OAuth2 testing
- [21-middleware.md](21-middleware.md) - Connector middleware system
- [22-sdk-example.md](22-sdk-example.md) - Full SDK example
- [23-package-structure.md](23-package-structure.md) - Package structure

### Extensibility
- [24-request-middleware.md](24-request-middleware.md) - Per-request middleware
- [25-macros.md](25-macros.md) - Macroable Request/Connector/Response
- [26-idempotency.md](26-idempotency.md) - Idempotency keys for safe retries
- [27-signing.md](27-signing.md) - HMAC and AWS Signature V4
- [28-graphql.md](28-graphql.md) - GraphQL support (draft)
- [29-serialization.md](29-serialization.md) - Request/response serialization

### Migration
- [99-migration.md](99-migration.md) - Migration from Saloon

## Open Questions

1. **Naming** - What should the package be called?
2. **Laravel Integration** - Separate package or built-in facades/service provider?
3. **Async Support** - Guzzle promises or separate async API?

## Future Features

Features to add later:

1. **Resource Classes** - Base `Resource` class for grouping related requests (e.g., `$connector->customers()->create()`)
2. **DTO Auto-mapping** - Response → DTO via constructor or `createDtoFromResponse` method
3. **Webhook Verification** - Signature validation for incoming webhooks
4. **SDK Generator** - CLI tool to scaffold from OpenAPI specs

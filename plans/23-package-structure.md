# Package Structure

```
src/
├── Connector.php
├── Request.php
├── Response.php
├── Attributes/
│   ├── Methods/
│   │   ├── Get.php
│   │   ├── Post.php
│   │   ├── Put.php
│   │   ├── Patch.php
│   │   ├── Delete.php
│   │   ├── Head.php
│   │   └── Options.php
│   ├── ContentTypes/
│   │   ├── Json.php
│   │   ├── Form.php
│   │   ├── Multipart.php
│   │   └── Xml.php
│   ├── Protocols/
│   │   ├── JsonRpc.php
│   │   ├── XmlRpc.php
│   │   ├── Soap.php
│   │   └── GraphQL.php
│   ├── Pagination/
│   │   ├── Pagination.php
│   │   ├── SimplePagination.php
│   │   ├── CursorPagination.php
│   │   ├── OffsetPagination.php
│   │   └── LinkPagination.php
│   ├── Caching/
│   │   ├── Cache.php
│   │   ├── NoCache.php
│   │   └── InvalidatesCache.php
│   ├── RateLimiting/
│   │   ├── RateLimit.php
│   │   └── ConcurrencyLimit.php
│   ├── Resilience/
│   │   ├── Retry.php
│   │   ├── Timeout.php
│   │   └── CircuitBreaker.php
│   ├── Network/
│   │   ├── Proxy.php
│   │   └── MaxConnections.php
│   ├── Observability/
│   │   └── Conditional.php
│   ├── Security/
│   │   ├── HmacSignature.php
│   │   ├── AwsSignature.php
│   │   └── Idempotent.php
│   ├── Middleware.php
│   ├── Dto.php
│   ├── ThrowOnError.php
│   └── Stream.php
├── Contracts/
│   ├── Authenticator.php
│   ├── CacheStore.php
│   ├── CircuitBreakerStore.php
│   ├── HttpClient.php
│   ├── IdempotencyStore.php
│   ├── Paginator.php
│   ├── RateLimitStore.php
│   ├── RequestMiddleware.php
│   ├── RequestSigner.php
│   └── Serializable.php
├── Proxy/
│   └── ProxyConfig.php
├── OAuth2/
│   ├── OAuth2Connector.php (trait)
│   ├── OAuth2Config.php
│   ├── OAuth2Tokens.php
│   ├── TokenRefreshInterceptor.php
│   └── Pkce.php
├── Http/
│   ├── GuzzleDriver.php
│   ├── SymfonyDriver.php
│   └── LaravelDriver.php
├── Pool/
│   ├── Pool.php
│   └── PoolResponse.php
├── Attachments/
│   └── Attachment.php
├── Auth/
│   ├── BearerToken.php
│   ├── BasicAuth.php
│   ├── QueryAuth.php
│   ├── HeaderAuth.php
│   └── CallableAuth.php
├── Pagination/
│   ├── CursorPaginator.php
│   ├── PagePaginator.php
│   ├── OffsetPaginator.php
│   └── LinkHeaderPaginator.php
├── Caching/
│   ├── CacheConfig.php
│   ├── LaravelCache.php
│   └── Psr16Cache.php
├── RateLimiting/
│   ├── MemoryStore.php
│   ├── RedisStore.php
│   └── LaravelStore.php
├── Resilience/
│   ├── RetryConfig.php
│   ├── CircuitBreakerConfig.php
│   ├── CircuitState.php
│   ├── MemoryCircuitStore.php
│   ├── RedisCircuitStore.php
│   └── LaravelCircuitStore.php
├── Network/
│   ├── ConnectionPoolConfig.php
│   └── DnsConfig.php
├── Observability/
│   ├── TracingConfig.php
│   ├── EventConfig.php
│   ├── MetricsConfig.php
│   └── ConditionalConfig.php
├── Signing/
│   ├── HmacSigner.php
│   ├── AwsSignatureV4.php
│   └── CustomSigner.php
├── Idempotency/
│   ├── IdempotencyConfig.php
│   ├── MemoryIdempotencyStore.php
│   └── RedisIdempotencyStore.php
├── Middleware/
│   ├── RequestMiddlewareInterface.php
│   ├── RetryMiddleware.php
│   ├── CacheMiddleware.php
│   ├── RateLimitMiddleware.php
│   ├── LogMiddleware.php
│   └── TokenRefreshMiddleware.php
├── Serialization/
│   ├── RequestSerializer.php
│   └── ResponseSerializer.php
├── GraphQL/
│   ├── GraphQLRequest.php
│   ├── GraphQLSubscription.php
│   ├── GraphQLConfig.php
│   └── QueryBuilder.php
├── Exceptions/
│   ├── RequestException.php
│   ├── ClientException.php
│   │   ├── NotFoundException.php
│   │   ├── UnauthorizedException.php
│   │   ├── ForbiddenException.php
│   │   ├── ValidationException.php
│   │   └── RateLimitException.php
│   ├── ServerException.php
│   │   ├── InternalServerException.php
│   │   └── ServiceUnavailableException.php
│   ├── CircuitOpenException.php
│   ├── AttributeConflictException.php
│   └── SerializationException.php
├── Testing/
│   ├── FakeConnector.php
│   ├── MockResponse.php
│   ├── ResponseSequence.php
│   ├── FakeOAuthProvider.php
│   └── ArrayTokenStore.php
├── Support/
│   ├── Resource.php
│   ├── Collection.php
│   └── Macroable.php
└── Laravel/
    ├── ServiceProvider.php
    ├── Facade.php
    └── Commands/
        └── MakeConnectorCommand.php
```

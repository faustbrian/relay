# Connector Middleware

Global middleware that applies to all requests through a connector. Uses Guzzle's HandlerStack directly.

> **Note:** For per-request middleware, see [24-request-middleware.md](24-request-middleware.md).

## Connector vs Request Middleware

| Feature | Connector Middleware | Request Middleware |
|---------|---------------------|-------------------|
| Scope | All requests through connector | Single request class |
| Definition | `middleware()` method on Connector | `#[Middleware]` attribute or `middleware()` method on Request |
| Type | Guzzle HandlerStack | Custom `RequestMiddleware` interface |
| Use case | Global concerns (logging, auth) | Request-specific transformations |

## Connector Middleware

Leverage Guzzle's middleware system directly:

```php
class ApiConnector extends Connector
{
    public function middleware(): HandlerStack
    {
        $stack = HandlerStack::create();

        // Retry on failure
        $stack->push(new RetryMiddleware(
            maxAttempts: 3,
            delay: fn($attempt) => $attempt * 1000,
            when: fn($response) => $response->status() >= 500,
        ));

        // Cache responses
        $stack->push(new CacheMiddleware(
            store: $this->cache,
            ttl: 3600,
            methods: ['GET'],
        ));

        // Rate limiting
        $stack->push(new RateLimitMiddleware(
            requests: 100,
            perSeconds: 60,
        ));

        // Logging
        $stack->push(new LogMiddleware($this->logger));

        return $stack;
    }
}
```

## Execution Order

When both connector and request middleware are defined:

```
1. Connector middleware (in order defined)
2. Request middleware() method
3. Request #[Middleware] attribute
4. HTTP request is sent
5. Response flows back through middleware (reverse order)
```

## When to Use Which

**Use Connector Middleware for:**
- Logging all requests
- Global authentication
- Rate limiting across all endpoints
- Metrics collection
- Error tracking (Sentry, Bugsnag)

**Use Request Middleware for:**
- Request-specific validation
- Response transformation for specific endpoints
- Conditional headers per request type
- Request signing for specific operations

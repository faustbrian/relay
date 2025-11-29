# Request Middleware

Per-request middleware that runs before/after the HTTP call.

## Via Attribute

```php
#[Post, Json, Middleware(LoggingMiddleware::class)]
class CreateUser extends Request
{
    public function endpoint(): string
    {
        return '/users';
    }
}

// Multiple middleware (executed in order)
#[Post, Json, Middleware([
    LoggingMiddleware::class,
    MetricsMiddleware::class,
    ValidationMiddleware::class,
])]
class CreateOrder extends Request { ... }
```

## Middleware Class

```php
class LoggingMiddleware implements RequestMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        Log::debug('Sending request', [
            'endpoint' => $request->endpoint(),
            'body' => $request->body(),
        ]);

        $response = $next($request);

        Log::debug('Received response', [
            'status' => $response->status(),
            'duration' => $response->duration(),
        ]);

        return $response;
    }
}
```

## Request Transformation

```php
class AddCorrelationIdMiddleware implements RequestMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Modify request before sending
        $request->withHeader('X-Correlation-Id', Str::uuid()->toString());

        return $next($request);
    }
}
```

## Response Transformation

```php
class UnwrapDataMiddleware implements RequestMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Modify response after receiving
        return $response->withJson(
            $response->json()['data'] ?? $response->json()
        );
    }
}
```

## Conditional Middleware

```php
#[Get, Json, Middleware(CachingMiddleware::class, when: 'shouldCache')]
class GetUser extends Request
{
    private bool $bypassCache = false;

    public function shouldCache(): bool
    {
        return !$this->bypassCache;
    }

    public function withoutCache(): self
    {
        $this->bypassCache = true;
        return $this;
    }
}
```

## Inline Middleware

```php
#[Get, Json]
class GetUser extends Request
{
    public function middleware(): array
    {
        return [
            function (Request $request, Closure $next): Response {
                // Inline middleware logic
                return $next($request);
            },
        ];
    }
}
```

## Middleware Priority

Order of execution:
1. Connector middleware (global)
2. Request class `middleware()` method
3. `#[Middleware]` attribute middleware

```php
// Connector middleware runs first
class ApiConnector extends Connector
{
    public function middleware(): HandlerStack
    {
        $stack = HandlerStack::create();
        $stack->push(new GlobalLoggingMiddleware());
        return $stack;
    }
}

// Then request middleware
#[Get, Json, Middleware(RequestSpecificMiddleware::class)]
class GetUser extends Request
{
    public function middleware(): array
    {
        return [new ValidationMiddleware()];
    }
}

// Execution order:
// 1. GlobalLoggingMiddleware (connector)
// 2. ValidationMiddleware (method)
// 3. RequestSpecificMiddleware (attribute)
```

## Middleware with Dependencies

```php
class RateLimitMiddleware implements RequestMiddleware
{
    public function __construct(
        private readonly RateLimiter $limiter,
        private readonly string $key,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->limiter->wait($this->key);
        return $next($request);
    }
}

// Via attribute with constructor args
#[Middleware(RateLimitMiddleware::class, args: ['key' => 'api'])]
class GetUser extends Request { ... }

// Or via method for complex setup
class GetUser extends Request
{
    public function middleware(): array
    {
        return [
            new RateLimitMiddleware(
                app(RateLimiter::class),
                'api:' . $this->userId,
            ),
        ];
    }
}
```

# Responses

## Response Class

```php
class Response
{
    public function __construct(
        private readonly ResponseInterface $psrResponse,
    ) {}

    public function status(): int {}
    public function headers(): array {}
    public function body(): string {}

    // Typed accessors
    public function json(): array {}
    public function object(): stdClass {}
    public function collect(): Collection {}  // Laravel integration

    // DTO mapping
    /** @template T */
    public function dto(string $class): T {}

    // Status checks
    public function ok(): bool {}
    public function failed(): bool {}
    public function serverError(): bool {}
    public function clientError(): bool {}

    // Access underlying PSR response
    public function toPsrResponse(): ResponseInterface {}
}
```

## DTO Mapping

Via attribute on the request:

```php
#[Get, Json, Dto(UserDto::class)]
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

// Usage
$user = $connector->send(new GetUser(1))->dto();
// Returns UserDto instance
```

## Collection Mapping

```php
#[Get, Json, Dto(UserDto::class, dataKey: 'data')]
class GetUsers extends Request
{
    public function endpoint(): string
    {
        return '/users';
    }
}

$users = $connector->send(new GetUsers())->dtoCollection();
// Returns Collection<UserDto>
```

## Response Mutation

Create modified copies of responses (immutable pattern):

```php
$response = $connector->send($request);

// Replace JSON body
$modified = $response->withJson(['id' => 1, 'name' => 'Modified']);

// Replace specific JSON key
$modified = $response->withJsonKey('name', 'New Name');

// Replace raw body
$modified = $response->withBody('{"custom": "body"}');

// Replace headers
$modified = $response->withHeaders(['X-Custom' => 'value']);

// Replace single header
$modified = $response->withHeader('X-Custom', 'value');

// Replace status code
$modified = $response->withStatus(201);
```

### Use Cases

**Response Hooks (unwrapping nested data):**
```php
class UnwrapDataHook
{
    public function __invoke(Response $response, Request $request): Response
    {
        return $response->withJson(
            $response->json()['data'] ?? $response->json()
        );
    }
}
```

**Testing (modifying mock responses):**
```php
$response = Response::make(['id' => 1])
    ->withHeader('X-Request-Id', 'test-123')
    ->withStatus(201);
```

**Middleware transformation:**
```php
class AddMetadataMiddleware implements RequestMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        return $response->withJson([
            ...$response->json(),
            '_metadata' => [
                'request_id' => $response->header('X-Request-Id'),
                'duration' => $response->duration(),
            ],
        ]);
    }
}
```

## Additional Response Methods

```php
$response = $connector->send($request);

// Timing
$response->duration();        // Request duration in ms
$response->transferTime();    // Transfer time in ms

// Request context
$response->request();         // Original Request object
$response->effectiveUri();    // Final URL after redirects

// Conditional request support
$response->etag();            // ETag header value
$response->lastModified();    // Last-Modified as Carbon
$response->wasNotModified();  // true if 304 response
$response->fromCache();       // true if from conditional cache

// Tracing
$response->traceId();         // Trace ID if configured
$response->spanId();          // Span ID if configured

// Idempotency
$response->idempotencyKey();       // Key used for request
$response->wasIdempotentReplay();  // true if server cached response

// Serialization
$response->serialize();       // Array for storage
Response::unserialize($data); // Restore from array
$response->toJson();          // JSON string
Response::fromJson($json);    // Restore from JSON
```

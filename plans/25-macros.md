# Macros

Extend Request, Connector, and Response classes with custom methods at runtime.

## Response Macros

```php
// Register macro
Response::macro('isSuccessful', function (): bool {
    return $this->status() >= 200 && $this->status() < 300;
});

Response::macro('toModel', function (string $model): Model {
    return $model::fromArray($this->json());
});

// Usage
$response = $connector->send($request);
$response->isSuccessful(); // true
$user = $response->toModel(User::class);
```

## Request Macros

```php
// Register macro
Request::macro('withTenant', function (string $tenantId): self {
    return $this->withHeader('X-Tenant-Id', $tenantId);
});

Request::macro('debug', function (): self {
    return $this->withHeader('X-Debug', 'true');
});

// Usage
$request = new GetUser(1);
$request->withTenant('acme')->debug();
```

## Connector Macros

```php
// Register macro
Connector::macro('healthCheck', function (): bool {
    return $this->get('/health')->isSuccessful();
});

Connector::macro('withTenant', function (string $tenantId): self {
    $this->defaultHeaders['X-Tenant-Id'] = $tenantId;
    return $this;
});

// Usage
$connector = new ApiConnector($token);
$connector->healthCheck(); // true
$connector->withTenant('acme')->send($request);
```

## Conditional Macros

```php
// Register only if not already defined
Response::macroIf('toArray', function (): array {
    return $this->json();
});

// Check if macro exists
if (Response::hasMacro('toModel')) {
    $user = $response->toModel(User::class);
}
```

## Macros with Parameters

```php
Response::macro('pluck', function (string $key, ?string $default = null): mixed {
    return data_get($this->json(), $key, $default);
});

// Usage
$name = $response->pluck('user.name');
$status = $response->pluck('meta.status', 'unknown');
```

## Mixin Classes

Group related macros into a class:

```php
class ResponseMixin
{
    public function isSuccessful(): Closure
    {
        return fn(): bool => $this->status() >= 200 && $this->status() < 300;
    }

    public function isError(): Closure
    {
        return fn(): bool => $this->status() >= 400;
    }

    public function toCollection(): Closure
    {
        return fn(): Collection => collect($this->json());
    }
}

// Register all macros from mixin
Response::mixin(new ResponseMixin());

// Usage
$response->isSuccessful();
$response->toCollection()->pluck('id');
```

## Service Provider Registration

```php
class ApiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Response macros
        Response::mixin(new ResponseMixin());

        // Request macros
        Request::macro('withCorrelationId', function (): self {
            return $this->withHeader(
                'X-Correlation-Id',
                request()->header('X-Correlation-Id', Str::uuid()->toString())
            );
        });

        // Connector macros
        Connector::macro('forTenant', function (Tenant $tenant): self {
            return $this->withTenant($tenant->id)
                ->withHeader('X-Tenant-Region', $tenant->region);
        });
    }
}
```

## Type Hints with PHPDoc

```php
/**
 * @method bool isSuccessful()
 * @method Collection toCollection()
 * @method mixed pluck(string $key, mixed $default = null)
 */
class Response
{
    use Macroable;
}
```

# Conditional Requests

Built-in ETag/Last-Modified support with automatic 304 handling.

## Via Attribute

```php
#[Get, Json, Conditional]
class GetUser extends Request
{
    public function endpoint(): string
    {
        return "/users/{$this->id}";
    }
}
```

## Usage

```php
// First request - stores ETag/Last-Modified
$response = $connector->send(new GetUser(1));
$response->etag();           // "abc123"
$response->lastModified();   // Carbon instance

// Subsequent request - sends If-None-Match / If-Modified-Since
$response = $connector->send(new GetUser(1));
$response->wasNotModified(); // true if 304
$response->fromCache();      // true if using cached response
```

## Manual Control

```php
#[Get, Json]
class GetUser extends Request
{
    public function __construct(
        private readonly int $id,
        private readonly ?string $etag = null,
    ) {}

    public function headers(): ?array
    {
        return array_filter([
            'If-None-Match' => $this->etag,
        ]);
    }
}

$response = $connector->send(new GetUser(1, etag: '"abc123"'));

if ($response->status() === 304) {
    // Use cached data
}
```

## Connector-Level

```php
class ApiConnector extends Connector
{
    public function conditionalRequests(): ?ConditionalConfig
    {
        return new ConditionalConfig(
            // Automatically handle ETag/Last-Modified
            enabled: true,

            // Store for caching validators
            store: new LaravelStore(app('cache')->store('redis')),

            // Methods to apply to
            methods: ['GET', 'HEAD'],
        );
    }
}
```

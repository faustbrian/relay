# Debugging

## Request/Response Inspection

```php
// Dump and continue
$response = $connector->send($request->dump());

// Dump and die
$response = $connector->send($request->dd());

// Dump with Laravel's dump()
$response = $connector->send($request->dump()); // Uses dump()
$response = $connector->send($request->ddd());  // Uses ddd()

// Response debugging
$response = $connector->send($request);
$response->dump();  // Dump response
$response->dd();    // Dump and die

// Debug both request and response
$connector->debug()->send($request);
```

## Debug Output

```php
$request->dump();
// Outputs:
// ┌─ Request ─────────────────────────────────────
// │ POST https://api.example.com/users
// │
// │ Headers:
// │   Content-Type: application/json
// │   Authorization: Bearer ***REDACTED***
// │
// │ Body:
// │   {"name": "John", "email": "john@example.com"}
// └───────────────────────────────────────────────

$response->dump();
// Outputs:
// ┌─ Response ────────────────────────────────────
// │ 201 Created (234ms)
// │
// │ Headers:
// │   Content-Type: application/json
// │   X-Request-Id: abc123
// │
// │ Body:
// │   {"id": 1, "name": "John", "email": "john@example.com"}
// └───────────────────────────────────────────────
```

## Sensitive Data Redaction

```php
class ApiConnector extends Connector
{
    // Headers to redact in debug output
    protected array $sensitiveHeaders = [
        'Authorization',
        'X-API-Key',
        'Cookie',
    ];

    // Body keys to redact
    protected array $sensitiveBodyKeys = [
        'password',
        'secret',
        'token',
        'credit_card',
    ];
}
```

## Logging

```php
// PSR-3 logger integration
class ApiConnector extends Connector
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function logger(): ?LoggerInterface
    {
        return $this->logger;
    }

    // Log level configuration
    public function logLevel(): string
    {
        return 'debug'; // debug, info, warning, error
    }

    // What to log
    public function logContext(): array
    {
        return ['request', 'response', 'timing'];
    }
}

// Laravel automatic logging
class ApiConnector extends Connector
{
    public function logger(): ?LoggerInterface
    {
        return app('log')->channel('api');
    }
}
```

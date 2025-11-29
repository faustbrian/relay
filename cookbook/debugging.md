# Debugging

Relay provides comprehensive debugging tools to help you understand, troubleshoot, and diagnose issues with your API integrations.

## Overview

Debugging features in Relay:
- `Debugger` for formatted request/response output
- `CurlDumper` to convert requests to curl commands
- `HurlDumper` to convert requests to Hurl format
- `LoggingMiddleware` for request/response logging
- Sensitive data redaction

## Debugger

### Formatting Requests

```php
use Cline\Relay\Observability\Debugging\Debugger;

$debugger = new Debugger();
$request = new CreateUserRequest('john@example.com', 'John Doe');

echo $debugger->formatRequest($request, 'https://api.example.com');
```

Output:
```
┌─ Request ─────────────────────────────────────
│ POST https://api.example.com/users
│ Query: page=1&limit=10
│
│ Headers:
│   Content-Type: application/json
│   Authorization: ***REDACTED***
│
│ Body:
│   {
│       "email": "john@example.com",
│       "name": "John Doe"
│   }
└───────────────────────────────────────────────
```

### Formatting Responses

```php
$response = $connector->send(new GetUserRequest(1));

echo $debugger->formatResponse($response);
```

Output:
```
┌─ Response ────────────────────────────────────
│ 200 OK (125ms)
│
│ Headers:
│   Content-Type: application/json
│   X-Request-Id: abc123
│
│ Body:
│   {
│       "id": 1,
│       "name": "John Doe",
│       "email": "john@example.com"
│   }
└───────────────────────────────────────────────
```

### Sensitive Data Redaction

Debugger automatically redacts sensitive information:

```php
$debugger = new Debugger();

// Default sensitive headers
// Authorization, X-API-Key, Cookie, Set-Cookie

// Default sensitive body keys
// password, secret, token, api_key, access_token, credit_card, cvv, ssn
```

### Custom Sensitive Fields

```php
$debugger = new Debugger();

// Set custom sensitive headers
$debugger->setSensitiveHeaders([
    'Authorization',
    'X-Custom-Secret',
    'X-Internal-Token',
]);

// Set custom sensitive body keys
$debugger->setSensitiveBodyKeys([
    'password',
    'pin',
    'social_security',
    'bank_account',
]);
```

## CurlDumper

Convert requests to curl commands for testing in terminal.

### Basic Usage

```php
use Cline\Relay\Observability\Debugging\CurlDumper;

$dumper = new CurlDumper();
$request = new GetUserRequest(1);

$curlCommand = $dumper->dump($request, 'https://api.example.com');

echo $curlCommand;
// curl -H 'Accept: application/json' 'https://api.example.com/users/1'
```

### POST Request

```php
$request = new CreateUserRequest(['name' => 'John', 'email' => 'john@example.com']);

$curlCommand = $dumper->dump($request, 'https://api.example.com');

// curl -X POST -H 'Content-Type: application/json' -d '{"name":"John","email":"john@example.com"}' 'https://api.example.com/users'
```

### Multi-line Format

For complex requests, use multi-line format:

```php
$curlCommand = $dumper->dumpMultiline($request, 'https://api.example.com');
```

Output:
```bash
curl \
  -X POST \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer token123' \
  -d '{"name":"John"}' \
  'https://api.example.com/users'
```

### CurlDumper Options

```php
$dumper = new CurlDumper();

// Enable gzip compression
$dumper->compressed();

// Disable SSL verification (for testing)
$dumper->insecure();

// Enable verbose output
$dumper->verbose();

// Enable silent mode
$dumper->silent();

// Follow redirects
$dumper->followRedirects()
       ->maxRedirects(5);

// Set timeouts
$dumper->timeout(30)
       ->connectTimeout(10);

$curlCommand = $dumper->dump($request, $baseUrl);
```

### Chained Configuration

```php
$curlCommand = (new CurlDumper())
    ->verbose()
    ->compressed()
    ->timeout(60)
    ->dump($request, 'https://api.example.com');
```

## HurlDumper

Convert requests to [Hurl](https://hurl.dev/) format for testing.

### Basic Usage

```php
use Cline\Relay\Observability\Debugging\HurlDumper;

$dumper = new HurlDumper();
$request = new GetUserRequest(1);

$hurlContent = $dumper->dump($request, 'https://api.example.com');
```

Output:
```hurl
GET https://api.example.com/users/1
Accept: application/json
```

### POST with Body

```php
$request = new CreateUserRequest(['name' => 'John']);

$hurlContent = $dumper->dump($request, 'https://api.example.com');
```

Output:
```hurl
POST https://api.example.com/users
Content-Type: application/json
{
    "name": "John"
}
```

### With Query Parameters

```php
$request = new SearchRequest('test', page: 2);

$hurlContent = $dumper->dump($request, 'https://api.example.com');
```

Output:
```hurl
GET https://api.example.com/search
[QueryStringParams]
q: test
page: 2
```

### With Basic Auth

```php
$dumper = new HurlDumper();
$dumper->withBasicAuth('username', 'password');

$hurlContent = $dumper->dump($request, $baseUrl);
```

Output:
```hurl
GET https://api.example.com/protected
[BasicAuth]
username: password
```

### Multiple Requests

```php
$requests = [
    ['request' => new GetUserRequest(1), 'baseUrl' => $baseUrl],
    ['request' => new GetOrdersRequest(1), 'baseUrl' => $baseUrl],
];

$hurlContent = $dumper->dumpMultiple($requests);
```

### Pretty Print Control

```php
// Disable JSON pretty printing
$dumper->prettyPrintJson(false);
```

## LoggingMiddleware

Log all requests and responses using PSR-3 loggers.

### Basic Setup

```php
use Cline\Relay\Features\Middleware\LoggingMiddleware;
use Psr\Log\LoggerInterface;

class MyConnector extends Connector
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function middleware(): array
    {
        return [
            new LoggingMiddleware($this->logger),
        ];
    }
}
```

### With Body Logging

```php
new LoggingMiddleware(
    logger: $this->logger,
    logRequestBody: true,
    logResponseBody: true,
);
```

### Log Output

Request log:
```
[info] HTTP Request {"method": "POST", "endpoint": "/users", "request_body": {"name": "John"}}
```

Response log:
```
[info] HTTP Response {"method": "POST", "endpoint": "/users", "status": 201, "duration_ms": 125.5}
```

## Debugging Patterns

### Debug Helper Method

Add a debug method to your connector:

```php
class MyConnector extends Connector
{
    private ?Debugger $debugger = null;

    public function debug(bool $enabled = true): self
    {
        $this->debugger = $enabled ? new Debugger() : null;

        return $this;
    }

    public function send(Request $request): Response
    {
        if ($this->debugger !== null) {
            echo $this->debugger->formatRequest($request, $this->resolveBaseUrl());
        }

        $response = parent::send($request);

        if ($this->debugger !== null) {
            echo $this->debugger->formatResponse($response);
        }

        return $response;
    }
}

// Usage
$connector = new MyConnector();
$connector->debug()->send(new GetUserRequest(1));
```

### Request Recording for Debugging

```php
use Cline\Relay\Testing\RequestRecorder;

class DebugConnector extends Connector
{
    private RequestRecorder $recorder;

    public function __construct()
    {
        $this->recorder = new RequestRecorder();
    }

    public function send(Request $request): Response
    {
        $response = parent::send($request);
        $this->recorder->record($request, $response);

        return $response;
    }

    public function dumpHistory(): void
    {
        $debugger = new Debugger();

        foreach ($this->recorder->all() as $record) {
            echo $debugger->formatRequest($record['request'], $this->resolveBaseUrl());
            if ($record['response'] !== null) {
                echo $debugger->formatResponse($record['response']);
            }
            echo "\n";
        }
    }
}
```

### Export Requests for External Testing

```php
class MyConnector extends Connector
{
    public function exportToCurl(Request $request): string
    {
        return (new CurlDumper())
            ->verbose()
            ->dump($request, $this->resolveBaseUrl());
    }

    public function exportToHurl(Request $request): string
    {
        return (new HurlDumper())
            ->dump($request, $this->resolveBaseUrl());
    }
}

// Usage
$connector = new MyConnector();
$request = new CreateUserRequest(['name' => 'John']);

// Copy to clipboard and run in terminal
echo $connector->exportToCurl($request);

// Save to .hurl file
file_put_contents('debug.hurl', $connector->exportToHurl($request));
```

### Conditional Debugging

```php
class MyConnector extends Connector
{
    public function send(Request $request): Response
    {
        if (config('app.debug')) {
            $this->logRequest($request);
        }

        $response = parent::send($request);

        if (config('app.debug')) {
            $this->logResponse($response);
        }

        return $response;
    }

    private function logRequest(Request $request): void
    {
        Log::debug('API Request', [
            'method' => $request->method(),
            'endpoint' => $request->endpoint(),
            'query' => $request->allQuery(),
            'body' => $request->body(),
        ]);
    }

    private function logResponse(Response $response): void
    {
        Log::debug('API Response', [
            'status' => $response->status(),
            'duration' => $response->duration(),
            'body' => $response->json(),
        ]);
    }
}
```

### Debugging Failed Requests

```php
try {
    $response = $connector->send(new CreateUserRequest($data));
} catch (RequestException $e) {
    $debugger = new Debugger();

    // Log the failed request
    Log::error('API Request Failed', [
        'request' => $debugger->formatRequest($e->request(), $connector->resolveBaseUrl()),
        'response' => $e->response() ? $debugger->formatResponse($e->response()) : 'No response',
        'exception' => $e->getMessage(),
    ]);

    // Export as curl for manual testing
    $curl = (new CurlDumper())->dump($e->request(), $connector->resolveBaseUrl());
    Log::debug('Curl command: ' . $curl);

    throw $e;
}
```

## Best Practices

### 1. Never Log Sensitive Data in Production

```php
new LoggingMiddleware(
    logger: $this->logger,
    logRequestBody: app()->environment('local'),
    logResponseBody: app()->environment('local'),
);
```

### 2. Use Debugger Redaction

```php
$debugger = new Debugger();
$debugger->setSensitiveHeaders(['Authorization', 'X-API-Key']);
$debugger->setSensitiveBodyKeys(['password', 'token', 'secret']);
```

### 3. Structured Logging

```php
Log::channel('api')->info('Request', [
    'method' => $request->method(),
    'endpoint' => $request->endpoint(),
    'correlation_id' => $this->correlationId,
]);
```

### 4. Debug Mode Toggle

```php
class MyConnector extends Connector
{
    private bool $debugMode = false;

    public function debug(bool $enabled = true): self
    {
        $this->debugMode = $enabled;

        return $this;
    }
}

// Only in development
if (app()->environment('local')) {
    $connector->debug();
}
```

## Full Example

Complete debugging setup:

```php
<?php

namespace App\Http\Connectors;

use Cline\Relay\Core\Connector;
use Cline\Relay\Observability\Debugging\CurlDumper;
use Cline\Relay\Observability\Debugging\Debugger;
use Cline\Relay\Observability\Debugging\HurlDumper;
use Cline\Relay\Features\Middleware\LoggingMiddleware;
use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;
use Psr\Log\LoggerInterface;

class DebuggableConnector extends Connector
{
    private Debugger $debugger;
    private bool $debugEnabled = false;

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
        $this->debugger = new Debugger();
        $this->debugger->setSensitiveHeaders([
            'Authorization',
            'X-API-Key',
        ]);
    }

    public function baseUrl(): string
    {
        return 'https://api.example.com/v1';
    }

    public function middleware(): array
    {
        return [
            new LoggingMiddleware(
                logger: $this->logger,
                logRequestBody: config('app.debug'),
                logResponseBody: config('app.debug'),
            ),
        ];
    }

    public function debug(bool $enabled = true): self
    {
        $this->debugEnabled = $enabled;

        return $this;
    }

    public function send(Request $request): Response
    {
        if ($this->debugEnabled) {
            echo $this->debugger->formatRequest($request, $this->resolveBaseUrl());
            echo "\n";
        }

        $response = parent::send($request);

        if ($this->debugEnabled) {
            echo $this->debugger->formatResponse($response);
            echo "\n";
        }

        return $response;
    }

    public function toCurl(Request $request): string
    {
        return (new CurlDumper())
            ->verbose()
            ->compressed()
            ->dump($request, $this->resolveBaseUrl());
    }

    public function toHurl(Request $request): string
    {
        return (new HurlDumper())
            ->dump($request, $this->resolveBaseUrl());
    }
}
```

Usage:

```php
$connector = new DebuggableConnector($logger);

// Enable debug output
$connector->debug();

// Send request with formatted output
$response = $connector->send(new GetUserRequest(1));

// Get curl command
$curl = $connector->toCurl(new CreateUserRequest($data));
echo $curl;

// Get Hurl format
$hurl = $connector->toHurl(new CreateUserRequest($data));
file_put_contents('request.hurl', $hurl);
```

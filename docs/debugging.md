---
title: Debugging
description: Debug tools, request inspection, and export utilities for Relay
---

Relay provides comprehensive debugging tools to help you understand, troubleshoot, and diagnose issues with your API integrations.

## Overview

Debugging features in Relay:
- Built-in `debug()`, `debugRequest()`, `debugResponse()` methods
- `Wiretap` for debugging all requests across connectors
- `Debugger` for formatted request/response output
- `CurlDumper` to convert requests to curl commands
- `HurlDumper` to convert requests to Hurl format
- `LoggingMiddleware` for request/response logging
- Sensitive data redaction

## Quick Start

### Connector-Level Debugging

```php
// Debug both request and response
$connector->debug()->send($request);

// Debug only the request
$connector->debugRequest()->send($request);

// Debug only the response
$connector->debugResponse()->send($request);

// Debug and terminate after response
$connector->debug(die: true)->send($request);
```

### Request-Level Debugging

```php
// Debug a specific request (takes precedence over connector)
$connector->send($request->debug());

// Debug only this request's response
$connector->send($request->debugResponse());
```

### Global Debugging

Debug all requests across all connectors:

```php
use Cline\Relay\Observability\Debugging\Wiretap;

// Debug all requests globally
Wiretap::enable();

// Stop debugging
Wiretap::disable();
```

## Custom Debug Handlers

### With Ray

```php
use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

// Debug requests with Ray
$connector->debugRequest(function (Request $request, RequestInterface $psrRequest) {
    ray($psrRequest);
});

// Debug responses with Ray
$connector->debugResponse(function (Response $response, ResponseInterface $psrResponse) {
    ray($psrResponse);
});
```

### Global Custom Handlers

```php
use Cline\Relay\Observability\Debugging\Wiretap;

// Debug all requests with Ray
Wiretap::requests(function (Request $request, RequestInterface $psrRequest) {
    ray('Request', [
        'method' => $psrRequest->getMethod(),
        'uri' => (string) $psrRequest->getUri(),
        'headers' => $psrRequest->getHeaders(),
    ]);
});

// Debug all responses with Ray
Wiretap::responses(function (Response $response, ResponseInterface $psrResponse) {
    ray('Response', [
        'status' => $response->status(),
        'body' => $response->json(),
        'duration' => $response->duration(),
    ]);
});
```

## Debugger Class

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

### Sensitive Data Redaction

Debugger automatically redacts sensitive information:

```php
$debugger = new Debugger();

// Default sensitive headers: Authorization, X-API-Key, Cookie, Set-Cookie
// Default sensitive body keys: password, secret, token, api_key, access_token, credit_card, cvv, ssn
```

### Custom Sensitive Fields

```php
$debugger = new Debugger();

$debugger->setSensitiveHeaders([
    'Authorization',
    'X-Custom-Secret',
]);

$debugger->setSensitiveBodyKeys([
    'password',
    'pin',
    'social_security',
]);
```

## CurlDumper

Convert requests to curl commands for testing in terminal.

```php
use Cline\Relay\Observability\Debugging\CurlDumper;

$dumper = new CurlDumper();
$curlCommand = $dumper->dump($request, 'https://api.example.com');

echo $curlCommand;
// curl -H 'Accept: application/json' 'https://api.example.com/users/1'
```

### CurlDumper Options

```php
$curlCommand = (new CurlDumper())
    ->verbose()
    ->compressed()
    ->insecure()
    ->followRedirects()
    ->timeout(60)
    ->dump($request, 'https://api.example.com');
```

## HurlDumper

Convert requests to [Hurl](https://hurl.dev/) format for testing.

```php
use Cline\Relay\Observability\Debugging\HurlDumper;

$dumper = new HurlDumper();
$hurlContent = $dumper->dump($request, 'https://api.example.com');
```

## LoggingMiddleware

Log all requests and responses using PSR-3 loggers.

```php
use Cline\Relay\Features\Middleware\LoggingMiddleware;

class MyConnector extends Connector
{
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
}
```

## Best Practices

1. **Never log sensitive data in production** - Use environment checks
2. **Use Debugger redaction** - Configure sensitive headers and body keys
3. **Use Wiretap for development** - Enable globally in local environment
4. **Export to curl for manual testing** - Debug external issues

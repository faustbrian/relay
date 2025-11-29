# Middleware

Relay supports Guzzle middleware for intercepting and modifying requests and responses. Middleware can add headers, log requests, track timing, and more.

## Overview

Middleware wraps request/response processing, allowing you to:
- Modify requests before they're sent
- Modify responses after they're received
- Log request/response data
- Track timing and metrics
- Handle errors and retries

## Built-in Middleware

### Header Middleware

Add headers to every request:

```php
use Cline\Relay\Features\Middleware\HeaderMiddleware;
use GuzzleHttp\HandlerStack;

class MyConnector extends Connector
{
    public function middleware(): HandlerStack
    {
        $stack = HandlerStack::create();

        $stack->push(new HeaderMiddleware([
            'X-Api-Version' => '2024-01',
            'X-Client-Name' => 'MyApp',
        ]));

        return $stack;
    }
}
```

### Logging Middleware

Log requests and responses:

```php
use Cline\Relay\Features\Middleware\LoggingMiddleware;
use Psr\Log\LoggerInterface;

class MyConnector extends Connector
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function middleware(): HandlerStack
    {
        $stack = HandlerStack::create();

        $stack->push(new LoggingMiddleware($this->logger));

        return $stack;
    }
}
```

Log output:

```
[INFO] HTTP Request: GET https://api.example.com/users
[INFO] HTTP Response: 200 OK (125ms)
```

### Timing Middleware

Track request duration:

```php
use Cline\Relay\Features\Middleware\TimingMiddleware;

class MyConnector extends Connector
{
    public function middleware(): HandlerStack
    {
        $stack = HandlerStack::create();

        $stack->push(new TimingMiddleware(function (float $duration, $request, $response) {
            // Duration in milliseconds
            metrics()->timing('api.request', $duration);
        }));

        return $stack;
    }
}
```

## Middleware Pipeline

Compose multiple middleware using the pipeline:

```php
use Cline\Relay\Features\Middleware\MiddlewarePipeline;
use Cline\Relay\Features\Middleware\HeaderMiddleware;
use Cline\Relay\Features\Middleware\LoggingMiddleware;
use Cline\Relay\Features\Middleware\TimingMiddleware;

class MyConnector extends Connector
{
    public function middleware(): HandlerStack
    {
        $pipeline = new MiddlewarePipeline();

        $pipeline->push(new HeaderMiddleware([
            'X-Request-ID' => fn () => uniqid(),
        ]));

        $pipeline->push(new LoggingMiddleware($this->logger));

        $pipeline->push(new TimingMiddleware(function ($duration) {
            $this->recordTiming($duration);
        }));

        return $pipeline->toHandlerStack();
    }
}
```

## Guzzle Middleware

Use Guzzle's built-in middleware:

### Retry Middleware

```php
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;

public function middleware(): HandlerStack
{
    $stack = HandlerStack::create();

    $stack->push(Middleware::retry(
        decider: function ($retries, $request, $response, $exception) {
            // Retry on connection errors
            if ($exception instanceof ConnectException) {
                return $retries < 3;
            }

            // Retry on 5xx errors
            if ($response && $response->getStatusCode() >= 500) {
                return $retries < 3;
            }

            return false;
        },
        delay: function ($retries) {
            return $retries * 1000; // Exponential backoff in ms
        }
    ));

    return $stack;
}
```

### History Middleware

Track request history:

```php
use GuzzleHttp\Middleware;

$history = [];

public function middleware(): HandlerStack
{
    $stack = HandlerStack::create();

    $stack->push(Middleware::history($this->history));

    return $stack;
}

// Access history after requests
public function getHistory(): array
{
    return $this->history;
}
```

### Map Request Middleware

Modify requests:

```php
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;

$stack->push(Middleware::mapRequest(function (RequestInterface $request) {
    return $request->withHeader('X-Timestamp', (string) time());
}));
```

### Map Response Middleware

Modify responses:

```php
use GuzzleHttp\Middleware;
use Psr\Http\Message\ResponseInterface;

$stack->push(Middleware::mapResponse(function (ResponseInterface $response) {
    // Add custom header to response
    return $response->withHeader('X-Processed', 'true');
}));
```

## Custom Middleware

Create custom middleware as a callable:

```php
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Promise\PromiseInterface;

class SignatureMiddleware
{
    public function __construct(
        private readonly string $secretKey,
    ) {}

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
            // Modify request before sending
            $signature = $this->sign($request);
            $request = $request->withHeader('X-Signature', $signature);

            // Call next handler
            return $handler($request, $options)->then(
                function (ResponseInterface $response) use ($request) {
                    // Modify response after receiving
                    return $response->withHeader('X-Request-ID', $request->getHeaderLine('X-Request-ID'));
                }
            );
        };
    }

    private function sign(RequestInterface $request): string
    {
        $payload = $request->getMethod() . $request->getUri()->getPath();
        return hash_hmac('sha256', $payload, $this->secretKey);
    }
}

// Usage
$stack->push(new SignatureMiddleware('secret-key'));
```

## Middleware Order

Middleware executes in the order they're added:

```php
$stack = HandlerStack::create();

// 1. First: Add headers
$stack->push(new HeaderMiddleware(['X-Api-Key' => $apiKey]));

// 2. Second: Sign request (after headers are added)
$stack->push(new SignatureMiddleware($secret));

// 3. Third: Log the final request
$stack->push(new LoggingMiddleware($logger));

// 4. Fourth: Track timing
$stack->push(new TimingMiddleware($callback));

// Execution order:
// Request:  Headers → Sign → Log → Timing → [HTTP] →
// Response: ← Timing ← Log ← Sign ← Headers
```

Use named middleware for insertion control:

```php
$stack->push(new LoggingMiddleware($logger), 'logging');
$stack->push(new TimingMiddleware($callback), 'timing');

// Insert before specific middleware
$stack->before('logging', new HeaderMiddleware($headers), 'headers');

// Insert after specific middleware
$stack->after('headers', new SignatureMiddleware($secret), 'signature');
```

## Common Patterns

### Request ID Tracking

```php
class RequestIdMiddleware
{
    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            $requestId = uniqid('req_', true);

            $request = $request->withHeader('X-Request-ID', $requestId);

            return $handler($request, $options)->then(
                function (ResponseInterface $response) use ($requestId) {
                    return $response->withHeader('X-Request-ID', $requestId);
                }
            );
        };
    }
}
```

### Rate Limit Tracking

```php
class RateLimitMiddleware
{
    private int $remaining = 0;
    private int $reset = 0;

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            return $handler($request, $options)->then(
                function (ResponseInterface $response) {
                    $this->remaining = (int) $response->getHeaderLine('X-RateLimit-Remaining');
                    $this->reset = (int) $response->getHeaderLine('X-RateLimit-Reset');

                    if ($this->remaining < 10) {
                        logger()->warning('Rate limit running low', [
                            'remaining' => $this->remaining,
                            'reset' => $this->reset,
                        ]);
                    }

                    return $response;
                }
            );
        };
    }

    public function getRemaining(): int
    {
        return $this->remaining;
    }
}
```

### Error Transformation

```php
class ErrorMiddleware
{
    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            return $handler($request, $options)->then(
                function (ResponseInterface $response) use ($request) {
                    if ($response->getStatusCode() >= 400) {
                        $body = json_decode($response->getBody()->getContents(), true);

                        throw new ApiException(
                            $body['error']['message'] ?? 'Unknown error',
                            $response->getStatusCode()
                        );
                    }

                    return $response;
                }
            );
        };
    }
}
```

### Caching Middleware

```php
class CacheMiddleware
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly int $ttl = 3600,
    ) {}

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            // Only cache GET requests
            if ($request->getMethod() !== 'GET') {
                return $handler($request, $options);
            }

            $key = $this->getCacheKey($request);

            // Check cache
            if ($this->cache->has($key)) {
                return new FulfilledPromise($this->cache->get($key));
            }

            // Make request and cache response
            return $handler($request, $options)->then(
                function (ResponseInterface $response) use ($key) {
                    if ($response->getStatusCode() === 200) {
                        $this->cache->set($key, $response, $this->ttl);
                    }
                    return $response;
                }
            );
        };
    }

    private function getCacheKey(RequestInterface $request): string
    {
        return 'http_' . md5($request->getUri()->__toString());
    }
}
```

## Full Example

Complete connector with middleware stack:

```php
<?php

namespace App\Http\Connectors;

use Cline\Relay\Core\Connector;
use Cline\Relay\Features\Middleware\HeaderMiddleware;
use Cline\Relay\Features\Middleware\LoggingMiddleware;
use Cline\Relay\Features\Middleware\TimingMiddleware;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Log\LoggerInterface;

class ApiConnector extends Connector
{
    private array $history = [];

    public function __construct(
        private readonly string $apiKey,
        private readonly LoggerInterface $logger,
    ) {}

    public function baseUrl(): string
    {
        return 'https://api.example.com/v1';
    }

    public function middleware(): HandlerStack
    {
        $stack = HandlerStack::create();

        // Add standard headers
        $stack->push(new HeaderMiddleware([
            'X-Api-Key' => $this->apiKey,
            'X-Client-Version' => '1.0.0',
        ]), 'headers');

        // Add request ID
        $stack->push(function (callable $handler) {
            return function ($request, $options) use ($handler) {
                $request = $request->withHeader('X-Request-ID', uniqid('req_'));
                return $handler($request, $options);
            };
        }, 'request_id');

        // Log requests
        $stack->push(new LoggingMiddleware($this->logger), 'logging');

        // Track timing
        $stack->push(new TimingMiddleware(function ($duration) {
            $this->logger->debug("Request took {$duration}ms");
        }), 'timing');

        // Retry on failure
        $stack->push(Middleware::retry(
            function ($retries, $request, $response, $exception) {
                return $retries < 3 && (
                    $exception !== null ||
                    ($response && $response->getStatusCode() >= 500)
                );
            },
            function ($retries) {
                return $retries * 500;
            }
        ), 'retry');

        // Track history (for debugging)
        $stack->push(Middleware::history($this->history), 'history');

        return $stack;
    }

    public function getRequestHistory(): array
    {
        return $this->history;
    }
}
```

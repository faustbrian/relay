---
title: Responses
description: Parse, validate, and transform HTTP responses with typed accessors and convenience methods
---

The Response class wraps HTTP responses with typed accessors and convenience methods for parsing, validation, and transformation.

## Basic Response Handling

```php
$response = $connector->send(new GetUserRequest(1));
```

## Status Checking

```php
$status = $response->status(); // 200, 404, 500, etc.

$response->ok();          // true if 2xx
$response->successful();  // alias for ok()
$response->redirect();    // true if 3xx
$response->clientError(); // true if 4xx
$response->serverError(); // true if 5xx
$response->failed();      // true if 4xx or 5xx
```

## Parsing Response Body

### As JSON Array

```php
$data = $response->json();
$name = $response->json('user.name');
$email = $response->json('data.0.email');
$role = $response->json('user.role') ?? 'guest';
```

### As Object

```php
$object = $response->object();
echo $object->name;
```

### As Collection

```php
$collection = $response->collect();
$users = $response->collect('data.users');

$activeUsers = $response->collect('users')
    ->where('active', true)
    ->pluck('email');
```

### As Raw String

```php
$body = $response->body();
```

## Mapping to DTOs

### Single DTO

```php
class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
    ) {}
}

$user = $response->dto(User::class);
```

### Collection of DTOs

```php
$users = $response->dtoCollection(User::class);
$users = $response->dtoCollection(User::class, 'data.users');
```

## Headers

```php
$contentType = $response->header('Content-Type');
$rateLimit = $response->header('X-RateLimit-Remaining');
$headers = $response->headers();
```

## Error Handling

### Throw on Failure

```php
$response->throw(); // Throws if 4xx or 5xx

$data = $response->throw()->json();
```

### Check Before Processing

```php
if ($response->failed()) {
    $error = $response->json('error.message');
    throw new ApiException($error, $response->status());
}
```

## Conditional Requests

```php
$etag = $response->etag();
$lastModified = $response->lastModified();

if ($response->wasNotModified()) {
    // Use cached version
}
```

## Caching Information

```php
if ($response->fromCache()) {
    // Response was served from cache
}
```

## Timing

```php
$durationMs = $response->duration();
```

## Rate Limit Information

```php
$rateLimit = $response->rateLimit();

if ($rateLimit) {
    echo $rateLimit->limit;
    echo $rateLimit->remaining;
    echo $rateLimit->reset;
}
```

## Tracing

```php
$traceId = $response->traceId();
$spanId = $response->spanId();
```

## Idempotency

```php
$key = $response->idempotencyKey();

if ($response->wasIdempotentReplay()) {
    // This response was a replay
}
```

## File Downloads

### Save to File

```php
$response->saveTo('/path/to/file.pdf');
```

### Stream with Progress

```php
$response->streamTo('/path/to/large-file.zip', function ($downloaded, $total) {
    $percent = $total > 0 ? ($downloaded / $total) * 100 : 0;
    echo "Downloaded: {$percent}%\n";
});
```

### Get Filename

```php
$filename = $response->filename();

if ($response->isDownload()) {
    $response->saveTo('/downloads/' . $response->filename());
}
```

### Get as Base64

```php
$base64 = $response->base64();
```

### Iterate Chunks

```php
foreach ($response->chunks(8192) as $chunk) {
    fwrite($handle, $chunk);
}
```

### Get as Stream

```php
$stream = $response->stream();
```

## Response Transformation

### Modify JSON Body

```php
$newResponse = $response->withJson(['modified' => true]);
$newResponse = $response->withJsonKey('processed_at', now()->toIso8601String());
```

### Modify Raw Body

```php
$newResponse = $response->withBody('new raw content');
```

### Modify Headers

```php
$newResponse = $response->withHeaders(['X-Custom' => 'value']);
$newResponse = $response->withHeader('X-Custom', 'value');
```

### Modify Status

```php
$newResponse = $response->withStatus(201);
```

## Accessing Original Request

```php
$request = $response->request();
echo $request->endpoint();
echo $request->method();
```

## PSR-7 Response

```php
$psrResponse = $response->toPsrResponse();
```

## Creating Responses

```php
use Cline\Relay\Core\Response;

$response = Response::make(
    data: ['id' => 1, 'name' => 'John'],
    status: 200,
    headers: ['X-Custom' => 'value'],
);

$response->json('name'); // 'John'
$response->status();     // 200
```

## Debugging

```php
$response->dump(); // Dump and continue
$response->dd();   // Dump and die
```

## Macros

```php
use Cline\Relay\Core\Response;

Response::macro('isRateLimited', function () {
    return $this->status() === 429;
});

if ($response->isRateLimited()) {
    $retryAfter = $response->header('Retry-After');
    sleep((int) $retryAfter);
}
```

## Full Example

```php
$response = $connector->send(new GetOrderRequest($orderId));

if ($response->failed()) {
    if ($response->status() === 404) {
        throw new OrderNotFoundException($orderId);
    }

    throw new ApiException(
        $response->json('error.message'),
        $response->status()
    );
}

logger()->info('API call completed', [
    'duration' => $response->duration(),
    'cached' => $response->fromCache(),
    'trace_id' => $response->traceId(),
]);

$rateLimit = $response->rateLimit();
if ($rateLimit && $rateLimit->remaining < 10) {
    logger()->warning('Rate limit running low', [
        'remaining' => $rateLimit->remaining,
        'reset' => $rateLimit->reset,
    ]);
}

$order = $response->dto(Order::class);
$items = $response->dtoCollection(OrderItem::class, 'items');

if ($response->json('has_attachment')) {
    $attachmentResponse = $connector->send(new GetOrderAttachmentRequest($orderId));

    if ($attachmentResponse->isDownload()) {
        $attachmentResponse->saveTo(
            storage_path('orders/' . $attachmentResponse->filename())
        );
    }
}
```

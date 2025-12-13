# Responses

The Response class wraps HTTP responses with typed accessors and convenience methods for parsing, validation, and transformation.

## Basic Response Handling

After sending a request, you receive a Response object:

```php
$response = $connector->send(new GetUserRequest(1));
```

## Status Checking

Check the HTTP status code:

```php
// Get the status code
$status = $response->status(); // 200, 404, 500, etc.

// Check status ranges
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
// Get entire response as array
$data = $response->json();

// Get specific key using dot notation
$name = $response->json('user.name');
$email = $response->json('data.0.email');

// With default value
$role = $response->json('user.role') ?? 'guest';
```

### As Object

```php
// Get as stdClass
$object = $response->object();
echo $object->name;
echo $object->email;
```

### As Collection

```php
// Get as Laravel Collection
$collection = $response->collect();

// Get nested key as collection
$users = $response->collect('data.users');

// Chain collection methods
$activeUsers = $response->collect('users')
    ->where('active', true)
    ->pluck('email');
```

### As Raw String

```php
$body = $response->body();
```

## Mapping to DTOs

Map responses to Data Transfer Objects:

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
echo $user->name;
```

### Collection of DTOs

```php
// Map array to DTO collection
$users = $response->dtoCollection(User::class);

// Map nested array
$users = $response->dtoCollection(User::class, 'data.users');

// Iterate
foreach ($users as $user) {
    echo $user->email;
}
```

## Headers

Access response headers:

```php
// Get specific header
$contentType = $response->header('Content-Type');
$rateLimit = $response->header('X-RateLimit-Remaining');

// Get all headers
$headers = $response->headers();
// Returns: ['Content-Type' => ['application/json'], ...]
```

## Error Handling

### Throw on Failure

```php
// Throws RuntimeException if response is 4xx or 5xx
$response->throw();

// Chain with other operations
$data = $response->throw()->json();
```

### Check Before Processing

```php
if ($response->failed()) {
    $error = $response->json('error.message');
    throw new ApiException($error, $response->status());
}

$data = $response->json();
```

## Conditional Requests

Handle ETags and Last-Modified headers:

```php
// Get ETag
$etag = $response->etag();

// Get Last-Modified as DateTimeImmutable
$lastModified = $response->lastModified();

// Check if response was 304 Not Modified
if ($response->wasNotModified()) {
    // Use cached version
}
```

## Caching Information

Check if response came from cache:

```php
if ($response->fromCache()) {
    // Response was served from cache
}
```

## Timing

Get request duration:

```php
$durationMs = $response->duration(); // Duration in milliseconds
```

## Rate Limit Information

Parse rate limit headers:

```php
$rateLimit = $response->rateLimit();

if ($rateLimit) {
    echo $rateLimit->limit;      // Total limit
    echo $rateLimit->remaining;  // Remaining requests
    echo $rateLimit->reset;      // Unix timestamp when limit resets
}
```

## Tracing

Access distributed tracing information:

```php
$traceId = $response->traceId();
$spanId = $response->spanId();
```

## Idempotency

Check idempotency information:

```php
$key = $response->idempotencyKey();

if ($response->wasIdempotentReplay()) {
    // This response was a replay of a previous request
}
```

## File Downloads

### Save to File

```php
// Save response body to a file
$response->saveTo('/path/to/file.pdf');
```

### Stream to File with Progress

```php
$response->streamTo('/path/to/large-file.zip', function ($downloaded, $total) {
    $percent = $total > 0 ? ($downloaded / $total) * 100 : 0;
    echo "Downloaded: {$percent}%\n";
});
```

### Get Filename

Extract filename from Content-Disposition header:

```php
$filename = $response->filename(); // 'report.pdf' or null
```

### Check if Download

```php
if ($response->isDownload()) {
    $response->saveTo('/downloads/' . $response->filename());
}
```

### Get as Base64

```php
$base64 = $response->base64();
// Useful for embedding images, etc.
```

### Iterate Chunks

Process large responses in chunks:

```php
foreach ($response->chunks(8192) as $chunk) {
    // Process each chunk
    fwrite($handle, $chunk);
}
```

### Get as Stream

```php
$stream = $response->stream();
// Returns a PHP stream resource
```

## Response Transformation

Create modified responses (immutable):

### Modify JSON Body

```php
// Replace entire body
$newResponse = $response->withJson(['modified' => true]);

// Modify specific key
$newResponse = $response->withJsonKey('processed_at', now()->toIso8601String());
```

### Modify Raw Body

```php
$newResponse = $response->withBody('new raw content');
```

### Modify Headers

```php
// Add/replace headers
$newResponse = $response->withHeaders([
    'X-Custom' => 'value',
    'X-Another' => 'another-value',
]);

// Single header
$newResponse = $response->withHeader('X-Custom', 'value');
```

### Modify Status

```php
$newResponse = $response->withStatus(201);
```

## Accessing Original Request

Get the request that produced this response:

```php
$request = $response->request();
echo $request->endpoint();
echo $request->method();
```

## PSR-7 Response

Get the underlying PSR-7 response:

```php
$psrResponse = $response->toPsrResponse();
```

## Creating Responses

Create responses programmatically (useful for testing):

```php
use Cline\Relay\Core\Response;

// Create from array data
$response = Response::make(
    data: ['id' => 1, 'name' => 'John'],
    status: 200,
    headers: ['X-Custom' => 'value'],
);

// Access as normal
$response->json('name'); // 'John'
$response->status();     // 200
```

## Debugging

Dump response for debugging:

```php
// Dump and continue
$response->dump();

// Dump and die
$response->dd();
```

Output includes:
- Status code
- Headers
- Body (as JSON if parseable, raw otherwise)
- Duration

## Macros

Extend Response with macros:

```php
use Cline\Relay\Core\Response;

Response::macro('isRateLimited', function () {
    return $this->status() === 429;
});

// Usage
if ($response->isRateLimited()) {
    $retryAfter = $response->header('Retry-After');
    sleep((int) $retryAfter);
}
```

## Full Example

Complete response handling:

```php
$response = $connector->send(new GetOrderRequest($orderId));

// Check for errors
if ($response->failed()) {
    if ($response->status() === 404) {
        throw new OrderNotFoundException($orderId);
    }

    throw new ApiException(
        $response->json('error.message'),
        $response->status()
    );
}

// Log timing
logger()->info('API call completed', [
    'duration' => $response->duration(),
    'cached' => $response->fromCache(),
    'trace_id' => $response->traceId(),
]);

// Check rate limits
$rateLimit = $response->rateLimit();
if ($rateLimit && $rateLimit->remaining < 10) {
    logger()->warning('Rate limit running low', [
        'remaining' => $rateLimit->remaining,
        'reset' => $rateLimit->reset,
    ]);
}

// Map to DTO
$order = $response->dto(Order::class);

// Or use collection for nested data
$items = $response->dtoCollection(OrderItem::class, 'items');

// Download attachments if present
if ($response->json('has_attachment')) {
    $attachmentResponse = $connector->send(new GetOrderAttachmentRequest($orderId));

    if ($attachmentResponse->isDownload()) {
        $attachmentResponse->saveTo(
            storage_path('orders/' . $attachmentResponse->filename())
        );
    }
}
```

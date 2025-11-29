# Request Pooling

Send multiple requests concurrently:

```php
// Simple pool - send all at once
$responses = $connector->pool([
    new GetUser(1),
    new GetUser(2),
    new GetUser(3),
]);

// $responses is array keyed by request index
$responses[0]->json(); // User 1
$responses[1]->json(); // User 2

// Named pool
$responses = $connector->pool([
    'john' => new GetUser(1),
    'jane' => new GetUser(2),
]);

$responses['john']->json();

// With concurrency limit
$responses = $connector->pool($requests)->concurrent(5)->send();

// With callback per response (for progress/streaming results)
$connector->pool($requests)
    ->concurrent(10)
    ->onResponse(function (Response $response, Request $request, string $key) {
        // Handle each response as it completes
    })
    ->onError(function (RequestException $e, Request $request, string $key) {
        // Handle failures
    })
    ->send();

// Lazy pool - for large request sets
$connector->pool($requests)
    ->concurrent(5)
    ->lazy() // Returns generator, memory efficient
    ->each(fn($response) => process($response));
```

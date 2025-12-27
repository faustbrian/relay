---
title: Pooling
description: Execute multiple requests concurrently with request pooling in Relay
---

Relay supports concurrent request execution through request pooling, significantly improving performance when dealing with multiple API calls.

## Basic Usage

### Creating a Pool

```php
$connector = new ApiConnector();

$responses = $connector->pool([
    new GetUserRequest(1),
    new GetUserRequest(2),
    new GetUserRequest(3),
])->send();

// Access responses by index
$user1 = $responses[0]->json();
$user2 = $responses[1]->json();
```

### Keyed Requests

Use associative arrays for easier response access:

```php
$responses = $connector->pool([
    'user' => new GetUserRequest(1),
    'orders' => new GetOrdersRequest(1),
    'profile' => new GetProfileRequest(1),
])->send();

$user = $responses['user']->json();
$orders = $responses['orders']->json();
```

## Concurrency Control

```php
// Execute max 10 requests at a time
$responses = $connector->pool($requests)
    ->concurrent(10)
    ->send();
```

The default concurrency is 5. Adjust based on API rate limits and server capacity.

## Response Handling

### Response Callback

Process responses as they complete:

```php
$connector->pool($requests)
    ->onResponse(function (Response $response, Request $request, int|string $key) {
        logger()->info("Request {$key} completed", [
            'status' => $response->status(),
        ]);
        cache()->put("user_{$key}", $response->json());
    })
    ->send();
```

### Error Callback

Handle errors for individual requests:

```php
$connector->pool($requests)
    ->onError(function (RequestException $exception, Request $request, int|string $key) {
        logger()->error("Request {$key} failed", [
            'status' => $exception->status(),
        ]);
    })
    ->send();
```

### Combined Callbacks

```php
$results = [];
$errors = [];

$connector->pool($requests)
    ->concurrent(10)
    ->onResponse(function ($response, $request, $key) use (&$results) {
        $results[$key] = $response->json();
    })
    ->onError(function ($exception, $request, $key) use (&$errors) {
        $errors[$key] = $exception->getMessage();
    })
    ->send();
```

## Memory-Efficient Processing

### Lazy Iteration

```php
$pool = $connector->pool($requests)
    ->concurrent(10)
    ->lazy();

foreach ($pool->iterate() as $key => $response) {
    processResponse($response);
}
```

### Each Method

```php
$connector->pool($requests)
    ->concurrent(5)
    ->each(function (Response $response, int|string $key) {
        $user = User::fromApi($response->json());
        $user->save();
    });
```

## Common Patterns

### Batch Processing

```php
$userIds = range(1, 1000);

foreach (array_chunk($userIds, 100) as $batch) {
    $requests = array_map(
        fn ($id) => new GetUserRequest($id),
        $batch,
    );

    $responses = $connector->pool($requests)
        ->concurrent(20)
        ->send();

    foreach ($responses as $response) {
        processUser($response->json());
    }
}
```

### Parallel API Aggregation

```php
$responses = $connector->pool([
    'user' => new GetUserRequest($userId),
    'orders' => new GetUserOrdersRequest($userId),
    'notifications' => new GetUserNotificationsRequest($userId),
])
->concurrent(4)
->send();

return [
    'user' => $responses['user']->json(),
    'orders' => $responses['orders']->json(),
    'notifications' => $responses['notifications']->json(),
];
```

### Retry Failed Requests

```php
$failedRequests = [];

$responses = $connector->pool($requests)
    ->onError(function ($exception, $request, $key) use (&$failedRequests) {
        if ($exception->status() >= 500) {
            $failedRequests[$key] = $request;
        }
    })
    ->send();

if ($failedRequests !== []) {
    sleep(5);
    $retryResponses = $connector->pool($failedRequests)
        ->concurrent(2)
        ->send();
}
```

### Progress Tracking

```php
$total = count($requests);
$completed = 0;

$connector->pool($requests)
    ->concurrent(10)
    ->onResponse(function () use (&$completed, $total) {
        $completed++;
        echo "\rProgress: " . round(($completed / $total) * 100) . "%";
    })
    ->send();
```

## Best Practices

1. **Size concurrency appropriately** - Consider API rate limits
2. **Use keys for correlation** - Easier to match requests to responses
3. **Handle all error cases** - Both response errors and exceptions
4. **Batch large request sets** - Process in manageable chunks

# Request Pooling

Relay supports concurrent request execution through request pooling. This allows you to send multiple requests simultaneously, significantly improving performance when dealing with multiple API calls.

## Overview

Request pooling in Relay:
- Executes multiple requests concurrently using Guzzle's async capabilities
- Configurable concurrency limits
- Supports callbacks for response and error handling
- Memory-efficient iteration with lazy processing
- Maintains request-response correlation with keyed arrays

## Basic Usage

### Creating a Pool

```php
use Cline\Relay\Core\Request;

$connector = new ApiConnector();

// Create a pool with multiple requests
$responses = $connector->pool([
    new GetUserRequest(1),
    new GetUserRequest(2),
    new GetUserRequest(3),
])->send();

// Access responses by index
$user1 = $responses[0]->json();
$user2 = $responses[1]->json();
$user3 = $responses[2]->json();
```

### Keyed Requests

Use associative arrays for easier response access:

```php
$responses = $connector->pool([
    'user' => new GetUserRequest(1),
    'orders' => new GetOrdersRequest(1),
    'profile' => new GetProfileRequest(1),
])->send();

// Access by key
$user = $responses['user']->json();
$orders = $responses['orders']->json();
$profile = $responses['profile']->json();
```

## Concurrency Control

### Setting Concurrency Limit

```php
// Execute max 10 requests at a time
$responses = $connector->pool($requests)
    ->concurrent(10)
    ->send();
```

### Default Concurrency

The default concurrency is 5. Adjust based on:
- API rate limits
- Server capacity
- Network conditions

```php
// High concurrency for fast APIs
$connector->pool($requests)
    ->concurrent(50)
    ->send();

// Low concurrency for rate-limited APIs
$connector->pool($requests)
    ->concurrent(2)
    ->send();
```

## Response Handling

### Response Callback

Process responses as they complete:

```php
$connector->pool($requests)
    ->onResponse(function (Response $response, Request $request, int|string $key) {
        logger()->info("Request {$key} completed", [
            'status' => $response->status(),
            'endpoint' => $request->endpoint(),
        ]);

        // Process response immediately
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
            'endpoint' => $request->endpoint(),
            'status' => $exception->status(),
            'message' => $exception->getMessage(),
        ]);

        // Queue for retry
        dispatch(new RetryRequestJob($request));
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

// Process results and errors
echo "Successful: " . count($results);
echo "Failed: " . count($errors);
```

## Memory-Efficient Processing

### Lazy Iteration

For large numbers of requests, use lazy iteration:

```php
$pool = $connector->pool($requests)
    ->concurrent(10)
    ->lazy();

// Process responses one at a time
foreach ($pool->iterate() as $key => $response) {
    processResponse($response);
}
```

### Each Method

Process each response with a callback:

```php
$connector->pool($requests)
    ->concurrent(5)
    ->each(function (Response $response, int|string $key) {
        // Process response
        $user = User::fromApi($response->json());
        $user->save();
    });
```

## Common Patterns

### Batch Processing

Process items in batches:

```php
$userIds = range(1, 1000);
$batchSize = 100;

foreach (array_chunk($userIds, $batchSize) as $batch) {
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

Fetch data from multiple endpoints simultaneously:

```php
$connector = new ApiConnector();

$responses = $connector->pool([
    'user' => new GetUserRequest($userId),
    'orders' => new GetUserOrdersRequest($userId),
    'notifications' => new GetUserNotificationsRequest($userId),
    'settings' => new GetUserSettingsRequest($userId),
])
->concurrent(4)
->send();

return [
    'user' => $responses['user']->json(),
    'orders' => $responses['orders']->json(),
    'notifications' => $responses['notifications']->json(),
    'settings' => $responses['settings']->json(),
];
```

### Retry Failed Requests

```php
$failedRequests = [];

$responses = $connector->pool($requests)
    ->onError(function ($exception, $request, $key) use (&$failedRequests) {
        // Track failed requests for retry
        if ($exception->status() >= 500) {
            $failedRequests[$key] = $request;
        }
    })
    ->send();

// Retry failed requests
if ($failedRequests !== []) {
    sleep(5); // Wait before retry

    $retryResponses = $connector->pool($failedRequests)
        ->concurrent(2) // Lower concurrency for retries
        ->send();

    // Merge with original responses
    foreach ($retryResponses as $key => $response) {
        $responses[$key] = $response;
    }
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
        $progress = round(($completed / $total) * 100);
        echo "\rProgress: {$progress}%";
    })
    ->onError(function () use (&$completed, $total) {
        $completed++;
        $progress = round(($completed / $total) * 100);
        echo "\rProgress: {$progress}%";
    })
    ->send();

echo "\nComplete!\n";
```

## Integration with Other Features

### Pooling with Authentication

```php
// Authentication is automatically applied to all pooled requests
$connector = new AuthenticatedApiConnector($apiKey);

$responses = $connector->pool([
    new GetProtectedResource(1),
    new GetProtectedResource(2),
])->send();
```

### Pooling with Rate Limiting

Be mindful of rate limits when pooling:

```php
// Lower concurrency to respect rate limits
$responses = $connector->pool($requests)
    ->concurrent(5)  // Stay within rate limits
    ->send();
```

### Pooling with Timeouts

Pool respects connector-level timeouts:

```php
class MyConnector extends Connector
{
    public function timeout(): int
    {
        return 30; // Each pooled request has 30s timeout
    }

    public function connectTimeout(): int
    {
        return 10;
    }
}
```

## Error Handling

### Partial Failures

Pooled requests can have mixed success/failure:

```php
$responses = [];
$errors = [];

$connector->pool($requests)
    ->onResponse(function ($response, $request, $key) use (&$responses) {
        $responses[$key] = $response;
    })
    ->onError(function ($exception, $request, $key) use (&$errors) {
        $errors[$key] = $exception;
    })
    ->send();

// Handle mixed results
foreach ($responses as $key => $response) {
    if ($response->failed()) {
        // Request succeeded but returned error status
        handleErrorResponse($response);
    } else {
        handleSuccessResponse($response);
    }
}

foreach ($errors as $key => $exception) {
    // Request failed to complete
    handleException($exception);
}
```

### Circuit Breaker Consideration

When pooling to an unreliable service, consider circuit breaker:

```php
try {
    $responses = $connector->pool($requests)
        ->concurrent(5)
        ->onError(function ($e) use (&$failureCount) {
            $failureCount++;
        })
        ->send();

    if ($failureCount > count($requests) * 0.5) {
        // More than 50% failures - service may be down
        CircuitBreaker::open('api_service');
    }
} catch (Exception $e) {
    logger()->error('Pool execution failed completely');
}
```

## Best Practices

### 1. Size Concurrency Appropriately

```php
// Consider API limits
$apiRateLimit = 100; // per second
$avgRequestTime = 0.5; // seconds

// Safe concurrency = rate limit * request time
$safeConcurrency = (int) ($apiRateLimit * $avgRequestTime);

$connector->pool($requests)
    ->concurrent($safeConcurrency)
    ->send();
```

### 2. Use Keys for Correlation

```php
// Good: Easy to correlate
$responses = $connector->pool([
    "user_{$id}" => new GetUserRequest($id),
    "orders_{$id}" => new GetOrdersRequest($id),
])->send();

// Access specific response
$user = $responses["user_{$id}"];
```

### 3. Handle All Error Cases

```php
$connector->pool($requests)
    ->onResponse(function ($response, $request, $key) {
        if ($response->failed()) {
            handleApiError($response, $key);
        } else {
            handleSuccess($response, $key);
        }
    })
    ->onError(function ($exception, $request, $key) {
        handleNetworkError($exception, $key);
    })
    ->send();
```

### 4. Batch Large Request Sets

```php
$allRequests = array_map(fn ($id) => new GetItemRequest($id), $itemIds);

// Process in manageable batches
foreach (array_chunk($allRequests, 100) as $batch) {
    $responses = $connector->pool($batch)
        ->concurrent(20)
        ->send();

    processBatch($responses);

    // Optional: pause between batches
    usleep(100000); // 100ms
}
```

## Full Example

Complete pooling implementation:

```php
<?php

namespace App\Services;

use App\Http\Connectors\ApiConnector;
use Cline\Relay\Core\Response;
use Illuminate\Support\Collection;

class UserSyncService
{
    public function __construct(
        private readonly ApiConnector $connector,
    ) {}

    public function syncUsers(array $userIds): array
    {
        $requests = [];
        foreach ($userIds as $id) {
            $requests["user_{$id}"] = new GetUserRequest($id);
        }

        $results = [
            'synced' => [],
            'failed' => [],
        ];

        $this->connector->pool($requests)
            ->concurrent(10)
            ->onResponse(function (Response $response, $request, $key) use (&$results) {
                $userId = str_replace('user_', '', $key);

                if ($response->ok()) {
                    $this->updateLocalUser($response->json());
                    $results['synced'][] = $userId;
                } else {
                    $results['failed'][] = [
                        'id' => $userId,
                        'status' => $response->status(),
                        'error' => $response->json('error.message'),
                    ];
                }
            })
            ->onError(function ($exception, $request, $key) use (&$results) {
                $userId = str_replace('user_', '', $key);

                $results['failed'][] = [
                    'id' => $userId,
                    'error' => $exception->getMessage(),
                ];

                logger()->error("Failed to sync user {$userId}", [
                    'exception' => $exception->getMessage(),
                ]);
            })
            ->send();

        return $results;
    }

    public function fetchDashboardData(int $userId): array
    {
        $responses = $this->connector->pool([
            'user' => new GetUserRequest($userId),
            'stats' => new GetUserStatsRequest($userId),
            'notifications' => new GetNotificationsRequest($userId, limit: 5),
            'recent_orders' => new GetOrdersRequest($userId, limit: 10),
        ])
        ->concurrent(4)
        ->send();

        return [
            'user' => $responses['user']->json(),
            'stats' => $responses['stats']->json(),
            'notifications' => $responses['notifications']->json('data'),
            'recent_orders' => $responses['recent_orders']->json('data'),
        ];
    }

    private function updateLocalUser(array $userData): void
    {
        User::updateOrCreate(
            ['external_id' => $userData['id']],
            [
                'name' => $userData['name'],
                'email' => $userData['email'],
                'synced_at' => now(),
            ]
        );
    }
}
```

Usage:

```php
$service = new UserSyncService(new ApiConnector());

// Sync multiple users
$results = $service->syncUsers([1, 2, 3, 4, 5]);
echo "Synced: " . count($results['synced']);
echo "Failed: " . count($results['failed']);

// Fetch dashboard data (4 parallel requests)
$dashboard = $service->fetchDashboardData($userId);
return view('dashboard', $dashboard);
```

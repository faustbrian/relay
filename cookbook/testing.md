# Testing

Relay provides comprehensive testing utilities to help you write reliable tests for your API integrations. This includes mock connectors, response factories, and request recording.

## Overview

Testing features in Relay:
- `MockClient` for global mocking with URL pattern matching
- `MockConnector` for simulating API responses
- `MockResponse` factory for creating test responses
- `Fixture` for recording and replaying API responses with redaction
- `MockConfig` for test configuration
- `RequestRecorder` for tracking sent requests
- Assertions for verifying request behavior
- Support for sequential and fixed responses

## MockClient

### Global Mocking

`MockClient` provides global mocking that works anywhere in your application:

```php
use Cline\Relay\Testing\MockClient;
use Cline\Relay\Testing\MockResponse;

beforeEach(function () {
    MockClient::destroyGlobal();
});

afterEach(function () {
    MockClient::destroyGlobal();
});

it('mocks API calls globally', function () {
    MockClient::global([
        MockResponse::json(['id' => 1]),
        MockResponse::json(['id' => 2]),
    ]);

    // Your code can make API calls deep in the stack
    // and they will be intercepted by MockClient
    $result = MyService::fetchData();

    expect($result->id)->toBe(1);

    MockClient::getGlobal()->assertSent('/users');
});
```

### URL Pattern Matching

Match responses to specific URL patterns:

```php
$mockClient = new MockClient([
    // Exact URL match
    'https://api.example.com/users' => MockResponse::json(['users' => []]),

    // Wildcard patterns
    'https://api.example.com/users/*/orders' => MockResponse::json(['orders' => []]),

    // Double wildcard
    '*/api/v1/*' => MockResponse::json(['version' => 'v1']),

    // Contains pattern
    '/users/' => MockResponse::json(['matched' => 'contains']),
]);
```

### Request Class Mapping

Map responses to specific request classes:

```php
use App\Requests\GetUserRequest;
use App\Requests\CreateOrderRequest;

$mockClient = new MockClient([
    GetUserRequest::class => MockResponse::json(['id' => 1, 'name' => 'John']),
    CreateOrderRequest::class => MockResponse::json(['order_id' => 123], 201),
]);
```

### Dynamic Responses with Closures

Generate responses based on request data:

```php
$mockClient = new MockClient([
    GetUserRequest::class => function (Request $request): Response {
        return MockResponse::json([
            'endpoint' => $request->endpoint(),
            'user_id' => $request->query('id'),
        ]);
    },
]);
```

### Sequential Responses

Return different responses for consecutive calls:

```php
$mockClient = new MockClient([
    MockResponse::json(['attempt' => 1]),
    MockResponse::json(['attempt' => 2]),
    MockResponse::json(['attempt' => 3]),
]);

// Add more after creation
$mockClient->addSequenceResponse(MockResponse::json(['attempt' => 4]));
```

### Assertions

Assert requests were made:

```php
// By request class
$mockClient->assertSent(GetUserRequest::class);

// By endpoint
$mockClient->assertSent('/users');

// With closure for complex assertions
$mockClient->assertSent(function (Request $request, Response $response): bool {
    return $request->endpoint() === '/users'
        && $response->json('id') === 1;
});

// Assert NOT sent
$mockClient->assertNotSent('/admin');

// Assert request count
$mockClient->assertSentCount(3);
$mockClient->assertNothingSent();
```

### Request History

Track and inspect all requests:

```php
// Get all sent requests
$requests = $mockClient->sentRequests();

// Get last request
$lastRequest = $mockClient->lastRequest();

// Get full history with request/response pairs
foreach ($mockClient->history() as $entry) {
    $request = $entry['request'];
    $response = $entry['response'];
}

// Reset state
$mockClient->reset();
```

## MockConfig

### Preventing Stray Requests

Ensure no real API calls are made during tests:

```php
use Cline\Relay\Testing\MockConfig;

beforeEach(function () {
    MockConfig::preventStrayRequests();
});

afterEach(function () {
    MockConfig::reset();
});

it('throws when unmocked request is made', function () {
    // No mock configured - will throw MockClientException
    $connector->send(new GetUserRequest(1));
})->throws(MockClientException::class);
```

### Throw on Missing Fixtures (CI Mode)

Prevent fixture recording in CI environments:

```php
// In your test bootstrap or base test case
if (getenv('CI')) {
    MockConfig::throwOnMissingFixtures();
}

// Or explicitly in tests
MockConfig::throwOnMissingFixtures(true);
```

### Custom Fixture Path

```php
MockConfig::setFixturePath('tests/fixtures/api');
```

## Fixture

### Recording and Replaying Responses

Fixtures allow you to record real API responses and replay them in tests:

```php
use Cline\Relay\Testing\Fixture;
use Cline\Relay\Testing\MockClient;

$mockClient = new MockClient([
    GetUserRequest::class => Fixture::make('users/get-user-1'),
]);
```

### Creating Custom Fixtures with Redaction

Extend `Fixture` to define sensitive data redaction:

```php
use Cline\Relay\Testing\Fixture;

class UserFixture extends Fixture
{
    protected function defineSensitiveHeaders(): array
    {
        return [
            'Authorization' => '[REDACTED]',
            'X-Api-Key' => '[API_KEY_REDACTED]',
        ];
    }

    protected function defineSensitiveJsonParameters(): array
    {
        return [
            'password' => '[HIDDEN]',
            'secret' => fn (): string => '[DYNAMIC_' . time() . ']',
            'token' => '[TOKEN_REDACTED]',
        ];
    }

    protected function defineSensitiveRegexPatterns(): array
    {
        return [
            '/sk-[a-zA-Z0-9]+/' => '[API_KEY]',
            '/\d{16}/' => '[CARD_NUMBER]',
        ];
    }
}
```

### Storing Fixtures

```php
$fixture = Fixture::make('users/created');
$fixture->store($response); // Saves to tests/Fixtures/Saloon/users/created.json
```

### Checking Fixture Existence

```php
$fixture = Fixture::make('users/list');

if ($fixture->exists()) {
    // Use cached fixture
}
```

### Fixture File Format

Fixtures are stored as JSON:

```json
{
    "status": 200,
    "headers": {
        "Content-Type": "application/json",
        "X-Request-Id": "abc123"
    },
    "body": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com"
    }
}
```

## MockConnector

### Basic Usage

Replace your real connector with `MockConnector` in tests:

```php
use Cline\Relay\Testing\MockConnector;
use Cline\Relay\Testing\MockResponse;

it('fetches user data', function () {
    $connector = new MockConnector();

    $connector->addResponse(MockResponse::json([
        'id' => 1,
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]));

    $response = $connector->send(new GetUserRequest(1));

    expect($response->json('name'))->toBe('John Doe');
    expect($response->json('email'))->toBe('john@example.com');
});
```

### Sequential Responses

Return different responses for consecutive requests:

```php
$connector = new MockConnector();

$connector->addResponses([
    MockResponse::json(['id' => 1, 'name' => 'User 1']),
    MockResponse::json(['id' => 2, 'name' => 'User 2']),
    MockResponse::json(['id' => 3, 'name' => 'User 3']),
]);

// First request returns User 1
$response1 = $connector->send(new GetUserRequest(1));
expect($response1->json('name'))->toBe('User 1');

// Second request returns User 2
$response2 = $connector->send(new GetUserRequest(2));
expect($response2->json('name'))->toBe('User 2');
```

### Fixed Response

Return the same response for all requests:

```php
$connector = new MockConnector();

$connector->alwaysReturn(MockResponse::json([
    'status' => 'ok',
]));

// All requests return the same response
$response1 = $connector->send(new HealthCheckRequest());
$response2 = $connector->send(new HealthCheckRequest());

expect($response1->json('status'))->toBe('ok');
expect($response2->json('status'))->toBe('ok');
```

### Dynamic Responses

Create responses based on the request:

```php
$connector = new MockConnector();

$connector->addResponse(function (Request $request) {
    $userId = $request->query('id');

    return MockResponse::json([
        'id' => $userId,
        'name' => "User {$userId}",
    ]);
});

$response = $connector->send(new GetUserRequest(42));
expect($response->json('id'))->toBe(42);
```

## MockResponse Factory

### JSON Responses

```php
// Simple JSON response (200 OK)
MockResponse::json(['key' => 'value']);

// JSON with custom status
MockResponse::json(['error' => 'Not found'], 404);

// JSON with custom headers
MockResponse::json(['data' => []], 200, [
    'X-Request-Id' => 'abc123',
]);
```

### Common HTTP Responses

```php
// 204 No Content
MockResponse::empty();

// 404 Not Found
MockResponse::notFound();
MockResponse::notFound(['error' => 'User not found']);

// 401 Unauthorized
MockResponse::unauthorized();

// 403 Forbidden
MockResponse::forbidden();

// 422 Validation Error
MockResponse::validationError([
    'email' => ['The email field is required'],
    'name' => ['The name must be at least 2 characters'],
]);

// 429 Rate Limited
MockResponse::rateLimited(60); // Retry-After: 60 seconds

// 500 Internal Server Error
MockResponse::serverError();

// 503 Service Unavailable
MockResponse::serviceUnavailable();
```

### Paginated Responses

```php
MockResponse::paginated(
    items: [
        ['id' => 1, 'name' => 'Item 1'],
        ['id' => 2, 'name' => 'Item 2'],
    ],
    page: 1,
    perPage: 15,
    total: 100,
);

// Returns:
// {
//     "data": [{"id": 1, "name": "Item 1"}, ...],
//     "meta": {
//         "current_page": 1,
//         "per_page": 15,
//         "total": 100,
//         "last_page": 7
//     }
// }
```

### File Downloads

```php
MockResponse::file(
    content: 'PDF content here',
    filename: 'report.pdf',
    mimeType: 'application/pdf',
);
```

### Caching Responses

```php
// Response with ETag
MockResponse::cached(
    etag: '"abc123"',
    lastModified: 'Wed, 21 Oct 2023 07:28:00 GMT',
);

// 304 Not Modified
MockResponse::notModified();
```

### Text Responses

```php
MockResponse::text('Plain text content');
MockResponse::text('<html>...</html>', 200, ['Content-Type' => 'text/html']);
```

### Custom Headers

```php
MockResponse::withHeaders([
    'X-Custom-Header' => 'value',
    'X-Rate-Limit-Remaining' => '99',
]);
```

## Request Assertions

### Asserting Requests Were Sent

```php
$connector = new MockConnector();
$connector->alwaysReturn(MockResponse::json(['ok' => true]));

$connector->send(new CreateUserRequest('john@example.com'));

// Assert specific endpoint was called
$connector->assertSent('/users');

// Assert endpoint with method
$connector->assertSent('/users', 'POST');
```

### Asserting Requests Were NOT Sent

```php
$connector->assertNotSent('/admin');
$connector->assertNotSent('/users', 'DELETE');
```

### Counting Requests

```php
// Assert exact number of requests
$connector->assertSentCount(3);

// Assert no requests were sent
$connector->assertNothingSent();
```

### Inspecting Sent Requests

```php
// Get all sent requests
$requests = $connector->sentRequests();

// Get the last request
$lastRequest = $connector->lastRequest();
expect($lastRequest->endpoint())->toBe('/users');
expect($lastRequest->method())->toBe('POST');

// Check request body
expect($lastRequest->body())->toBe([
    'email' => 'john@example.com',
]);
```

### Request History

```php
$history = $connector->history();

foreach ($history as $entry) {
    $request = $entry['request'];
    $response = $entry['response'];

    echo "Sent {$request->method()} to {$request->endpoint()}\n";
    echo "Received {$response->status()}\n";
}
```

### Resetting MockConnector

```php
$connector->reset();

// Clears all:
// - Configured responses
// - Sent requests
// - Request history
```

## RequestRecorder

For more advanced request tracking:

```php
use Cline\Relay\Testing\RequestRecorder;

$recorder = new RequestRecorder();

// Record requests manually
$recorder->record($request, $response);

// Get all records
$records = $recorder->all();

// Get just requests
$requests = $recorder->requests();

// Get just responses
$responses = $recorder->responses();

// Get last request/response
$lastRequest = $recorder->lastRequest();
$lastResponse = $recorder->lastResponse();

// Count records
$count = $recorder->count();
```

### Finding Requests

```php
// Find by endpoint
$requests = $recorder->findByEndpoint('/users');

// Find by method
$postRequests = $recorder->findByMethod('POST');

// Find with custom filter
$requests = $recorder->findRequests(function (Request $request) {
    return str_starts_with($request->endpoint(), '/api/');
});
```

### Recorder Assertions

```php
// Assert any request matches filter
$recorder->assertRecorded(function (Request $request) {
    return $request->method() === 'POST'
        && $request->endpoint() === '/users';
});

// Assert endpoint was called
$recorder->assertRecordedEndpoint('/users');
```

## Testing Patterns

### Testing Service Classes

```php
use App\Services\UserService;
use Cline\Relay\Testing\MockConnector;
use Cline\Relay\Testing\MockResponse;

it('creates a user', function () {
    $connector = new MockConnector();

    $connector->addResponse(MockResponse::json([
        'id' => 1,
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ], 201));

    $service = new UserService($connector);

    $user = $service->createUser([
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    expect($user->id)->toBe(1);
    expect($user->name)->toBe('John Doe');

    $connector->assertSent('/users', 'POST');
});
```

### Testing Error Handling

```php
it('handles 404 errors gracefully', function () {
    $connector = new MockConnector();
    $connector->addResponse(MockResponse::notFound([
        'error' => 'User not found',
    ]));

    $service = new UserService($connector);

    expect(fn () => $service->getUser(999))
        ->toThrow(UserNotFoundException::class);
});

it('handles rate limiting', function () {
    $connector = new MockConnector();
    $connector->addResponse(MockResponse::rateLimited(60));

    $service = new UserService($connector);

    expect(fn () => $service->getUser(1))
        ->toThrow(RateLimitException::class);
});
```

### Testing Pagination

```php
it('fetches all pages', function () {
    $connector = new MockConnector();

    // Page 1
    $connector->addResponse(MockResponse::json([
        'data' => [['id' => 1], ['id' => 2]],
        'meta' => ['next_cursor' => 'abc123'],
    ]));

    // Page 2
    $connector->addResponse(MockResponse::json([
        'data' => [['id' => 3], ['id' => 4]],
        'meta' => ['next_cursor' => null],
    ]));

    $items = $connector->paginate(new GetItemsRequest())
        ->collect();

    expect($items)->toHaveCount(4);
    $connector->assertSentCount(2);
});
```

### Testing Authentication

```php
it('includes auth headers', function () {
    $connector = new MockConnector();
    $connector->alwaysReturn(MockResponse::json(['ok' => true]));

    // Authenticate before sending
    $connector->send(new GetProtectedResourceRequest());

    $request = $connector->lastRequest();

    expect($request->allHeaders())
        ->toHaveKey('Authorization');
});
```

### Testing Request Bodies

```php
it('sends correct payload', function () {
    $connector = new MockConnector();
    $connector->addResponse(MockResponse::json(['id' => 1], 201));

    $connector->send(new CreateOrderRequest([
        'product_id' => 123,
        'quantity' => 2,
    ]));

    $request = $connector->lastRequest();

    expect($request->body())->toBe([
        'product_id' => 123,
        'quantity' => 2,
    ]);
});
```

### Testing Query Parameters

```php
it('sends correct query parameters', function () {
    $connector = new MockConnector();
    $connector->addResponse(MockResponse::json(['results' => []]));

    $connector->send(new SearchRequest('test', page: 2, limit: 20));

    $request = $connector->lastRequest();

    expect($request->allQuery())->toBe([
        'q' => 'test',
        'page' => 2,
        'limit' => 20,
    ]);
});
```

## Laravel Testing Integration

### Using in Feature Tests

```php
use App\Http\Connectors\ApiConnector;
use Cline\Relay\Testing\MockConnector;
use Cline\Relay\Testing\MockResponse;

it('displays user profile', function () {
    $mockConnector = new MockConnector();
    $mockConnector->addResponse(MockResponse::json([
        'id' => 1,
        'name' => 'John Doe',
    ]));

    // Bind mock to container
    $this->app->bind(ApiConnector::class, fn () => $mockConnector);

    $response = $this->get('/users/1');

    $response->assertOk();
    $response->assertSee('John Doe');
});
```

### Creating Test Helpers

```php
// tests/TestCase.php
abstract class TestCase extends BaseTestCase
{
    protected function mockApiConnector(): MockConnector
    {
        $connector = new MockConnector();
        $this->app->bind(ApiConnector::class, fn () => $connector);

        return $connector;
    }
}

// In your test
it('works with helper', function () {
    $connector = $this->mockApiConnector();
    $connector->addResponse(MockResponse::json(['ok' => true]));

    // Test your code...
});
```

## Best Practices

### 1. Use Specific Mock Responses

```php
// Good: Specific, realistic response
MockResponse::json([
    'id' => 1,
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'created_at' => '2024-01-15T10:30:00Z',
]);

// Bad: Generic response that doesn't match real API
MockResponse::json(['data' => 'something']);
```

### 2. Test Error Cases

```php
it('handles various errors', function ($response, $expectedException) {
    $connector = new MockConnector();
    $connector->addResponse($response);

    expect(fn () => $service->doSomething())
        ->toThrow($expectedException);
})->with([
    [MockResponse::notFound(), NotFoundException::class],
    [MockResponse::unauthorized(), AuthenticationException::class],
    [MockResponse::rateLimited(), RateLimitException::class],
    [MockResponse::serverError(), ServerException::class],
]);
```

### 3. Verify Request Contents

```php
it('sends correct request', function () {
    $connector = new MockConnector();
    $connector->addResponse(MockResponse::json(['ok' => true]));

    $service->createUser('john@example.com', 'John');

    $request = $connector->lastRequest();

    // Verify everything
    expect($request->method())->toBe('POST');
    expect($request->endpoint())->toBe('/users');
    expect($request->body())->toMatchArray([
        'email' => 'john@example.com',
        'name' => 'John',
    ]);
});
```

### 4. Reset Between Tests

```php
beforeEach(function () {
    $this->connector = new MockConnector();
});

afterEach(function () {
    $this->connector->reset();
});
```

### 5. Use Dynamic Responses for Complex Logic

```php
$connector->addResponse(function (Request $request) {
    if ($request->query('id') === 999) {
        return MockResponse::notFound();
    }

    return MockResponse::json([
        'id' => $request->query('id'),
        'name' => 'Test User',
    ]);
});
```

## Full Example

Complete test suite:

```php
<?php

use App\Services\UserService;
use Cline\Relay\Testing\MockConnector;
use Cline\Relay\Testing\MockResponse;

describe('UserService', function () {
    beforeEach(function () {
        $this->connector = new MockConnector();
        $this->service = new UserService($this->connector);
    });

    describe('getUser', function () {
        it('returns user data', function () {
            $this->connector->addResponse(MockResponse::json([
                'id' => 1,
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ]));

            $user = $this->service->getUser(1);

            expect($user->name)->toBe('John Doe');
            $this->connector->assertSent('/users/1', 'GET');
        });

        it('throws when user not found', function () {
            $this->connector->addResponse(MockResponse::notFound());

            expect(fn () => $this->service->getUser(999))
                ->toThrow(UserNotFoundException::class);
        });
    });

    describe('createUser', function () {
        it('creates and returns user', function () {
            $this->connector->addResponse(MockResponse::json([
                'id' => 1,
                'name' => 'New User',
            ], 201));

            $user = $this->service->createUser([
                'name' => 'New User',
                'email' => 'new@example.com',
            ]);

            expect($user->id)->toBe(1);

            $request = $this->connector->lastRequest();
            expect($request->method())->toBe('POST');
            expect($request->body()['name'])->toBe('New User');
        });

        it('handles validation errors', function () {
            $this->connector->addResponse(MockResponse::validationError([
                'email' => ['The email is invalid'],
            ]));

            expect(fn () => $this->service->createUser([
                'name' => 'Test',
                'email' => 'invalid',
            ]))->toThrow(ValidationException::class);
        });
    });

    describe('listUsers', function () {
        it('paginates through all users', function () {
            $this->connector->addResponses([
                MockResponse::paginated(
                    items: [['id' => 1], ['id' => 2]],
                    page: 1,
                    total: 4,
                ),
                MockResponse::paginated(
                    items: [['id' => 3], ['id' => 4]],
                    page: 2,
                    total: 4,
                ),
            ]);

            $users = $this->service->getAllUsers();

            expect($users)->toHaveCount(4);
            $this->connector->assertSentCount(2);
        });
    });
});
```

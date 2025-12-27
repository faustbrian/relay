---
title: Testing
description: Mock connectors, response factories, and testing utilities for Relay
---

Relay provides comprehensive testing utilities for writing reliable tests.

## MockClient

### Global Mocking

```php
use Cline\Relay\Testing\MockClient;
use Cline\Relay\Testing\MockResponse;

beforeEach(function () {
    MockClient::destroyGlobal();
});

it('mocks API calls globally', function () {
    MockClient::global([
        MockResponse::json(['id' => 1]),
        MockResponse::json(['id' => 2]),
    ]);

    $result = MyService::fetchData();

    expect($result->id)->toBe(1);
    MockClient::getGlobal()->assertSent('/users');
});
```

### URL Pattern Matching

```php
$mockClient = new MockClient([
    'https://api.example.com/users' => MockResponse::json(['users' => []]),
    'https://api.example.com/users/*/orders' => MockResponse::json(['orders' => []]),
    '*/api/v1/*' => MockResponse::json(['version' => 'v1']),
]);
```

### Request Class Mapping

```php
$mockClient = new MockClient([
    GetUserRequest::class => MockResponse::json(['id' => 1, 'name' => 'John']),
    CreateOrderRequest::class => MockResponse::json(['order_id' => 123], 201),
]);
```

### Dynamic Responses

```php
$mockClient = new MockClient([
    GetUserRequest::class => function (Request $request): Response {
        return MockResponse::json([
            'user_id' => $request->query('id'),
        ]);
    },
]);
```

### Assertions

```php
$mockClient->assertSent(GetUserRequest::class);
$mockClient->assertSent('/users');
$mockClient->assertNotSent('/admin');
$mockClient->assertSentCount(3);
$mockClient->assertNothingSent();
```

## MockConnector

### Basic Usage

```php
use Cline\Relay\Testing\MockConnector;

it('fetches user data', function () {
    $connector = new MockConnector();

    $connector->addResponse(MockResponse::json([
        'id' => 1,
        'name' => 'John Doe',
    ]));

    $response = $connector->send(new GetUserRequest(1));

    expect($response->json('name'))->toBe('John Doe');
});
```

### Sequential Responses

```php
$connector = new MockConnector();

$connector->addResponses([
    MockResponse::json(['id' => 1]),
    MockResponse::json(['id' => 2]),
    MockResponse::json(['id' => 3]),
]);
```

### Fixed Response

```php
$connector->alwaysReturn(MockResponse::json(['status' => 'ok']));
```

### Dynamic Responses

```php
$connector->addResponse(function (Request $request) {
    return MockResponse::json([
        'id' => $request->query('id'),
    ]);
});
```

## MockResponse Factory

### JSON Responses

```php
MockResponse::json(['key' => 'value']);
MockResponse::json(['error' => 'Not found'], 404);
MockResponse::json(['data' => []], 200, ['X-Request-Id' => 'abc123']);
```

### Common HTTP Responses

```php
MockResponse::empty();                    // 204
MockResponse::notFound();                 // 404
MockResponse::unauthorized();             // 401
MockResponse::forbidden();                // 403
MockResponse::validationError(['email' => ['Required']]); // 422
MockResponse::rateLimited(60);            // 429
MockResponse::serverError();              // 500
MockResponse::serviceUnavailable();       // 503
```

### Paginated Responses

```php
MockResponse::paginated(
    items: [['id' => 1], ['id' => 2]],
    page: 1,
    perPage: 15,
    total: 100,
);
```

## Fixtures

Record and replay real API responses. Fixtures store API responses as JSON files that can be replayed in tests.

### Basic Usage

```php
use Cline\Relay\Testing\Fixture;
use Cline\Relay\Testing\MockClient;

$mockClient = new MockClient([
    GetUserRequest::class => Fixture::make('users/get-user-1'),
]);
```

### Fixture Recording

Fixtures support automatic recording - on the first test run, a real API request is made and the response is saved. Subsequent runs replay from the saved file.

```php
use Cline\Relay\Testing\Fixture;
use Cline\Relay\Testing\MockConfig;

// Disable throw on missing fixtures to enable recording
MockConfig::throwOnMissingFixtures(false);

// When using a connector with MockClient, recording is automatic
$connector = new ApiConnector();
$connector->withMockClient(new MockClient([
    GetUserRequest::class => Fixture::make('users/get-user-1'),
]));

// First run: makes real API call, stores response in tests/Fixtures/Saloon/users/get-user-1.json
// Subsequent runs: replays from the stored file
$response = $connector->send(new GetUserRequest(1));
```

### Custom Fixture Path

```php
use Cline\Relay\Testing\Fixture;

// Set custom path for all fixtures
Fixture::setFixturePath('tests/Fixtures/Api');

// Fixture will be stored at: tests/Fixtures/Api/users/list.json
$fixture = Fixture::make('users/list');
```

### Redacting Sensitive Data

Protect sensitive information when recording fixtures:

```php
$fixture = Fixture::make('users/auth')
    ->withSensitiveHeaders([
        'Authorization' => '[REDACTED]',
        'X-Api-Key' => '[API_KEY]',
    ])
    ->withSensitiveJsonParameters([
        'password' => '[HIDDEN]',
        'token' => fn () => '[DYNAMIC_TOKEN]',
    ])
    ->withSensitiveRegexPatterns([
        '/sk-[a-zA-Z0-9]+/' => '[API_KEY]',
        '/\d{16}/' => '[CARD_NUMBER]',
    ]);
```

### Fixture File Format

Fixtures are stored as JSON with this structure:

```json
{
    "status": 200,
    "headers": {
        "Content-Type": "application/json"
    },
    "body": {
        "id": 1,
        "name": "John Doe"
    }
}
```

### Configuration Options

```php
use Cline\Relay\Testing\MockConfig;

// Throw exception when fixture file is missing (default: false)
MockConfig::throwOnMissingFixtures(true);

// Set fixture storage path
MockConfig::setFixturePath('tests/Fixtures/Custom');

// Reset all mock configuration
MockConfig::reset();
```

## Request Assertions

```php
$connector->assertSent('/users');
$connector->assertSent('/users', 'POST');
$connector->assertNotSent('/admin');
$connector->assertSentCount(3);

$lastRequest = $connector->lastRequest();
expect($lastRequest->endpoint())->toBe('/users');
expect($lastRequest->method())->toBe('POST');
expect($lastRequest->body())->toBe(['email' => 'john@example.com']);
```

## Testing Patterns

### Testing Service Classes

```php
it('creates a user', function () {
    $connector = new MockConnector();
    $connector->addResponse(MockResponse::json(['id' => 1], 201));

    $service = new UserService($connector);
    $user = $service->createUser(['name' => 'John']);

    expect($user->id)->toBe(1);
    $connector->assertSent('/users', 'POST');
});
```

### Testing Error Handling

```php
it('handles 404 errors', function () {
    $connector = new MockConnector();
    $connector->addResponse(MockResponse::notFound());

    expect(fn () => $service->getUser(999))
        ->toThrow(UserNotFoundException::class);
});
```

### Testing Pagination

```php
it('fetches all pages', function () {
    $connector = new MockConnector();

    $connector->addResponses([
        MockResponse::json(['data' => [['id' => 1]], 'meta' => ['next_cursor' => 'abc']]),
        MockResponse::json(['data' => [['id' => 2]], 'meta' => ['next_cursor' => null]]),
    ]);

    $items = $connector->paginate(new GetItemsRequest())->collect();

    expect($items)->toHaveCount(2);
    $connector->assertSentCount(2);
});
```

### Laravel Feature Tests

```php
it('displays user profile', function () {
    $mockConnector = new MockConnector();
    $mockConnector->addResponse(MockResponse::json(['id' => 1, 'name' => 'John']));

    $this->app->bind(ApiConnector::class, fn () => $mockConnector);

    $response = $this->get('/users/1');

    $response->assertOk();
    $response->assertSee('John');
});
```

## Best Practices

1. **Use specific mock responses** - Match real API structure
2. **Test error cases** - 404, 401, 429, 500 scenarios
3. **Verify request contents** - Check body, headers, query params
4. **Reset between tests** - Use `beforeEach`/`afterEach`

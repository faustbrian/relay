# Serialization

Serialize requests and responses for queue jobs, failed request storage, and replay.

## Request Serialization

```php
$request = new CreateUser(name: 'John', email: 'john@example.com');

// Serialize for storage
$serialized = $request->serialize();
// Returns: ['class' => CreateUser::class, 'data' => [...]]

// Store in database, cache, or queue
Cache::put('pending_request', $serialized, 3600);

// Later: restore and send
$restored = Request::unserialize($serialized);
$connector->send($restored);
```

## Response Serialization

```php
$response = $connector->send($request);

// Serialize response
$serialized = $response->serialize();
// Returns: ['status' => 200, 'headers' => [...], 'body' => '...', 'request' => [...]]

// Restore
$restored = Response::unserialize($serialized);
$restored->json();     // Works as expected
$restored->status();   // 200
```

## Queue Job Integration

```php
class ProcessApiRequest implements ShouldQueue
{
    public function __construct(
        public array $serializedRequest,
        public string $connectorClass,
    ) {}

    public function handle(): void
    {
        $connector = app($this->connectorClass);
        $request = Request::unserialize($this->serializedRequest);

        $response = $connector->send($request);

        // Process response...
    }
}

// Dispatch
$request = new CreatePayment(amount: 1000);
ProcessApiRequest::dispatch(
    $request->serialize(),
    PaymentConnector::class,
);
```

## Failed Request Storage

Store failed requests for manual review or retry:

```php
class ApiConnector extends Connector
{
    public function onRequestFailed(Request $request, Response $response): void
    {
        FailedApiRequest::create([
            'request' => $request->serialize(),
            'response' => $response->serialize(),
            'connector' => static::class,
            'failed_at' => now(),
        ]);
    }
}

// Later: retry failed requests
$failed = FailedApiRequest::find(1);
$connector = app($failed->connector);
$request = Request::unserialize($failed->request);

$response = $connector->send($request);
```

## Bulk Retry

```php
// Retry all failed requests from last 24 hours
FailedApiRequest::where('failed_at', '>', now()->subDay())
    ->each(function (FailedApiRequest $failed) {
        $connector = app($failed->connector);
        $request = Request::unserialize($failed->request);

        try {
            $connector->send($request);
            $failed->delete();
        } catch (RequestException $e) {
            $failed->update(['retry_count' => $failed->retry_count + 1]);
        }
    });
```

## Connector Serialization

For queued jobs that need the full connector state:

```php
class ApiConnector extends Connector implements Serializable
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $environment,
    ) {}

    public function serialize(): array
    {
        return [
            'class' => static::class,
            'apiKey' => $this->apiKey,
            'environment' => $this->environment,
        ];
    }

    public static function unserialize(array $data): static
    {
        return new static(
            apiKey: $data['apiKey'],
            environment: $data['environment'],
        );
    }
}
```

## Sensitive Data Handling

Exclude sensitive data from serialization:

```php
#[Post, Json]
class CreatePayment extends Request
{
    public function __construct(
        private readonly int $amount,
        private readonly string $cardNumber,
        private readonly string $cvv,
    ) {}

    // Fields to exclude from serialization
    protected array $sensitive = ['cardNumber', 'cvv'];

    public function serialize(): array
    {
        $data = parent::serialize();

        // Redact sensitive fields
        foreach ($this->sensitive as $field) {
            $data['data'][$field] = '[REDACTED]';
        }

        return $data;
    }
}
```

## Request Replay from Logs

```php
// Store full request/response for debugging
class ApiConnector extends Connector
{
    public function events(): ?EventConfig
    {
        return new EventConfig(
            onResponse: function (Response $response, Request $request) {
                ApiRequestLog::create([
                    'request' => $request->serialize(),
                    'response' => $response->serialize(),
                    'duration' => $response->duration(),
                ]);
            },
        );
    }
}

// Replay from log
$log = ApiRequestLog::find(1);
$request = Request::unserialize($log->request);
$connector->send($request);
```

## JSON Serialization

For API storage or cross-service communication:

```php
$request = new GetUser(1);

// To JSON
$json = $request->toJson();

// From JSON
$restored = Request::fromJson($json);
```

## Serialization Format

```php
// Request serialization format
[
    'class' => 'App\Requests\CreateUser',
    'data' => [
        'name' => 'John',
        'email' => 'john@example.com',
    ],
    'headers' => ['X-Custom' => 'value'],
    'query' => null,
    'body' => ['name' => 'John', 'email' => 'john@example.com'],
]

// Response serialization format
[
    'status' => 200,
    'headers' => [
        'Content-Type' => 'application/json',
        'X-Request-Id' => 'abc123',
    ],
    'body' => '{"id":1,"name":"John"}',
    'request' => [...], // Serialized request
    'duration' => 150.5, // ms
]
```

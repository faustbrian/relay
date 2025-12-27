---
title: JSON-RPC Microservices
description: Base classes for building internal microservice integrations with automatic configuration and authentication
---

Relay provides specialized base classes for internal microservice communication using JSON-RPC. These classes handle common patterns like configuration lookup, bearer token authentication, and Spatie Data integration.

## Overview

The JSON-RPC microservice classes provide:
- Automatic configuration lookup via `services.microservices.{name}.{key}`
- Bearer token authentication from config
- Built-in retry logic (3 attempts, 500ms interval)
- JSON-RPC error detection
- Automatic service name derivation
- Spatie Laravel Data integration with null filtering

## JsonRpcMicroserviceConnector

Base connector for internal microservices with automatic configuration.

```php
use Cline\Relay\Core\JsonRpc\JsonRpcMicroserviceConnector;

class PostalConnector extends JsonRpcMicroserviceConnector
{
    // Service name derived automatically: "postal"
    // Base URL from: config('services.microservices.postal.base_url')
    // Token from: config('services.microservices.postal.token')
}
```

### Configuration

Add your microservice configuration to `config/services.php`:

```php
return [
    'microservices' => [
        'postal' => [
            'base_url' => env('POSTAL_SERVICE_URL', 'https://postal.internal'),
            'token' => env('POSTAL_SERVICE_TOKEN'),
        ],
    ],
];
```

### Service Name Derivation

The service name is automatically derived from the connector's namespace:

| Connector Class | Derived Service Name |
|----------------|---------------------|
| `App\Postal\Connectors\ServiceConnector` | `postal` |
| `Clients\PaymentGateway\Connectors\ApiConnector` | `payment_gateway` |
| `Integrations\UserAuth\Connectors\AuthConnector` | `user_auth` |

### Customizing Behavior

```php
class RobustConnector extends JsonRpcMicroserviceConnector
{
    // Customize retry behavior
    protected int $tries = 5;
    protected int $retryInterval = 1000;

    // Override default config
    public function defaultConfig(): array
    {
        return [
            'timeout' => 60,
            'connect_timeout' => 10,
        ];
    }

    // Custom authentication
    public function authenticate(Request $request): void
    {
        $apiKey = $this->configByKey('api_key');
        $request->withHeader('X-API-Key', $apiKey);
    }
}
```

### Error Detection

The connector automatically detects JSON-RPC errors:

```php
public function hasRequestFailed(Response $response): bool
{
    // HTTP errors (4xx, 5xx)
    if ($response->clientError() || $response->serverError()) {
        return true;
    }

    // JSON-RPC error field present
    return $response->json('error') !== null;
}
```

## JsonRpcMicroserviceRequest

Base request class with automatic data serialization for Spatie Laravel Data objects.

```php
use Cline\Relay\Core\JsonRpc\JsonRpcMicroserviceRequest;
use Spatie\LaravelData\Data;

class QueryCoordinatesRequest extends JsonRpcMicroserviceRequest
{
    public function __construct(
        public readonly QueryCoordinatesData $data,
    ) {}
}
```

### Automatic Data Serialization

The request automatically handles the `$data` property:

```php
// With Spatie Data object
$request = new QueryCoordinatesRequest(
    new QueryCoordinatesData(
        countryCode: 'FI',
        postalCode: '00100',
        locale: 'fi',
        locality: null, // Filtered out
    ),
);

// Produces JSON-RPC body:
{
    "jsonrpc": "2.0",
    "method": "app.queryCoordinates",
    "params": {
        "data": {
            "countryCode": "FI",
            "postalCode": "00100",
            "locale": "fi"
        }
    },
    "id": "uuid-here"
}
```

### Null Filtering

Null values are automatically filtered from nested arrays:

```php
class AddressData extends Data
{
    public function __construct(
        public readonly string $street,
        public readonly ?string $apartment, // null - will be filtered
        public readonly string $city,
        public readonly ?string $state, // null - will be filtered
    ) {}
}
```

### Method Prefix

Customize the JSON-RPC method prefix:

```php
class InternalRequest extends JsonRpcMicroserviceRequest
{
    protected ?string $methodPrefix = 'internal'; // "internal.methodName"
}

class ExternalRequest extends JsonRpcMicroserviceRequest
{
    protected ?string $methodPrefix = null; // Just "methodName"
}
```

## Full Example

### Data Transfer Objects

```php
use Spatie\LaravelData\Data;

class QueryCoordinatesData extends Data
{
    public function __construct(
        public readonly string $countryCode,
        public readonly string $postalCode,
        public readonly string $locale,
        public readonly ?string $locality = null,
    ) {}
}
```

### Request Classes

```php
use Cline\Relay\Core\JsonRpc\JsonRpcMicroserviceRequest;

class QueryCoordinatesRequest extends JsonRpcMicroserviceRequest
{
    public function __construct(
        public readonly QueryCoordinatesData $data,
    ) {}
}

class RetrieveCountryInfoRequest extends JsonRpcMicroserviceRequest
{
    public function __construct(
        public readonly RetrieveCountryInfoData $data,
    ) {}
}
```

### Connector with Convenience Methods

```php
use Cline\Relay\Core\JsonRpc\JsonRpcMicroserviceConnector;
use Cline\Relay\Core\Response;

class ServiceConnector extends JsonRpcMicroserviceConnector
{
    public function queryCoordinates(QueryCoordinatesData $data): Response
    {
        return $this->send(new QueryCoordinatesRequest($data));
    }

    public function retrieveCountryInfo(RetrieveCountryInfoData $data): Response
    {
        return $this->send(new RetrieveCountryInfoRequest($data));
    }
}
```

### Usage

```php
$connector = new ServiceConnector();

$response = $connector->queryCoordinates(
    new QueryCoordinatesData(
        countryCode: 'FI',
        postalCode: '00100',
        locale: 'fi',
        locality: 'Helsinki',
    ),
);

if ($response->successful()) {
    $lat = $response->json('result.data.attributes.latitude');
    $lng = $response->json('result.data.attributes.longitude');
}
```

## Testing

Use the standard Relay testing utilities with microservice connectors:

```php
use Cline\Relay\Testing\MockClient;

test('queries coordinates', function () {
    $connector = (new ServiceConnector())
        ->withMockClient(new MockClient([
            new MockResponse(['result' => ['data' => ['attributes' => [
                'latitude' => 60.1699,
                'longitude' => 24.9384,
            ]]]]),
        ]));

    $response = $connector->queryCoordinates(
        new QueryCoordinatesData(
            countryCode: 'FI',
            postalCode: '00100',
            locale: 'fi',
        ),
    );

    expect($response->json('result.data.attributes.latitude'))->toBe(60.1699);
});
```

For debugging during development:

```php
$connector = (new ServiceConnector())->debug();
$response = $connector->queryCoordinates($data);
// Prints formatted request and response
```

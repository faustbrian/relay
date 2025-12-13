# Telemetry & Metrics

Emit events for request_start, request_end, error — integrate with monitoring.

## Event Hooks

```php
class ApiConnector extends Connector
{
    public function events(): ?EventConfig
    {
        return new EventConfig(
            onRequest: function (Request $request) {
                // Before request is sent
                Log::debug('Sending request', [
                    'endpoint' => $request->endpoint(),
                    'method' => $request->method(),
                ]);
            },
            onResponse: function (Response $response, Request $request, float $duration) {
                // After response received
                Metrics::timing('api.request', $duration, [
                    'endpoint' => $request->endpoint(),
                    'status' => $response->status(),
                ]);
            },
            onError: function (RequestException $e, Request $request) {
                // On error
                Log::error('Request failed', [
                    'endpoint' => $request->endpoint(),
                    'error' => $e->getMessage(),
                ]);
            },
        );
    }
}
```

## Metrics Collection

```php
class ApiConnector extends Connector
{
    public function metrics(): ?MetricsConfig
    {
        return new MetricsConfig(
            // Driver: statsd, prometheus, cloudwatch, datadog
            driver: 'prometheus',

            // Metric prefix
            prefix: 'api',

            // What to collect
            collect: [
                'request_count',      // Counter
                'request_duration',   // Histogram
                'error_count',        // Counter
                'response_size',      // Histogram
            ],

            // Labels/tags to include
            labels: ['endpoint', 'method', 'status'],
        );
    }
}

// Access metrics
$connector->metrics()->requestCount();
$connector->metrics()->averageDuration();
$connector->metrics()->errorRate();
```

## Laravel Integration

```php
class ApiConnector extends Connector
{
    public function events(): ?EventConfig
    {
        return new EventConfig(
            // Dispatch Laravel events
            dispatchEvents: true,
        );
    }
}

// Listen in EventServiceProvider
protected $listen = [
    RequestSent::class => [LogApiRequest::class],
    ResponseReceived::class => [RecordApiMetrics::class],
    RequestFailed::class => [AlertOnApiFailure::class],
];
```

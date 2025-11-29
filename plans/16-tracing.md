# Request Tracing

Auto-generate and propagate trace IDs across requests (OpenTelemetry-style).

## Connector-Level

```php
class ApiConnector extends Connector
{
    public function tracing(): ?TracingConfig
    {
        return new TracingConfig(
            // Header to use for trace ID
            traceHeader: 'X-Request-Id',

            // Generate trace ID if not present
            generateTraceId: fn() => Str::uuid()->toString(),

            // Propagate parent trace ID to child requests
            propagate: true,

            // Also set span ID for distributed tracing
            spanHeader: 'X-Span-Id',
        );
    }
}
```

## Reading Trace Info

```php
$response = $connector->send($request);

$response->traceId();    // The trace ID used
$response->spanId();     // The span ID if set
$response->duration();   // Request duration in ms
```

## OpenTelemetry Integration

```php
class ApiConnector extends Connector
{
    public function tracing(): ?TracingConfig
    {
        return new TracingConfig(
            // Full OpenTelemetry support
            openTelemetry: true,

            // Service name for traces
            serviceName: 'my-app',

            // Span attributes to include
            attributes: [
                'http.method',
                'http.url',
                'http.status_code',
            ],
        );
    }
}
```

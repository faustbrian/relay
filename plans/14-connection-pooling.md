# Connection Pooling

Explicit control over keep-alive, idle timeout, pool size per host.

## Connector-Level

```php
class ApiConnector extends Connector
{
    public function connectionPool(): ?ConnectionPoolConfig
    {
        return new ConnectionPoolConfig(
            maxConnections: 100,           // Total max connections
            maxConnectionsPerHost: 10,     // Max per host
            idleTimeout: 60,               // Seconds before closing idle connection
            keepAlive: true,               // Enable HTTP keep-alive
        );
    }
}
```

## Per-Request Override

```php
#[Get, Json, MaxConnections(5)]
class LimitedRequest extends Request { ... }
```

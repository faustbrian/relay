# Proxy

## Via Attribute

```php
// Simple proxy
#[Get, Json, Proxy('http://proxy.example.com:8080')]
class ProxiedRequest extends Request { ... }

// SOCKS proxy (e.g., Tor)
#[Get, Json, Proxy('socks5://127.0.0.1:9050')]
class AnonymousRequest extends Request { ... }

// With authentication
#[Get, Json, Proxy('http://user:pass@proxy.example.com:8080')]
class AuthProxiedRequest extends Request { ... }
```

## Connector-Level

```php
class ApiConnector extends Connector
{
    // Simple string
    public function proxy(): ?string
    {
        return 'http://proxy.example.com:8080';
    }
}

// With full config
class ApiConnector extends Connector
{
    public function proxy(): ?ProxyConfig
    {
        return new ProxyConfig(
            url: 'http://proxy.example.com:8080',
            username: 'user',
            password: 'pass',
            noProxy: ['localhost', '127.0.0.1', '*.internal.com'],
        );
    }
}
```

## Runtime/Dynamic Proxy

```php
class ApiConnector extends Connector
{
    public function __construct(
        private readonly ProxyPool $proxyPool,
    ) {}

    public function proxy(): ?string
    {
        // Rotate proxies per request
        return $this->proxyPool->next();
    }
}

// Or based on environment
class ApiConnector extends Connector
{
    public function proxy(): ?string
    {
        return match (app()->environment()) {
            'production' => 'http://prod-proxy:8080',
            'staging' => 'http://staging-proxy:8080',
            default => null, // No proxy in dev
        };
    }
}

// Per-request override via method
class GeoRestrictedRequest extends Request
{
    public function __construct(
        private readonly string $region,
    ) {}

    public function proxy(): ?string
    {
        return match ($this->region) {
            'eu' => 'http://eu-proxy:8080',
            'us' => 'http://us-proxy:8080',
            'asia' => 'http://asia-proxy:8080',
            default => null,
        };
    }
}
```

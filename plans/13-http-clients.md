# HTTP Client Abstraction

Default is Guzzle, but supports swapping to other PSR-18 clients:

```php
// Default: Guzzle
class ApiConnector extends Connector
{
    // Uses Guzzle by default
}

// Symfony HTTP Client
class ApiConnector extends Connector
{
    public function httpClient(): ClientInterface
    {
        return new SymfonyHttpClient();
    }
}

// Laravel HTTP Client
class ApiConnector extends Connector
{
    public function httpClient(): ClientInterface
    {
        return new LaravelHttpClient();
    }
}

// Custom client
class ApiConnector extends Connector
{
    public function httpClient(): ClientInterface
    {
        return new CustomPsr18Client(
            // Your config
        );
    }
}
```

## Built-in Drivers

```php
// Guzzle (default)
new GuzzleDriver(
    timeout: 30,
    verify: true,
    proxy: 'http://proxy.example.com',
);

// Symfony
new SymfonyDriver(
    timeout: 30,
    maxRedirects: 5,
);

// Laravel
new LaravelDriver(); // Uses Http facade
```

# DNS Configuration

Custom resolvers, DNS-over-HTTPS, caching TTL.

```php
class ApiConnector extends Connector
{
    public function dnsConfig(): ?DnsConfig
    {
        return new DnsConfig(
            // Custom DNS servers
            servers: ['8.8.8.8', '8.8.4.4'],

            // DNS-over-HTTPS
            dohUrl: 'https://cloudflare-dns.com/dns-query',

            // Cache TTL (default: respect DNS TTL)
            cacheTtl: 300,

            // Resolve to specific IP (useful for testing)
            resolve: [
                'api.example.com' => '192.168.1.100',
            ],
        );
    }
}
```

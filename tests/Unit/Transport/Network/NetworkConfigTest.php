<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Relay\Support\Attributes\Network\ForceIpResolve;
use Cline\Relay\Support\Attributes\Network\Proxy;
use Cline\Relay\Support\Attributes\Network\Ssl;
use Cline\Relay\Transport\Http\GuzzleDriver;
use Cline\Relay\Transport\Network\ConnectionConfig;
use Cline\Relay\Transport\Network\ProxyConfig;
use Cline\Relay\Transport\Network\SslConfig;

describe('ProxyConfig', function (): void {
    it('creates from single URL', function (): void {
        $proxy = ProxyConfig::url('http://proxy.example.com:8080');

        expect($proxy->http)->toBe('http://proxy.example.com:8080');
        expect($proxy->https)->toBe('http://proxy.example.com:8080');
        expect($proxy->isConfigured())->toBeTrue();
    });

    it('creates with separate proxies', function (): void {
        $proxy = ProxyConfig::separate(
            'http://http-proxy.example.com:8080',
            'http://https-proxy.example.com:8443',
        );

        expect($proxy->http)->toBe('http://http-proxy.example.com:8080');
        expect($proxy->https)->toBe('http://https-proxy.example.com:8443');
    });

    it('adds no-proxy domains', function (): void {
        $proxy = ProxyConfig::url('http://proxy.example.com:8080')
            ->withNoProxy(['localhost', '127.0.0.1', '*.internal.com']);

        expect($proxy->noProxy)->toBe(['localhost', '127.0.0.1', '*.internal.com']);
    });

    it('converts to simple Guzzle config when same proxy', function (): void {
        $proxy = ProxyConfig::url('http://proxy.example.com:8080');

        expect($proxy->toGuzzleConfig())->toBe('http://proxy.example.com:8080');
    });

    it('converts to array Guzzle config when different proxies', function (): void {
        $proxy = ProxyConfig::separate(
            'http://http-proxy.example.com:8080',
            'http://https-proxy.example.com:8443',
        );

        $config = $proxy->toGuzzleConfig();

        expect($config)->toBeArray();
        expect($config['http'])->toBe('http://http-proxy.example.com:8080');
        expect($config['https'])->toBe('http://https-proxy.example.com:8443');
    });

    it('includes no-proxy in array config', function (): void {
        $proxy = ProxyConfig::url('http://proxy.example.com:8080')
            ->withNoProxy(['localhost']);

        $config = $proxy->toGuzzleConfig();

        expect($config)->toBeArray();
        expect($config['no'])->toBe(['localhost']);
    });

    it('reports unconfigured when no proxies', function (): void {
        $proxy = new ProxyConfig();

        expect($proxy->isConfigured())->toBeFalse();
    });
});

describe('SslConfig', function (): void {
    it('creates default with verification', function (): void {
        $ssl = new SslConfig();

        expect($ssl->verify)->toBeTrue();
        expect($ssl->toGuzzleConfig())->toBe([]);
    });

    it('creates insecure config', function (): void {
        $ssl = SslConfig::insecure();

        expect($ssl->verify)->toBeFalse();
        expect($ssl->toGuzzleConfig()['verify'])->toBeFalse();
    });

    it('creates with client certificate', function (): void {
        $ssl = SslConfig::withClientCert('/path/to/cert.pem');

        expect($ssl->certPath)->toBe('/path/to/cert.pem');

        $config = $ssl->toGuzzleConfig();
        expect($config['cert'])->toBe('/path/to/cert.pem');
    });

    it('creates with client certificate and key', function (): void {
        $ssl = SslConfig::withClientCert(
            '/path/to/cert.pem',
            '/path/to/key.pem',
            'password',
        );

        $config = $ssl->toGuzzleConfig();
        expect($config['cert'])->toBe(['/path/to/cert.pem', 'password']);
        expect($config['ssl_key'])->toBe(['/path/to/key.pem', 'password']);
    });

    it('creates with custom CA bundle', function (): void {
        $ssl = SslConfig::withCaBundle('/path/to/ca-bundle.crt');

        $config = $ssl->toGuzzleConfig();
        expect($config['verify'])->toBe('/path/to/ca-bundle.crt');
    });

    it('creates with client certificate and key without password', function (): void {
        $ssl = SslConfig::withClientCert(
            '/path/to/cert.pem',
            '/path/to/key.pem',
        );

        $config = $ssl->toGuzzleConfig();
        expect($config['cert'])->toBe('/path/to/cert.pem');
        expect($config['ssl_key'])->toBe('/path/to/key.pem');
    });

    it('creates with client certificate and password without key', function (): void {
        $ssl = SslConfig::withClientCert(
            '/path/to/cert.pem',
            null,
            'password',
        );

        $config = $ssl->toGuzzleConfig();
        expect($config['cert'])->toBe(['/path/to/cert.pem', 'password']);
        expect($config)->not->toHaveKey('ssl_key');
    });
});

describe('ConnectionConfig', function (): void {
    it('creates with default settings', function (): void {
        $conn = new ConnectionConfig();

        expect($conn->maxConnections)->toBe(10);
        expect($conn->maxConnectionsPerHost)->toBe(5);
        expect($conn->idleTimeout)->toBe(60);
        expect($conn->keepAlive)->toBeTrue();
    });

    it('creates with custom max connections', function (): void {
        $conn = ConnectionConfig::withMaxConnections(20);

        expect($conn->maxConnections)->toBe(20);
    });

    it('creates with keep-alive disabled', function (): void {
        $conn = ConnectionConfig::noKeepAlive();

        expect($conn->keepAlive)->toBeFalse();

        $curlOptions = $conn->toCurlOptions();
        expect($curlOptions[\CURLOPT_FORBID_REUSE])->toBeTrue();
        expect($curlOptions[\CURLOPT_FRESH_CONNECT])->toBeTrue();
    });

    it('creates forcing IPv4', function (): void {
        $conn = ConnectionConfig::forceIPv4();

        expect($conn->forceIpVersion)->toBe('4');
        expect($conn->getCurlIpResolve())->toBe(\CURL_IPRESOLVE_V4);
    });

    it('creates forcing IPv6', function (): void {
        $conn = ConnectionConfig::forceIPv6();

        expect($conn->forceIpVersion)->toBe('6');
        expect($conn->getCurlIpResolve())->toBe(\CURL_IPRESOLVE_V6);
    });

    it('returns empty curl options for default config', function (): void {
        $conn = new ConnectionConfig();

        expect($conn->toCurlOptions())->toBe([]);
    });
});

describe('GuzzleDriver', function (): void {
    it('can be created with default settings', function (): void {
        $driver = GuzzleDriver::create();

        expect($driver)->toBeInstanceOf(GuzzleDriver::class);
    });

    it('can be created with proxy', function (): void {
        $proxy = ProxyConfig::url('http://proxy.example.com:8080');
        $driver = GuzzleDriver::withProxy($proxy);

        expect($driver)->toBeInstanceOf(GuzzleDriver::class);
    });

    it('can be created with SSL config', function (): void {
        $ssl = SslConfig::insecure();
        $driver = GuzzleDriver::withSsl($ssl);

        expect($driver)->toBeInstanceOf(GuzzleDriver::class);
    });

    it('can be created with connection config', function (): void {
        $conn = ConnectionConfig::forceIPv4();
        $driver = GuzzleDriver::withConnection($conn);

        expect($driver)->toBeInstanceOf(GuzzleDriver::class);
    });

    it('can be created with all configurations', function (): void {
        $driver = new GuzzleDriver(
            timeout: 60,
            connectTimeout: 15,
            proxy: ProxyConfig::url('http://proxy.example.com:8080'),
            ssl: SslConfig::insecure(),
            connection: ConnectionConfig::forceIPv4(),
        );

        expect($driver)->toBeInstanceOf(GuzzleDriver::class);
    });
});

describe('Network Attributes', function (): void {
    it('creates Proxy attribute', function (): void {
        $proxy = new Proxy(
            http: 'http://proxy.example.com:8080',
            https: 'http://proxy.example.com:8443',
            noProxy: ['localhost'],
        );

        expect($proxy->http)->toBe('http://proxy.example.com:8080');
        expect($proxy->https)->toBe('http://proxy.example.com:8443');
        expect($proxy->noProxy)->toBe(['localhost']);
    });

    it('creates Ssl attribute', function (): void {
        $ssl = new Ssl(
            verify: false,
            certPath: '/path/to/cert.pem',
        );

        expect($ssl->verify)->toBeFalse();
        expect($ssl->certPath)->toBe('/path/to/cert.pem');
    });

    it('creates ForceIpResolve attribute with v4', function (): void {
        $attr = new ForceIpResolve(ForceIpResolve::V4);

        expect($attr->version)->toBe('v4');
    });

    it('creates ForceIpResolve attribute with v6', function (): void {
        $attr = new ForceIpResolve(ForceIpResolve::V6);

        expect($attr->version)->toBe('v6');
    });

    it('defaults ForceIpResolve to v4', function (): void {
        $attr = new ForceIpResolve();

        expect($attr->version)->toBe(ForceIpResolve::V4);
    });
});

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Transport\Http;

use Cline\Relay\Transport\Network\ConnectionConfig;
use Cline\Relay\Transport\Network\ProxyConfig;
use Cline\Relay\Transport\Network\SslConfig;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Guzzle HTTP client driver.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class GuzzleDriver implements ClientInterface
{
    private Client $client;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        int $timeout = 30,
        int $connectTimeout = 10,
        array $config = [],
        ?HandlerStack $handler = null,
        ?ProxyConfig $proxy = null,
        ?SslConfig $ssl = null,
        ?ConnectionConfig $connection = null,
    ) {
        $clientConfig = [
            'timeout' => $timeout,
            'connect_timeout' => $connectTimeout,
            'http_errors' => false, // We handle errors ourselves
            'handler' => $handler ?? HandlerStack::create(),
            ...$config,
        ];

        // Apply proxy configuration
        if ($proxy instanceof ProxyConfig && $proxy->isConfigured()) {
            $clientConfig['proxy'] = $proxy->toGuzzleConfig();
        }

        // Apply SSL configuration
        if ($ssl instanceof SslConfig) {
            $clientConfig = [...$clientConfig, ...$ssl->toGuzzleConfig()];
        }

        // Apply connection configuration
        if ($connection instanceof ConnectionConfig) {
            $curlOptions = $connection->toCurlOptions();

            if ($curlOptions !== []) {
                $clientConfig['curl'] = $curlOptions;
            }
        }

        $this->client = new Client($clientConfig);
    }

    /**
     * Create a new driver with default settings.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Create a new driver with proxy.
     */
    public static function withProxy(ProxyConfig $proxy): self
    {
        return new self(proxy: $proxy);
    }

    /**
     * Create a new driver with SSL configuration.
     */
    public static function withSsl(SslConfig $ssl): self
    {
        return new self(ssl: $ssl);
    }

    /**
     * Create a new driver with connection configuration.
     */
    public static function withConnection(ConnectionConfig $connection): self
    {
        return new self(connection: $connection);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->client->send($request);
    }
}

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Transport\Network;

/**
 * Configuration for HTTP proxy.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class ProxyConfig
{
    /**
     * @param null|array<string> $noProxy Domains to bypass proxy
     */
    public function __construct(
        public ?string $http = null,
        public ?string $https = null,
        public ?array $noProxy = null,
    ) {}

    /**
     * Create from a single proxy URL for all protocols.
     */
    public static function url(string $url): self
    {
        return new self(http: $url, https: $url);
    }

    /**
     * Create with separate HTTP and HTTPS proxies.
     */
    public static function separate(string $http, string $https): self
    {
        return new self(http: $http, https: $https);
    }

    /**
     * Create with no-proxy domains.
     *
     * @param array<string> $domains
     */
    public function withNoProxy(array $domains): self
    {
        return new self(
            http: $this->http,
            https: $this->https,
            noProxy: $domains,
        );
    }

    /**
     * Convert to Guzzle proxy configuration.
     *
     * @return array<string, mixed>|string
     */
    public function toGuzzleConfig(): array|string
    {
        // If same proxy for both, return simple string
        if ($this->http === $this->https && $this->noProxy === null) {
            return $this->http ?? '';
        }

        $config = [];

        if ($this->http !== null) {
            $config['http'] = $this->http;
        }

        if ($this->https !== null) {
            $config['https'] = $this->https;
        }

        if ($this->noProxy !== null) {
            $config['no'] = $this->noProxy;
        }

        return $config;
    }

    /**
     * Check if proxy is configured.
     */
    public function isConfigured(): bool
    {
        return $this->http !== null || $this->https !== null;
    }
}

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Transport\Network;

use const CURL_IPRESOLVE_V4;
use const CURL_IPRESOLVE_V6;
use const CURLOPT_FORBID_REUSE;
use const CURLOPT_FRESH_CONNECT;
use const CURLOPT_IPRESOLVE;

/**
 * Configuration for connection pooling and keep-alive.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class ConnectionConfig
{
    public function __construct(
        public int $maxConnections = 10,
        public int $maxConnectionsPerHost = 5,
        public int $idleTimeout = 60,
        public bool $keepAlive = true,
        public ?string $forceIpVersion = null, // '4' or '6'
    ) {}

    /**
     * Create with custom max connections.
     */
    public static function withMaxConnections(int $max): self
    {
        return new self(maxConnections: $max);
    }

    /**
     * Create with keep-alive disabled.
     */
    public static function noKeepAlive(): self
    {
        return new self(keepAlive: false);
    }

    /**
     * Create forcing IPv4.
     */
    public static function forceIPv4(): self
    {
        return new self(forceIpVersion: '4');
    }

    /**
     * Create forcing IPv6.
     */
    public static function forceIPv6(): self
    {
        return new self(forceIpVersion: '6');
    }

    /**
     * Get CURLOPT_IPRESOLVE value.
     */
    public function getCurlIpResolve(): ?int
    {
        return match ($this->forceIpVersion) {
            '4' => CURL_IPRESOLVE_V4,
            '6' => CURL_IPRESOLVE_V6,
            default => null,
        };
    }

    /**
     * Convert to Guzzle CURL options.
     *
     * @return array<int, mixed>
     */
    public function toCurlOptions(): array
    {
        $options = [];

        if (!$this->keepAlive) {
            $options[CURLOPT_FORBID_REUSE] = true;
            $options[CURLOPT_FRESH_CONNECT] = true;
        }

        $ipResolve = $this->getCurlIpResolve();

        if ($ipResolve !== null) {
            $options[CURLOPT_IPRESOLVE] = $ipResolve;
        }

        return $options;
    }
}

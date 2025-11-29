<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Transport\Network;

/**
 * Configuration for SSL/TLS settings.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class SslConfig
{
    public function __construct(
        public bool $verify = true,
        public ?string $certPath = null,
        public ?string $keyPath = null,
        public ?string $keyPassword = null,
        public ?string $caBundlePath = null,
    ) {}

    /**
     * Create with verification disabled (insecure, use for dev only).
     */
    public static function insecure(): self
    {
        return new self(verify: false);
    }

    /**
     * Create with client certificate.
     */
    public static function withClientCert(
        string $certPath,
        ?string $keyPath = null,
        ?string $keyPassword = null,
    ): self {
        return new self(
            certPath: $certPath,
            keyPath: $keyPath,
            keyPassword: $keyPassword,
        );
    }

    /**
     * Create with custom CA bundle.
     */
    public static function withCaBundle(string $path): self
    {
        return new self(caBundlePath: $path);
    }

    /**
     * Convert to Guzzle configuration.
     *
     * @return array<string, mixed>
     */
    public function toGuzzleConfig(): array
    {
        $config = [];

        // Set verification
        if (!$this->verify) {
            $config['verify'] = false;
        } elseif ($this->caBundlePath !== null) {
            $config['verify'] = $this->caBundlePath;
        }

        // Set client certificate
        if ($this->certPath !== null) {
            if ($this->keyPath !== null) {
                $config['cert'] = $this->keyPassword !== null
                    ? [$this->certPath, $this->keyPassword]
                    : $this->certPath;
                $config['ssl_key'] = $this->keyPassword !== null
                    ? [$this->keyPath, $this->keyPassword]
                    : $this->keyPath;
            } else {
                $config['cert'] = $this->keyPassword !== null
                    ? [$this->certPath, $this->keyPassword]
                    : $this->certPath;
            }
        }

        return $config;
    }
}

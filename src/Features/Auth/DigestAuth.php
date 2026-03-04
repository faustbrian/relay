<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Features\Auth;

use Cline\Relay\Core\Request;
use Cline\Relay\Support\Contracts\Authenticator;

/**
 * HTTP Digest authentication.
 *
 * Note: Digest authentication requires special handling at the HTTP client level.
 * This authenticator stores credentials that can be used when configuring the
 * Guzzle client with the 'auth' option set to ['username', 'password', 'digest'].
 *
 * For usage with Relay's Connector, override the defaultConfig() method:
 *
 * ```php
 * public function defaultConfig(): array
 * {
 *     return [
 *         'auth' => [$this->digestAuth->username(), $this->digestAuth->password(), 'digest'],
 *     ];
 * }
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class DigestAuth implements Authenticator
{
    public function __construct(
        private string $username,
        private string $password,
    ) {}

    /**
     * Digest auth cannot be applied via headers alone.
     * Returns the request unchanged - use toGuzzleAuth() for Guzzle config.
     */
    public function authenticate(Request $request): Request
    {
        // Digest auth must be handled at the HTTP client level
        // This is a no-op - use toGuzzleAuth() in connector's defaultConfig()
        return $request;
    }

    /**
     * Get the username.
     */
    public function username(): string
    {
        return $this->username;
    }

    /**
     * Get the password.
     */
    public function password(): string
    {
        return $this->password;
    }

    /**
     * Get the Guzzle auth configuration array.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    public function toGuzzleAuth(): array
    {
        return [$this->username, $this->password, 'digest'];
    }
}

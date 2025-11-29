<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Contracts;

use DateTimeImmutable;

/**
 * Interface for OAuth authenticators with token management.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface OAuthAuthenticator extends Authenticator
{
    /**
     * Unserialize an authenticator from storage.
     */
    public static function unserialize(string $serialized): static;

    /**
     * Get the access token.
     */
    public function getAccessToken(): string;

    /**
     * Get the refresh token.
     */
    public function getRefreshToken(): ?string;

    /**
     * Get the token expiration time.
     */
    public function getExpiresAt(): ?DateTimeImmutable;

    /**
     * Check if the token has expired.
     */
    public function hasExpired(): bool;

    /**
     * Check if the token has not expired.
     */
    public function hasNotExpired(): bool;

    /**
     * Check if the authenticator can be refreshed.
     */
    public function isRefreshable(): bool;

    /**
     * Check if the authenticator cannot be refreshed.
     */
    public function isNotRefreshable(): bool;

    /**
     * Serialize the authenticator for storage.
     */
    public function serialize(): string;
}

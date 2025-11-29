<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Features\Auth;

use Cline\Relay\Core\Connector;
use Cline\Relay\Core\Request;
use Cline\Relay\Support\Contracts\Authenticator;
use Cline\Relay\Support\Contracts\OAuthAuthenticator;
use Closure;

/**
 * Authenticator that automatically refreshes expired OAuth tokens.
 *
 * This authenticator wraps an OAuthAuthenticator and automatically refreshes
 * the access token when it has expired. The connector must use the
 * AuthorizationCodeGrant trait to support token refresh.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @phpstan-type ConnectorWithOAuth Connector
 */
final class AutoRefreshAuthenticator implements Authenticator
{
    /**
     * @param Connector                              $connector     The connector with OAuth support (must use AuthorizationCodeGrant trait)
     * @param OAuthAuthenticator                     $authenticator The current authenticator
     * @param null|Closure(OAuthAuthenticator): void $onRefresh     Callback when token is refreshed (e.g., to save to cache)
     *
     * @phpstan-param ConnectorWithOAuth $connector
     */
    public function __construct(
        private readonly Connector $connector,
        private OAuthAuthenticator $authenticator,
        private readonly ?Closure $onRefresh = null,
    ) {}

    /**
     * Authenticate the request, refreshing the token if expired.
     */
    public function authenticate(Request $request): Request
    {
        if ($this->authenticator->hasExpired() && $this->authenticator->isRefreshable()) {
            $this->refresh();
        }

        return $this->authenticator->authenticate($request);
    }

    /**
     * Get the current authenticator.
     */
    public function getAuthenticator(): OAuthAuthenticator
    {
        return $this->authenticator;
    }

    /**
     * Manually refresh the token.
     */
    public function refresh(): void
    {
        /**
         * @var OAuthAuthenticator $newAuthenticator
         *
         * @phpstan-ignore method.notFound (Method exists via AuthorizationCodeGrant trait)
         */
        $newAuthenticator = $this->connector->refreshAccessToken($this->authenticator);
        $this->authenticator = $newAuthenticator;

        if ($this->onRefresh instanceof Closure) {
            ($this->onRefresh)($newAuthenticator);
        }
    }

    /**
     * Check if the current token has expired.
     */
    public function hasExpired(): bool
    {
        return $this->authenticator->hasExpired();
    }

    /**
     * Check if the current token is refreshable.
     */
    public function isRefreshable(): bool
    {
        return $this->authenticator->isRefreshable();
    }

    /**
     * Get the current access token.
     */
    public function getAccessToken(): string
    {
        return $this->authenticator->getAccessToken();
    }

    /**
     * Get the current refresh token.
     */
    public function getRefreshToken(): ?string
    {
        return $this->authenticator->getRefreshToken();
    }
}

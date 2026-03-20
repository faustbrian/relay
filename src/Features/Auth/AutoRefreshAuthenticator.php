<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Features\Auth;

use Cline\Relay\Core\AbstractConnector;
use Cline\Relay\Core\AbstractRequest;
use Cline\Relay\Support\Contracts\AuthenticatorInterface;
use Cline\Relay\Support\Contracts\OAuthAuthenticatorInterface;
use Closure;

/**
 * AuthenticatorInterface that automatically refreshes expired OAuth tokens.
 *
 * This authenticator wraps an OAuthAuthenticatorInterface and automatically refreshes
 * the access token when it has expired. The connector must use the
 * AuthorizationCodeGrant trait to support token refresh.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @phpstan-type ConnectorWithOAuth AbstractConnector
 */
final class AutoRefreshAuthenticator implements AuthenticatorInterface
{
    /**
     * @param AbstractConnector                               $connector     The connector with OAuth support (must use AuthorizationCodeGrant trait)
     * @param OAuthAuthenticatorInterface                     $authenticator The current authenticator
     * @param null|Closure(OAuthAuthenticatorInterface): void $onRefresh     Callback when token is refreshed (e.g., to save to cache)
     *
     * @phpstan-param ConnectorWithOAuth $connector
     */
    public function __construct(
        private readonly AbstractConnector $connector,
        private OAuthAuthenticatorInterface $authenticator,
        private readonly ?Closure $onRefresh = null,
    ) {}

    /**
     * Authenticate the request, refreshing the token if expired.
     */
    public function authenticate(AbstractRequest $request): AbstractRequest
    {
        if ($this->authenticator->hasExpired() && $this->authenticator->isRefreshable()) {
            $this->refresh();
        }

        return $this->authenticator->authenticate($request);
    }

    /**
     * Get the current authenticator.
     */
    public function getAuthenticator(): OAuthAuthenticatorInterface
    {
        return $this->authenticator;
    }

    /**
     * Manually refresh the token.
     */
    public function refresh(): void
    {
        /**
         * @var OAuthAuthenticatorInterface $newAuthenticator
         *
         * @phpstan-ignore method.notFound (Method exists via AuthorizationCodeGrant trait)
         */
        $newAuthenticator = $this->connector->refreshAccessToken($this->authenticator);
        $this->authenticator = $newAuthenticator;

        if (!$this->onRefresh instanceof Closure) {
            return;
        }

        ($this->onRefresh)($newAuthenticator);
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

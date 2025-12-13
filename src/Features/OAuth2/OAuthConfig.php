<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Features\OAuth2;

use Cline\Relay\Core\Request;
use Cline\Relay\Support\Contracts\OAuthAuthenticator;
use Cline\Relay\Support\Exceptions\OAuthConfigException;
use Closure;

/**
 * OAuth2 configuration.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class OAuthConfig
{
    private string $clientId = '';

    private string $clientSecret = '';

    private string $redirectUri = '';

    private string $authorizeEndpoint = 'authorize';

    private string $tokenEndpoint = 'token';

    private string $userEndpoint = 'user';

    /** @var array<string> */
    private array $defaultScopes = [];

    /** @var null|Closure(Request): void */
    private ?Closure $requestModifier = null;

    /** @var null|Closure(OAuthAuthenticator, OAuthAuthenticator): void */
    private ?Closure $onTokenRefreshed = null;

    private bool $autoRefreshOn401 = false;

    private bool $usePkce = false;

    public static function make(): self
    {
        return new self();
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function setClientId(string $clientId): static
    {
        $this->clientId = $clientId;

        return $this;
    }

    public function getClientSecret(): string
    {
        return $this->clientSecret;
    }

    public function setClientSecret(string $clientSecret): static
    {
        $this->clientSecret = $clientSecret;

        return $this;
    }

    public function getRedirectUri(): string
    {
        return $this->redirectUri;
    }

    public function setRedirectUri(string $redirectUri): static
    {
        $this->redirectUri = $redirectUri;

        return $this;
    }

    public function getAuthorizeEndpoint(): string
    {
        return $this->authorizeEndpoint;
    }

    public function setAuthorizeEndpoint(string $authorizeEndpoint): static
    {
        $this->authorizeEndpoint = $authorizeEndpoint;

        return $this;
    }

    public function getTokenEndpoint(): string
    {
        return $this->tokenEndpoint;
    }

    public function setTokenEndpoint(string $tokenEndpoint): static
    {
        $this->tokenEndpoint = $tokenEndpoint;

        return $this;
    }

    public function getUserEndpoint(): string
    {
        return $this->userEndpoint;
    }

    public function setUserEndpoint(string $userEndpoint): static
    {
        $this->userEndpoint = $userEndpoint;

        return $this;
    }

    /**
     * @return array<string>
     */
    public function getDefaultScopes(): array
    {
        return $this->defaultScopes;
    }

    /**
     * @param array<string> $defaultScopes
     */
    public function setDefaultScopes(array $defaultScopes): static
    {
        $this->defaultScopes = $defaultScopes;

        return $this;
    }

    /**
     * @param callable(Request): void $requestModifier
     */
    public function setRequestModifier(callable $requestModifier): static
    {
        $this->requestModifier = $requestModifier(...);

        return $this;
    }

    public function invokeRequestModifier(Request $request): Request
    {
        if (!$this->requestModifier instanceof Closure) {
            return $request;
        }

        ($this->requestModifier)($request);

        return $request;
    }

    /**
     * Set a callback to be invoked when a token is refreshed.
     *
     * @param callable(OAuthAuthenticator $newToken, OAuthAuthenticator $oldToken): void $callback
     */
    public function setOnTokenRefreshed(callable $callback): static
    {
        $this->onTokenRefreshed = $callback(...);

        return $this;
    }

    /**
     * Invoke the token refreshed callback.
     */
    public function invokeOnTokenRefreshed(OAuthAuthenticator $newToken, OAuthAuthenticator $oldToken): void
    {
        if (!$this->onTokenRefreshed instanceof Closure) {
            return;
        }

        ($this->onTokenRefreshed)($newToken, $oldToken);
    }

    /**
     * Check if a token refreshed callback is set.
     */
    public function hasOnTokenRefreshed(): bool
    {
        return $this->onTokenRefreshed instanceof Closure;
    }

    /**
     * Enable automatic token refresh on 401 responses.
     */
    public function setAutoRefreshOn401(bool $enabled = true): static
    {
        $this->autoRefreshOn401 = $enabled;

        return $this;
    }

    /**
     * Check if auto refresh on 401 is enabled.
     */
    public function shouldAutoRefreshOn401(): bool
    {
        return $this->autoRefreshOn401;
    }

    /**
     * Enable PKCE (Proof Key for Code Exchange) for the authorization code flow.
     */
    public function setUsePkce(bool $enabled = true): static
    {
        $this->usePkce = $enabled;

        return $this;
    }

    /**
     * Check if PKCE is enabled.
     */
    public function usePkce(): bool
    {
        return $this->usePkce;
    }

    /**
     * @throws OAuthConfigException
     */
    public function validate(bool $withRedirectUrl = true): void
    {
        if ($this->clientId === '') {
            throw OAuthConfigException::missingClientId();
        }

        if ($this->clientSecret === '') {
            throw OAuthConfigException::missingClientSecret();
        }

        if ($withRedirectUrl && $this->redirectUri === '') {
            throw OAuthConfigException::missingRedirectUri();
        }
    }
}

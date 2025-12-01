<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\Auth;

use Cline\Relay\Core\Connector;
use Cline\Relay\Features\OAuth2\AuthorizationCodeGrant;
use Cline\Relay\Features\OAuth2\OAuthConfig;
use Cline\Relay\Support\Contracts\OAuthAuthenticator as OAuthAuthenticatorContract;
use Override;

/**
 * Connector fixture that simulates OAuth token refresh.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class RefreshableConnector extends Connector
{
    use AuthorizationCodeGrant;

    public OAuthAuthenticatorContract $newAuth;

    #[Override()]
    public function baseUrl(): string
    {
        return 'https://api.example.com';
    }

    #[Override()]
    public function oauthConfig(): OAuthConfig
    {
        return new OAuthConfig(
            clientId: 'test-client-id',
            clientSecret: 'test-client-secret',
            redirectUri: 'https://example.com/callback',
            authorizeEndpoint: '/oauth/authorize',
            tokenEndpoint: '/oauth/token',
        );
    }

    public function refreshAccessToken(
        OAuthAuthenticatorContract|string $refreshToken,
        bool $returnResponse = false,
        ?callable $requestModifier = null,
    ): OAuthAuthenticatorContract {
        return $this->newAuth;
    }
}

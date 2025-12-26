<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Features\OAuth2;

use Carbon\CarbonImmutable;
use Cline\Relay\Core\Connector;
use Cline\Relay\Core\Response;
use Cline\Relay\Features\Auth\AccessTokenAuthenticator;
use Cline\Relay\Support\Contracts\OAuthAuthenticator;
use DateInterval;
use DateTimeImmutable;

use function array_key_exists;
use function is_callable;
use function is_numeric;

/**
 * Trait for OAuth2 Client Credentials Grant flow.
 *
 * @phpstan-require-extends Connector
 *
 * @author Brian Faust <brian@cline.sh>
 */
trait ClientCredentialsGrant
{
    /**
     * Get an access token using client credentials.
     *
     * @param  array<string>                                             $scopes
     * @param  null|callable(GetClientCredentialsTokenRequest): void     $requestModifier
     * @return ($returnResponse is true ? Response : OAuthAuthenticator)
     */
    public function getAccessToken(
        array $scopes = [],
        string $scopeSeparator = ' ',
        bool $returnResponse = false,
        ?callable $requestModifier = null,
    ): OAuthAuthenticator|Response {
        $this->oauthConfig()->validate(withRedirectUrl: false);

        $request = $this->resolveAccessTokenRequest($this->oauthConfig(), $scopes, $scopeSeparator);
        $request = $this->oauthConfig()->invokeRequestModifier($request);

        if (is_callable($requestModifier)) {
            $requestModifier($request);
        }

        $response = $this->send($request);

        if ($returnResponse) {
            return $response;
        }

        $response->throw();

        return $this->createOAuthAuthenticatorFromResponse($response);
    }

    /**
     * Get the OAuth2 configuration.
     */
    abstract public function oauthConfig(): OAuthConfig;

    /**
     * Create an authenticator from the token response.
     */
    protected function createOAuthAuthenticatorFromResponse(Response $response): OAuthAuthenticator
    {
        $data = $response->json();

        $accessToken = $data['access_token'];
        $expiresAt = null;

        if (array_key_exists('expires_in', $data) && is_numeric($data['expires_in'])) {
            $expiresAt = CarbonImmutable::now()->add(
                DateInterval::createFromDateString((int) $data['expires_in'].' seconds'),
            );
        }

        return $this->createOAuthAuthenticator($accessToken, $expiresAt);
    }

    /**
     * Create the OAuthAuthenticator instance.
     *
     * Override this method to use a custom authenticator class.
     */
    protected function createOAuthAuthenticator(string $accessToken, ?DateTimeImmutable $expiresAt = null): OAuthAuthenticator
    {
        return new AccessTokenAuthenticator($accessToken, null, $expiresAt);
    }

    /**
     * Resolve the access token request.
     *
     * Override this method to use a custom request class.
     *
     * @param array<string> $scopes
     */
    protected function resolveAccessTokenRequest(OAuthConfig $config, array $scopes = [], string $scopeSeparator = ' '): GetClientCredentialsTokenRequest
    {
        return new GetClientCredentialsTokenRequest($config, $scopes, $scopeSeparator);
    }
}

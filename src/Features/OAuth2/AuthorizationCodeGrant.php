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
use Cline\Relay\Support\Exceptions\InvalidStateException;
use Cline\Relay\Support\Exceptions\OAuthRefreshTokenException;
use DateInterval;
use DateTimeImmutable;

use const PHP_QUERY_RFC3986;

use function array_filter;
use function array_key_exists;
use function array_merge;
use function bin2hex;
use function http_build_query;
use function implode;
use function is_callable;
use function is_numeric;
use function mb_ltrim;
use function mb_rtrim;
use function random_bytes;
use function str_contains;
use function throw_if;

/**
 * Trait for OAuth2 Authorization Code Grant flow.
 *
 * @phpstan-require-extends Connector
 *
 * @author Brian Faust <brian@cline.sh>
 */
trait AuthorizationCodeGrant
{
    protected ?string $state = null;

    protected ?string $codeVerifier = null;

    /**
     * Get the authorization URL.
     *
     * @param array<string>         $scopes
     * @param array<string, string> $additionalQueryParameters
     */
    public function getAuthorizationUrl(
        array $scopes = [],
        ?string $state = null,
        string $scopeSeparator = ' ',
        array $additionalQueryParameters = [],
    ): string {
        $config = $this->oauthConfig();
        $config->validate();

        $this->state = $state ?? bin2hex(random_bytes(16));

        $queryParameters = array_filter([
            'response_type' => 'code',
            'scope' => implode($scopeSeparator, array_filter(array_merge($config->getDefaultScopes(), $scopes))),
            'client_id' => $config->getClientId(),
            'redirect_uri' => $config->getRedirectUri(),
            'state' => $this->state,
            ...$additionalQueryParameters,
        ]);

        // Add PKCE parameters if enabled
        if ($config->usePkce()) {
            $pkce = Pkce::generate();
            $this->codeVerifier = $pkce['verifier'];
            $queryParameters['code_challenge'] = $pkce['challenge'];
            $queryParameters['code_challenge_method'] = $pkce['method'];
        }

        $query = http_build_query($queryParameters, '', '&', PHP_QUERY_RFC3986);
        $url = $this->joinUrl($this->resolveBaseUrl(), $config->getAuthorizeEndpoint());
        $glue = str_contains((string) $url, '?') ? '&' : '?';

        return $url.$glue.$query;
    }

    /**
     * Exchange authorization code for access token.
     *
     * @param null|callable(GetAccessTokenRequest): void $requestModifier
     *
     * @throws InvalidStateException
     *
     * @return ($returnResponse is true ? Response : OAuthAuthenticator)
     */
    public function getAccessToken(
        string $code,
        ?string $state = null,
        ?string $expectedState = null,
        bool $returnResponse = false,
        ?callable $requestModifier = null,
        ?string $codeVerifier = null,
    ): OAuthAuthenticator|Response {
        $this->oauthConfig()->validate();

        throw_if($state !== null && $expectedState !== null && $state !== $expectedState, InvalidStateException::class);

        // Use provided code_verifier or the one stored from getAuthorizationUrl()
        $verifier = $codeVerifier ?? $this->codeVerifier;

        $request = $this->resolveAccessTokenRequest($code, $this->oauthConfig(), $verifier);
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
     * Refresh an access token.
     *
     * @param  null|callable(GetRefreshTokenRequest): void               $requestModifier
     * @return ($returnResponse is true ? Response : OAuthAuthenticator)
     */
    public function refreshAccessToken(
        OAuthAuthenticator|string $refreshToken,
        bool $returnResponse = false,
        ?callable $requestModifier = null,
    ): OAuthAuthenticator|Response {
        $this->oauthConfig()->validate();

        $oldAuthenticator = $refreshToken instanceof OAuthAuthenticator ? $refreshToken : null;

        if ($refreshToken instanceof OAuthAuthenticator) {
            throw_if($refreshToken->isNotRefreshable(), OAuthRefreshTokenException::missingRefreshToken());

            $refreshToken = $refreshToken->getRefreshToken();
        }

        $request = $this->resolveRefreshTokenRequest($this->oauthConfig(), $refreshToken);
        $request = $this->oauthConfig()->invokeRequestModifier($request);

        if (is_callable($requestModifier)) {
            $requestModifier($request);
        }

        $response = $this->send($request);

        if ($returnResponse) {
            return $response;
        }

        $response->throw();

        $newAuthenticator = $this->createOAuthAuthenticatorFromResponse($response, $refreshToken);

        if ($oldAuthenticator instanceof OAuthAuthenticator) {
            $this->oauthConfig()->invokeOnTokenRefreshed($newAuthenticator, $oldAuthenticator);
        }

        return $newAuthenticator;
    }

    /**
     * Get the authenticated user.
     *
     * @param null|callable(GetUserRequest): void $requestModifier
     */
    public function getUser(OAuthAuthenticator $authenticator, ?callable $requestModifier = null): Response
    {
        $request = $this->resolveUserRequest($this->oauthConfig());
        $request = $authenticator->authenticate($request);
        $request = $this->oauthConfig()->invokeRequestModifier($request);

        if (is_callable($requestModifier)) {
            $requestModifier($request);
        }

        return $this->send($request);
    }

    /**
     * Get the state generated by getAuthorizationUrl().
     */
    public function getState(): ?string
    {
        return $this->state;
    }

    /**
     * Get the code verifier generated by getAuthorizationUrl() when PKCE is enabled.
     */
    public function getCodeVerifier(): ?string
    {
        return $this->codeVerifier;
    }

    /**
     * Get the OAuth2 configuration.
     */
    abstract public function oauthConfig(): OAuthConfig;

    /**
     * Create an authenticator from the token response.
     */
    protected function createOAuthAuthenticatorFromResponse(Response $response, ?string $fallbackRefreshToken = null): OAuthAuthenticator
    {
        $data = $response->json();

        $accessToken = $data['access_token'];
        $refreshToken = $data['refresh_token'] ?? $fallbackRefreshToken;
        $expiresAt = null;

        if (array_key_exists('expires_in', $data) && is_numeric($data['expires_in'])) {
            $expiresAt = CarbonImmutable::now()->add(
                DateInterval::createFromDateString((int) $data['expires_in'].' seconds'),
            );
        }

        return $this->createOAuthAuthenticator($accessToken, $refreshToken, $expiresAt);
    }

    /**
     * Create the OAuthAuthenticator instance.
     *
     * Override this method to use a custom authenticator class.
     */
    protected function createOAuthAuthenticator(
        string $accessToken,
        ?string $refreshToken = null,
        ?DateTimeImmutable $expiresAt = null,
    ): OAuthAuthenticator {
        return new AccessTokenAuthenticator($accessToken, $refreshToken, $expiresAt);
    }

    /**
     * Resolve the access token request.
     *
     * Override this method to use a custom request class.
     */
    protected function resolveAccessTokenRequest(string $code, OAuthConfig $config, ?string $codeVerifier = null): GetAccessTokenRequest
    {
        return new GetAccessTokenRequest($code, $config, $codeVerifier);
    }

    /**
     * Resolve the refresh token request.
     *
     * Override this method to use a custom request class.
     */
    protected function resolveRefreshTokenRequest(OAuthConfig $config, string $refreshToken): GetRefreshTokenRequest
    {
        return new GetRefreshTokenRequest($config, $refreshToken);
    }

    /**
     * Resolve the user request.
     *
     * Override this method to use a custom request class.
     */
    protected function resolveUserRequest(OAuthConfig $config): GetUserRequest
    {
        return new GetUserRequest($config);
    }

    /**
     * Join URL parts.
     */
    private function joinUrl(string $baseUrl, string $endpoint): string
    {
        return mb_rtrim($baseUrl, '/').'/'.mb_ltrim($endpoint, '/');
    }
}

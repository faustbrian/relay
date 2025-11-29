<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Carbon\CarbonImmutable;
use Cline\Relay\Core\Connector;
use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;
use Cline\Relay\Features\Auth\AccessTokenAuthenticator;
use Cline\Relay\Features\OAuth2\AuthorizationCodeGrant;
use Cline\Relay\Features\OAuth2\ClientCredentialsGrant;
use Cline\Relay\Features\OAuth2\GetAccessTokenRequest;
use Cline\Relay\Features\OAuth2\GetClientCredentialsTokenRequest;
use Cline\Relay\Features\OAuth2\GetRefreshTokenRequest;
use Cline\Relay\Features\OAuth2\GetUserRequest;
use Cline\Relay\Features\OAuth2\OAuthConfig;
use Cline\Relay\Features\OAuth2\Pkce;
use Cline\Relay\Support\Exceptions\InvalidStateException;
use Cline\Relay\Support\Exceptions\OAuthConfigException;
use Cline\Relay\Testing\MockResponse;
use Tests\Fixtures\MockableTrait;

describe('OAuth2', function (): void {
    describe('OAuthConfig', function (): void {
        it('can be created with make()', function (): void {
            $config = OAuthConfig::make();

            expect($config)->toBeInstanceOf(OAuthConfig::class);
        });

        it('can set and get client ID', function (): void {
            $config = OAuthConfig::make()
                ->setClientId('test-client-id');

            expect($config->getClientId())->toBe('test-client-id');
        });

        it('can set and get client secret', function (): void {
            $config = OAuthConfig::make()
                ->setClientSecret('test-client-secret');

            expect($config->getClientSecret())->toBe('test-client-secret');
        });

        it('can set and get redirect URI', function (): void {
            $config = OAuthConfig::make()
                ->setRedirectUri('https://example.com/callback');

            expect($config->getRedirectUri())->toBe('https://example.com/callback');
        });

        it('can set and get endpoints', function (): void {
            $config = OAuthConfig::make()
                ->setAuthorizeEndpoint('oauth/authorize')
                ->setTokenEndpoint('oauth/token')
                ->setUserEndpoint('api/me');

            expect($config->getAuthorizeEndpoint())->toBe('oauth/authorize');
            expect($config->getTokenEndpoint())->toBe('oauth/token');
            expect($config->getUserEndpoint())->toBe('api/me');
        });

        it('has default endpoints', function (): void {
            $config = OAuthConfig::make();

            expect($config->getAuthorizeEndpoint())->toBe('authorize');
            expect($config->getTokenEndpoint())->toBe('token');
            expect($config->getUserEndpoint())->toBe('user');
        });

        it('can set and get default scopes', function (): void {
            $config = OAuthConfig::make()
                ->setDefaultScopes(['read', 'write']);

            expect($config->getDefaultScopes())->toBe(['read', 'write']);
        });

        it('throws exception when client ID is missing during validation', function (): void {
            $config = OAuthConfig::make()
                ->setClientSecret('secret')
                ->setRedirectUri('https://example.com/callback');

            $config->validate();
        })->throws(OAuthConfigException::class, 'Client ID');

        it('throws exception when client secret is missing during validation', function (): void {
            $config = OAuthConfig::make()
                ->setClientId('client-id')
                ->setRedirectUri('https://example.com/callback');

            $config->validate();
        })->throws(OAuthConfigException::class, 'Client Secret');

        it('throws exception when redirect URI is missing during validation', function (): void {
            $config = OAuthConfig::make()
                ->setClientId('client-id')
                ->setClientSecret('secret');

            $config->validate();
        })->throws(OAuthConfigException::class, 'Redirect URI');

        it('validates without redirect URI when not required', function (): void {
            $config = OAuthConfig::make()
                ->setClientId('client-id')
                ->setClientSecret('secret');

            $config->validate(withRedirectUrl: false);

            expect(true)->toBeTrue(); // No exception thrown
        });

        it('can invoke request modifier', function (): void {
            $modified = false;

            $config = OAuthConfig::make()
                ->setRequestModifier(function (Request $request) use (&$modified): void {
                    $modified = true;
                });

            $request = new class() extends Request
            {
                public function endpoint(): string
                {
                    return '/test';
                }
            };

            $config->invokeRequestModifier($request);

            expect($modified)->toBeTrue();
        });

        it('can set and check auto refresh on 401', function (): void {
            $config = OAuthConfig::make();

            expect($config->shouldAutoRefreshOn401())->toBeFalse();

            $config->setAutoRefreshOn401(true);

            expect($config->shouldAutoRefreshOn401())->toBeTrue();
        });

        it('can set and invoke token refreshed callback', function (): void {
            $callbackInvoked = false;
            $receivedNew = null;
            $receivedOld = null;

            $config = OAuthConfig::make()
                ->setOnTokenRefreshed(function ($new, $old) use (&$callbackInvoked, &$receivedNew, &$receivedOld): void {
                    $callbackInvoked = true;
                    $receivedNew = $new;
                    $receivedOld = $old;
                });

            expect($config->hasOnTokenRefreshed())->toBeTrue();

            $oldAuth = new AccessTokenAuthenticator('old-token', 'refresh-token');
            $newAuth = new AccessTokenAuthenticator('new-token', 'new-refresh-token');

            $config->invokeOnTokenRefreshed($newAuth, $oldAuth);

            expect($callbackInvoked)->toBeTrue();
            expect($receivedNew)->toBe($newAuth);
            expect($receivedOld)->toBe($oldAuth);
        });

        it('does not invoke callback when not set', function (): void {
            $config = OAuthConfig::make();

            expect($config->hasOnTokenRefreshed())->toBeFalse();

            $oldAuth = new AccessTokenAuthenticator('old-token', 'refresh-token');
            $newAuth = new AccessTokenAuthenticator('new-token', 'new-refresh-token');

            // Should not throw
            $config->invokeOnTokenRefreshed($newAuth, $oldAuth);

            expect(true)->toBeTrue();
        });
    });

    describe('AccessTokenAuthenticator', function (): void {
        it('can be created with access token only', function (): void {
            $auth = new AccessTokenAuthenticator('test-token');

            expect($auth->getAccessToken())->toBe('test-token');
            expect($auth->getRefreshToken())->toBeNull();
            expect($auth->getExpiresAt())->toBeNull();
        });

        it('can be created with refresh token', function (): void {
            $auth = new AccessTokenAuthenticator('access', 'refresh');

            expect($auth->getAccessToken())->toBe('access');
            expect($auth->getRefreshToken())->toBe('refresh');
        });

        it('can be created with expiration', function (): void {
            $expiresAt = CarbonImmutable::now()->addHours(1);
            $auth = new AccessTokenAuthenticator('access', null, $expiresAt);

            expect($auth->getExpiresAt())->toBe($expiresAt);
        });

        it('is refreshable when has refresh token', function (): void {
            $auth = new AccessTokenAuthenticator('access', 'refresh');

            expect($auth->isRefreshable())->toBeTrue();
            expect($auth->isNotRefreshable())->toBeFalse();
        });

        it('is not refreshable when no refresh token', function (): void {
            $auth = new AccessTokenAuthenticator('access');

            expect($auth->isRefreshable())->toBeFalse();
            expect($auth->isNotRefreshable())->toBeTrue();
        });

        it('has not expired when no expiration set', function (): void {
            $auth = new AccessTokenAuthenticator('access');

            expect($auth->hasExpired())->toBeFalse();
            expect($auth->hasNotExpired())->toBeTrue();
        });

        it('has not expired when expiration is in the future', function (): void {
            $expiresAt = CarbonImmutable::now()->addHours(1);
            $auth = new AccessTokenAuthenticator('access', null, $expiresAt);

            expect($auth->hasExpired())->toBeFalse();
            expect($auth->hasNotExpired())->toBeTrue();
        });

        it('has expired when expiration is in the past', function (): void {
            $expiresAt = CarbonImmutable::now()->subHours(1);
            $auth = new AccessTokenAuthenticator('access', null, $expiresAt);

            expect($auth->hasExpired())->toBeTrue();
            expect($auth->hasNotExpired())->toBeFalse();
        });

        it('authenticates request with bearer token', function (): void {
            $auth = new AccessTokenAuthenticator('my-access-token');

            $request = new class() extends Request
            {
                public function endpoint(): string
                {
                    return '/test';
                }
            };

            $authenticated = $auth->authenticate($request);

            expect($authenticated->allHeaders()['Authorization'])->toBe('Bearer my-access-token');
        });

        it('can be serialized and unserialized', function (): void {
            $expiresAt = CarbonImmutable::now()->addHours(1);
            $auth = new AccessTokenAuthenticator('access', 'refresh', $expiresAt);

            $serialized = $auth->serialize();
            $restored = AccessTokenAuthenticator::unserialize($serialized);

            expect($restored->getAccessToken())->toBe('access');
            expect($restored->getRefreshToken())->toBe('refresh');
            expect($restored->getExpiresAt()->format('c'))->toBe($expiresAt->format('c'));
        });
    });

    describe('InvalidStateException', function (): void {
        it('has a descriptive message', function (): void {
            $exception = new InvalidStateException();

            expect($exception->getMessage())->toContain('state');
        });
    });

    describe('PKCE', function (): void {
        it('generates a valid code verifier', function (): void {
            $verifier = Pkce::generateVerifier();

            // RFC 7636: verifier must be 43-128 characters
            expect(mb_strlen($verifier))->toBeGreaterThanOrEqual(43);
            expect(mb_strlen($verifier))->toBeLessThanOrEqual(128);

            // Must be base64url safe (no +, /, =)
            expect($verifier)->not->toContain('+');
            expect($verifier)->not->toContain('/');
            expect($verifier)->not->toContain('=');
        });

        it('generates a valid S256 code challenge', function (): void {
            $verifier = Pkce::generateVerifier();
            $challenge = Pkce::generateChallenge($verifier);

            // Challenge must be base64url safe
            expect($challenge)->not->toContain('+');
            expect($challenge)->not->toContain('/');
            expect($challenge)->not->toContain('=');

            // Challenge should be 43 characters (256 bits / 6 bits per base64 char)
            expect(mb_strlen($challenge))->toBe(43);
        });

        it('generates plain challenge when method is plain', function (): void {
            $verifier = Pkce::generateVerifier();
            $challenge = Pkce::generateChallenge($verifier, Pkce::METHOD_PLAIN);

            expect($challenge)->toBe($verifier);
        });

        it('generates a complete PKCE pair', function (): void {
            $pkce = Pkce::generate();

            expect($pkce)->toHaveKeys(['verifier', 'challenge', 'method']);
            expect($pkce['method'])->toBe(Pkce::METHOD_S256);
            expect(mb_strlen($pkce['verifier']))->toBeGreaterThanOrEqual(43);
            expect(mb_strlen($pkce['challenge']))->toBe(43);
        });

        it('generates unique verifiers each time', function (): void {
            $verifier1 = Pkce::generateVerifier();
            $verifier2 = Pkce::generateVerifier();

            expect($verifier1)->not->toBe($verifier2);
        });
    });

    describe('OAuthConfig PKCE', function (): void {
        it('has PKCE disabled by default', function (): void {
            $config = OAuthConfig::make();

            expect($config->usePkce())->toBeFalse();
        });

        it('can enable PKCE', function (): void {
            $config = OAuthConfig::make()
                ->setUsePkce(true);

            expect($config->usePkce())->toBeTrue();
        });

        it('can disable PKCE after enabling', function (): void {
            $config = OAuthConfig::make()
                ->setUsePkce(true)
                ->setUsePkce(false);

            expect($config->usePkce())->toBeFalse();
        });
    });

    describe('AuthorizationCodeGrant', function (): void {
        it('generates authorization URL with required parameters', function (): void {
            $connector = new class() extends Connector
            {
                use AuthorizationCodeGrant;

                private OAuthConfig $config;

                public function __construct()
                {
                    $this->config = OAuthConfig::make()
                        ->setClientId('test-client')
                        ->setClientSecret('test-secret')
                        ->setRedirectUri('https://example.com/callback');
                }

                public function oauthConfig(): OAuthConfig
                {
                    return $this->config;
                }

                public function baseUrl(): string
                {
                    return 'https://oauth.example.com';
                }
            };

            $url = $connector->getAuthorizationUrl();

            expect($url)->toContain('https://oauth.example.com/authorize');
            expect($url)->toContain('response_type=code');
            expect($url)->toContain('client_id=test-client');
            expect($url)->toContain('redirect_uri=');
            expect($url)->toContain('state=');
        });

        it('includes scopes in authorization URL', function (): void {
            $connector = new class() extends Connector
            {
                use AuthorizationCodeGrant;

                private OAuthConfig $config;

                public function __construct()
                {
                    $this->config = OAuthConfig::make()
                        ->setClientId('test-client')
                        ->setClientSecret('test-secret')
                        ->setRedirectUri('https://example.com/callback');
                }

                public function oauthConfig(): OAuthConfig
                {
                    return $this->config;
                }

                public function baseUrl(): string
                {
                    return 'https://oauth.example.com';
                }
            };

            $url = $connector->getAuthorizationUrl(['read', 'write']);

            expect($url)->toContain('scope=read%20write');
        });

        it('uses custom state when provided', function (): void {
            $connector = new class() extends Connector
            {
                use AuthorizationCodeGrant;

                private OAuthConfig $config;

                public function __construct()
                {
                    $this->config = OAuthConfig::make()
                        ->setClientId('test-client')
                        ->setClientSecret('test-secret')
                        ->setRedirectUri('https://example.com/callback');
                }

                public function oauthConfig(): OAuthConfig
                {
                    return $this->config;
                }

                public function baseUrl(): string
                {
                    return 'https://oauth.example.com';
                }
            };

            $url = $connector->getAuthorizationUrl(state: 'my-custom-state');

            expect($url)->toContain('state=my-custom-state');
            expect($connector->getState())->toBe('my-custom-state');
        });

        it('includes PKCE parameters when enabled', function (): void {
            $connector = new class() extends Connector
            {
                use AuthorizationCodeGrant;

                private OAuthConfig $config;

                public function __construct()
                {
                    $this->config = OAuthConfig::make()
                        ->setClientId('test-client')
                        ->setClientSecret('test-secret')
                        ->setRedirectUri('https://example.com/callback')
                        ->setUsePkce(true);
                }

                public function oauthConfig(): OAuthConfig
                {
                    return $this->config;
                }

                public function baseUrl(): string
                {
                    return 'https://oauth.example.com';
                }
            };

            $url = $connector->getAuthorizationUrl();

            expect($url)->toContain('code_challenge=');
            expect($url)->toContain('code_challenge_method=S256');
            expect($connector->getCodeVerifier())->not->toBeNull();
        });

        it('throws on state mismatch', function (): void {
            $connector = new class() extends Connector
            {
                use AuthorizationCodeGrant;

                private OAuthConfig $config;

                public function __construct()
                {
                    $this->config = OAuthConfig::make()
                        ->setClientId('test-client')
                        ->setClientSecret('test-secret')
                        ->setRedirectUri('https://example.com/callback');
                }

                public function oauthConfig(): OAuthConfig
                {
                    return $this->config;
                }

                public function baseUrl(): string
                {
                    return 'https://oauth.example.com';
                }
            };

            $connector->getAccessToken('code', state: 'received-state', expectedState: 'different-state');
        })->throws(InvalidStateException::class);

        it('throws when refreshing non-refreshable authenticator', function (): void {
            $connector = new class() extends Connector
            {
                use AuthorizationCodeGrant;

                private OAuthConfig $config;

                public function __construct()
                {
                    $this->config = OAuthConfig::make()
                        ->setClientId('test-client')
                        ->setClientSecret('test-secret')
                        ->setRedirectUri('https://example.com/callback');
                }

                public function oauthConfig(): OAuthConfig
                {
                    return $this->config;
                }

                public function baseUrl(): string
                {
                    return 'https://oauth.example.com';
                }
            };

            $oldAuth = new AccessTokenAuthenticator('old-token'); // No refresh token

            $connector->refreshAccessToken($oldAuth);
        })->throws(InvalidArgumentException::class, 'does not contain a refresh token');

        it('includes additional query parameters in authorization URL', function (): void {
            $connector = new class() extends Connector
            {
                use AuthorizationCodeGrant;

                private OAuthConfig $config;

                public function __construct()
                {
                    $this->config = OAuthConfig::make()
                        ->setClientId('test-client')
                        ->setClientSecret('test-secret')
                        ->setRedirectUri('https://example.com/callback');
                }

                public function oauthConfig(): OAuthConfig
                {
                    return $this->config;
                }

                public function baseUrl(): string
                {
                    return 'https://oauth.example.com';
                }
            };

            $url = $connector->getAuthorizationUrl(
                additionalQueryParameters: ['prompt' => 'consent', 'login_hint' => 'user@example.com'],
            );

            expect($url)->toContain('prompt=consent');
            expect($url)->toContain('login_hint=user%40example.com');
        });

        it('merges default scopes with provided scopes', function (): void {
            $connector = new class() extends Connector
            {
                use AuthorizationCodeGrant;

                private OAuthConfig $config;

                public function __construct()
                {
                    $this->config = OAuthConfig::make()
                        ->setClientId('test-client')
                        ->setClientSecret('test-secret')
                        ->setRedirectUri('https://example.com/callback')
                        ->setDefaultScopes(['openid', 'profile']);
                }

                public function oauthConfig(): OAuthConfig
                {
                    return $this->config;
                }

                public function baseUrl(): string
                {
                    return 'https://oauth.example.com';
                }
            };

            $url = $connector->getAuthorizationUrl(['email']);

            expect($url)->toContain('scope=openid%20profile%20email');
        });

        it('uses custom scope separator', function (): void {
            $connector = new class() extends Connector
            {
                use AuthorizationCodeGrant;

                private OAuthConfig $config;

                public function __construct()
                {
                    $this->config = OAuthConfig::make()
                        ->setClientId('test-client')
                        ->setClientSecret('test-secret')
                        ->setRedirectUri('https://example.com/callback');
                }

                public function oauthConfig(): OAuthConfig
                {
                    return $this->config;
                }

                public function baseUrl(): string
                {
                    return 'https://oauth.example.com';
                }
            };

            $url = $connector->getAuthorizationUrl(['read', 'write'], scopeSeparator: ',');

            expect($url)->toContain('scope=read%2Cwrite');
        });

        it('stores generated state for later retrieval', function (): void {
            $connector = new class() extends Connector
            {
                use AuthorizationCodeGrant;

                private OAuthConfig $config;

                public function __construct()
                {
                    $this->config = OAuthConfig::make()
                        ->setClientId('test-client')
                        ->setClientSecret('test-secret')
                        ->setRedirectUri('https://example.com/callback');
                }

                public function oauthConfig(): OAuthConfig
                {
                    return $this->config;
                }

                public function baseUrl(): string
                {
                    return 'https://oauth.example.com';
                }
            };

            // State should be null before calling getAuthorizationUrl
            expect($connector->getState())->toBeNull();

            $connector->getAuthorizationUrl();

            // State should be set after calling getAuthorizationUrl
            expect($connector->getState())->not->toBeNull();
            expect(mb_strlen((string) $connector->getState()))->toBe(32); // 16 bytes = 32 hex chars
        });

        it('stores code verifier when PKCE is enabled', function (): void {
            $connector = new class() extends Connector
            {
                use AuthorizationCodeGrant;

                private OAuthConfig $config;

                public function __construct()
                {
                    $this->config = OAuthConfig::make()
                        ->setClientId('test-client')
                        ->setClientSecret('test-secret')
                        ->setRedirectUri('https://example.com/callback')
                        ->setUsePkce(true);
                }

                public function oauthConfig(): OAuthConfig
                {
                    return $this->config;
                }

                public function baseUrl(): string
                {
                    return 'https://oauth.example.com';
                }
            };

            // Code verifier should be null before calling getAuthorizationUrl
            expect($connector->getCodeVerifier())->toBeNull();

            $connector->getAuthorizationUrl();

            // Code verifier should be set after calling getAuthorizationUrl with PKCE
            expect($connector->getCodeVerifier())->not->toBeNull();
            expect(mb_strlen((string) $connector->getCodeVerifier()))->toBeGreaterThanOrEqual(43);
        });

        it('handles authorize endpoint with existing query parameters', function (): void {
            $connector = new class() extends Connector
            {
                use AuthorizationCodeGrant;

                private OAuthConfig $config;

                public function __construct()
                {
                    $this->config = OAuthConfig::make()
                        ->setClientId('test-client')
                        ->setClientSecret('test-secret')
                        ->setRedirectUri('https://example.com/callback')
                        ->setAuthorizeEndpoint('oauth/authorize?existing=param');
                }

                public function oauthConfig(): OAuthConfig
                {
                    return $this->config;
                }

                public function baseUrl(): string
                {
                    return 'https://oauth.example.com';
                }
            };

            $url = $connector->getAuthorizationUrl();

            // Should use & instead of ? since URL already has query params
            expect($url)->toContain('oauth/authorize?existing=param&');
            expect($url)->toContain('response_type=code');
        });
    });

    describe('ClientCredentialsGrant', function (): void {
        it('validates config without redirect URI', function (): void {
            // Client credentials flow doesn't require redirect URI
            $connector = new class() extends Connector
            {
                use ClientCredentialsGrant;

                private OAuthConfig $config;

                public function __construct()
                {
                    $this->config = OAuthConfig::make()
                        ->setClientId('test-client')
                        ->setClientSecret('test-secret');
                    // No redirect URI - should be valid for client credentials
                }

                public function oauthConfig(): OAuthConfig
                {
                    return $this->config;
                }

                public function baseUrl(): string
                {
                    return 'https://oauth.example.com';
                }
            };

            // Should not throw - client credentials doesn't need redirect URI
            expect($connector->oauthConfig()->getClientId())->toBe('test-client');
            expect($connector->oauthConfig()->getClientSecret())->toBe('test-secret');
        });

        it('gets access token with client credentials', function (): void {
            $connector = new class() extends Connector
            {
                use ClientCredentialsGrant;
                use MockableTrait;

                private OAuthConfig $config;

                public function __construct()
                {
                    $this->config = OAuthConfig::make()
                        ->setClientId('test-client')
                        ->setClientSecret('test-secret');
                }

                public function oauthConfig(): OAuthConfig
                {
                    return $this->config;
                }

                public function baseUrl(): string
                {
                    return 'https://oauth.example.com';
                }
            };

            $connector->addResponse(MockResponse::json([
                'access_token' => 'client-credentials-token',
                'expires_in' => 3_600,
            ]));

            $authenticator = $connector->getAccessToken();

            expect($authenticator)->toBeInstanceOf(AccessTokenAuthenticator::class);
            expect($authenticator->getAccessToken())->toBe('client-credentials-token');
            expect($authenticator->getRefreshToken())->toBeNull();
        });

        it('includes scopes in request', function (): void {
            $connector = new class() extends Connector
            {
                use ClientCredentialsGrant;
                use MockableTrait;

                private OAuthConfig $config;

                public function __construct()
                {
                    $this->config = OAuthConfig::make()
                        ->setClientId('test-client')
                        ->setClientSecret('test-secret');
                }

                public function oauthConfig(): OAuthConfig
                {
                    return $this->config;
                }

                public function baseUrl(): string
                {
                    return 'https://oauth.example.com';
                }
            };

            $connector->addResponse(MockResponse::json([
                'access_token' => 'token',
                'expires_in' => 3_600,
            ]));

            $connector->getAccessToken(['read', 'write']);

            $request = $connector->lastRequest();
            expect($request->body()['scope'])->toBe('read write');
        });

        it('returns response when returnResponse is true', function (): void {
            $connector = new class() extends Connector
            {
                use ClientCredentialsGrant;
                use MockableTrait;

                private OAuthConfig $config;

                public function __construct()
                {
                    $this->config = OAuthConfig::make()
                        ->setClientId('test-client')
                        ->setClientSecret('test-secret');
                }

                public function oauthConfig(): OAuthConfig
                {
                    return $this->config;
                }

                public function baseUrl(): string
                {
                    return 'https://oauth.example.com';
                }
            };

            $connector->addResponse(MockResponse::json([
                'access_token' => 'token',
            ]));

            $response = $connector->getAccessToken(returnResponse: true);

            expect($response)->toBeInstanceOf(Response::class);
        });

        it('uses custom scope separator', function (): void {
            $connector = new class() extends Connector
            {
                use ClientCredentialsGrant;
                use MockableTrait;

                private OAuthConfig $config;

                public function __construct()
                {
                    $this->config = OAuthConfig::make()
                        ->setClientId('test-client')
                        ->setClientSecret('test-secret');
                }

                public function oauthConfig(): OAuthConfig
                {
                    return $this->config;
                }

                public function baseUrl(): string
                {
                    return 'https://oauth.example.com';
                }
            };

            $connector->addResponse(MockResponse::json([
                'access_token' => 'token',
                'expires_in' => 3_600,
            ]));

            $connector->getAccessToken(['read', 'write'], scopeSeparator: ',');

            $request = $connector->lastRequest();
            expect($request->body()['scope'])->toBe('read,write');
        });

        it('applies request modifier', function (): void {
            $modifierCalled = false;

            $connector = new class() extends Connector
            {
                use ClientCredentialsGrant;
                use MockableTrait;

                private OAuthConfig $config;

                public function __construct()
                {
                    $this->config = OAuthConfig::make()
                        ->setClientId('test-client')
                        ->setClientSecret('test-secret');
                }

                public function oauthConfig(): OAuthConfig
                {
                    return $this->config;
                }

                public function baseUrl(): string
                {
                    return 'https://oauth.example.com';
                }
            };

            $connector->addResponse(MockResponse::json([
                'access_token' => 'token',
                'expires_in' => 3_600,
            ]));

            $connector->getAccessToken(requestModifier: function ($request) use (&$modifierCalled): void {
                $modifierCalled = true;
            });

            expect($modifierCalled)->toBeTrue();
        });

        it('handles token without expires_in', function (): void {
            $connector = new class() extends Connector
            {
                use ClientCredentialsGrant;
                use MockableTrait;

                private OAuthConfig $config;

                public function __construct()
                {
                    $this->config = OAuthConfig::make()
                        ->setClientId('test-client')
                        ->setClientSecret('test-secret');
                }

                public function oauthConfig(): OAuthConfig
                {
                    return $this->config;
                }

                public function baseUrl(): string
                {
                    return 'https://oauth.example.com';
                }
            };

            $connector->addResponse(MockResponse::json([
                'access_token' => 'token-without-expiry',
            ]));

            $authenticator = $connector->getAccessToken();

            expect($authenticator->getAccessToken())->toBe('token-without-expiry');
            expect($authenticator->getExpiresAt())->toBeNull();
        });
    });

    describe('AuthorizationCodeGrant with MockableTrait', function (): void {
        it('exchanges authorization code for access token', function (): void {
            $connector = new class() extends Connector
            {
                use AuthorizationCodeGrant;
                use MockableTrait;

                private OAuthConfig $config;

                public function __construct()
                {
                    $this->config = OAuthConfig::make()
                        ->setClientId('test-client')
                        ->setClientSecret('test-secret')
                        ->setRedirectUri('https://example.com/callback');
                }

                public function oauthConfig(): OAuthConfig
                {
                    return $this->config;
                }

                public function baseUrl(): string
                {
                    return 'https://oauth.example.com';
                }
            };

            $connector->addResponse(MockResponse::json([
                'access_token' => 'test-access-token',
                'refresh_token' => 'test-refresh-token',
                'expires_in' => 3_600,
            ]));

            $authenticator = $connector->getAccessToken('auth-code-123');

            expect($authenticator)->toBeInstanceOf(AccessTokenAuthenticator::class);
            expect($authenticator->getAccessToken())->toBe('test-access-token');
            expect($authenticator->getRefreshToken())->toBe('test-refresh-token');
        });

        it('returns response when returnResponse is true', function (): void {
            $connector = new class() extends Connector
            {
                use AuthorizationCodeGrant;
                use MockableTrait;

                private OAuthConfig $config;

                public function __construct()
                {
                    $this->config = OAuthConfig::make()
                        ->setClientId('test-client')
                        ->setClientSecret('test-secret')
                        ->setRedirectUri('https://example.com/callback');
                }

                public function oauthConfig(): OAuthConfig
                {
                    return $this->config;
                }

                public function baseUrl(): string
                {
                    return 'https://oauth.example.com';
                }
            };

            $connector->addResponse(MockResponse::json([
                'access_token' => 'test-access-token',
            ]));

            $response = $connector->getAccessToken('auth-code-123', returnResponse: true);

            expect($response)->toBeInstanceOf(Response::class);
            expect($response->json('access_token'))->toBe('test-access-token');
        });

        it('refreshes access token', function (): void {
            $connector = new class() extends Connector
            {
                use AuthorizationCodeGrant;
                use MockableTrait;

                private OAuthConfig $config;

                public function __construct()
                {
                    $this->config = OAuthConfig::make()
                        ->setClientId('test-client')
                        ->setClientSecret('test-secret')
                        ->setRedirectUri('https://example.com/callback');
                }

                public function oauthConfig(): OAuthConfig
                {
                    return $this->config;
                }

                public function baseUrl(): string
                {
                    return 'https://oauth.example.com';
                }
            };

            $connector->addResponse(MockResponse::json([
                'access_token' => 'new-access-token',
                'refresh_token' => 'new-refresh-token',
                'expires_in' => 3_600,
            ]));

            $authenticator = $connector->refreshAccessToken('old-refresh-token');

            expect($authenticator->getAccessToken())->toBe('new-access-token');
            expect($authenticator->getRefreshToken())->toBe('new-refresh-token');
        });

        it('refreshes using OAuthAuthenticator', function (): void {
            $connector = new class() extends Connector
            {
                use AuthorizationCodeGrant;
                use MockableTrait;

                private OAuthConfig $config;

                public function __construct()
                {
                    $this->config = OAuthConfig::make()
                        ->setClientId('test-client')
                        ->setClientSecret('test-secret')
                        ->setRedirectUri('https://example.com/callback');
                }

                public function oauthConfig(): OAuthConfig
                {
                    return $this->config;
                }

                public function baseUrl(): string
                {
                    return 'https://oauth.example.com';
                }
            };

            $connector->addResponse(MockResponse::json([
                'access_token' => 'new-access-token',
                'expires_in' => 3_600,
            ]));

            $oldAuth = new AccessTokenAuthenticator('old-token', 'old-refresh-token');
            $newAuth = $connector->refreshAccessToken($oldAuth);

            expect($newAuth->getAccessToken())->toBe('new-access-token');
            // Falls back to old refresh token when not in response
            expect($newAuth->getRefreshToken())->toBe('old-refresh-token');
        });

        it('invokes token refreshed callback', function (): void {
            $callbackInvoked = false;
            $receivedNew = null;
            $receivedOld = null;

            $connector = new class($callbackInvoked, $receivedNew, $receivedOld) extends Connector
            {
                use AuthorizationCodeGrant;
                use MockableTrait;

                private OAuthConfig $config;

                public function __construct(
                    private bool &$callbackInvoked,
                    private mixed &$receivedNew,
                    private mixed &$receivedOld,
                ) {
                    $this->config = OAuthConfig::make()
                        ->setClientId('test-client')
                        ->setClientSecret('test-secret')
                        ->setRedirectUri('https://example.com/callback')
                        ->setOnTokenRefreshed(function ($new, $old): void {
                            $this->callbackInvoked = true;
                            $this->receivedNew = $new;
                            $this->receivedOld = $old;
                        });
                }

                public function oauthConfig(): OAuthConfig
                {
                    return $this->config;
                }

                public function baseUrl(): string
                {
                    return 'https://oauth.example.com';
                }
            };

            $connector->addResponse(MockResponse::json([
                'access_token' => 'new-token',
                'expires_in' => 3_600,
            ]));

            $oldAuth = new AccessTokenAuthenticator('old-token', 'refresh-token');
            $connector->refreshAccessToken($oldAuth);

            expect($callbackInvoked)->toBeTrue();
            expect($receivedNew)->toBeInstanceOf(AccessTokenAuthenticator::class);
            expect($receivedOld)->toBe($oldAuth);
        });

        it('gets authenticated user', function (): void {
            $connector = new class() extends Connector
            {
                use AuthorizationCodeGrant;
                use MockableTrait;

                private OAuthConfig $config;

                public function __construct()
                {
                    $this->config = OAuthConfig::make()
                        ->setClientId('test-client')
                        ->setClientSecret('test-secret')
                        ->setRedirectUri('https://example.com/callback');
                }

                public function oauthConfig(): OAuthConfig
                {
                    return $this->config;
                }

                public function baseUrl(): string
                {
                    return 'https://oauth.example.com';
                }
            };

            $connector->addResponse(MockResponse::json([
                'id' => 123,
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ]));

            $auth = new AccessTokenAuthenticator('access-token');
            $response = $connector->getUser($auth);

            expect($response->json('id'))->toBe(123);
            expect($response->json('name'))->toBe('John Doe');
        });

        it('includes code verifier in token request when PKCE is enabled', function (): void {
            $connector = new class() extends Connector
            {
                use AuthorizationCodeGrant;
                use MockableTrait;

                private OAuthConfig $config;

                public function __construct()
                {
                    $this->config = OAuthConfig::make()
                        ->setClientId('test-client')
                        ->setClientSecret('test-secret')
                        ->setRedirectUri('https://example.com/callback')
                        ->setUsePkce(true);
                }

                public function oauthConfig(): OAuthConfig
                {
                    return $this->config;
                }

                public function baseUrl(): string
                {
                    return 'https://oauth.example.com';
                }
            };

            // First generate the authorization URL to create the code verifier
            $connector->getAuthorizationUrl();

            $codeVerifier = $connector->getCodeVerifier();

            $connector->addResponse(MockResponse::json([
                'access_token' => 'test-token',
                'expires_in' => 3_600,
            ]));

            $connector->getAccessToken('auth-code');

            $request = $connector->lastRequest();
            expect($request->body()['code_verifier'])->toBe($codeVerifier);
        });

        it('allows providing custom code verifier in token request', function (): void {
            $connector = new class() extends Connector
            {
                use AuthorizationCodeGrant;
                use MockableTrait;

                private OAuthConfig $config;

                public function __construct()
                {
                    $this->config = OAuthConfig::make()
                        ->setClientId('test-client')
                        ->setClientSecret('test-secret')
                        ->setRedirectUri('https://example.com/callback');
                }

                public function oauthConfig(): OAuthConfig
                {
                    return $this->config;
                }

                public function baseUrl(): string
                {
                    return 'https://oauth.example.com';
                }
            };

            $connector->addResponse(MockResponse::json([
                'access_token' => 'test-token',
                'expires_in' => 3_600,
            ]));

            $customVerifier = 'custom-code-verifier-12345678901234567890123456789012345678';
            $connector->getAccessToken('auth-code', codeVerifier: $customVerifier);

            $request = $connector->lastRequest();
            expect($request->body()['code_verifier'])->toBe($customVerifier);
        });

        test('invokes request modifier callback in getAccessToken', function (): void {
            $modifierCalled = false;

            $connector = new class() extends Connector
            {
                use AuthorizationCodeGrant;
                use MockableTrait;

                private OAuthConfig $config;

                public function __construct()
                {
                    $this->config = OAuthConfig::make()
                        ->setClientId('test-client')
                        ->setClientSecret('test-secret')
                        ->setRedirectUri('https://example.com/callback');
                }

                public function oauthConfig(): OAuthConfig
                {
                    return $this->config;
                }

                public function baseUrl(): string
                {
                    return 'https://oauth.example.com';
                }
            };

            $connector->addResponse(MockResponse::json([
                'access_token' => 'test-token',
                'expires_in' => 3_600,
            ]));

            $connector->getAccessToken('auth-code', requestModifier: function (GetAccessTokenRequest $request) use (&$modifierCalled): void {
                $modifierCalled = true;
            });

            expect($modifierCalled)->toBeTrue();
        });

        test('returns response when returnResponse is true in refreshAccessToken', function (): void {
            $connector = new class() extends Connector
            {
                use AuthorizationCodeGrant;
                use MockableTrait;

                private OAuthConfig $config;

                public function __construct()
                {
                    $this->config = OAuthConfig::make()
                        ->setClientId('test-client')
                        ->setClientSecret('test-secret')
                        ->setRedirectUri('https://example.com/callback');
                }

                public function oauthConfig(): OAuthConfig
                {
                    return $this->config;
                }

                public function baseUrl(): string
                {
                    return 'https://oauth.example.com';
                }
            };

            $connector->addResponse(MockResponse::json([
                'access_token' => 'new-access-token',
                'refresh_token' => 'new-refresh-token',
                'expires_in' => 3_600,
            ]));

            $response = $connector->refreshAccessToken('old-refresh-token', returnResponse: true);

            expect($response)->toBeInstanceOf(Response::class);
            expect($response->json('access_token'))->toBe('new-access-token');
        });

        test('invokes request modifier callback in refreshAccessToken', function (): void {
            $modifierCalled = false;

            $connector = new class() extends Connector
            {
                use AuthorizationCodeGrant;
                use MockableTrait;

                private OAuthConfig $config;

                public function __construct()
                {
                    $this->config = OAuthConfig::make()
                        ->setClientId('test-client')
                        ->setClientSecret('test-secret')
                        ->setRedirectUri('https://example.com/callback');
                }

                public function oauthConfig(): OAuthConfig
                {
                    return $this->config;
                }

                public function baseUrl(): string
                {
                    return 'https://oauth.example.com';
                }
            };

            $connector->addResponse(MockResponse::json([
                'access_token' => 'new-token',
                'expires_in' => 3_600,
            ]));

            $connector->refreshAccessToken('refresh-token', requestModifier: function (GetRefreshTokenRequest $request) use (&$modifierCalled): void {
                $modifierCalled = true;
            });

            expect($modifierCalled)->toBeTrue();
        });

        test('invokes request modifier callback in getUser', function (): void {
            $modifierCalled = false;

            $connector = new class() extends Connector
            {
                use AuthorizationCodeGrant;
                use MockableTrait;

                private OAuthConfig $config;

                public function __construct()
                {
                    $this->config = OAuthConfig::make()
                        ->setClientId('test-client')
                        ->setClientSecret('test-secret')
                        ->setRedirectUri('https://example.com/callback');
                }

                public function oauthConfig(): OAuthConfig
                {
                    return $this->config;
                }

                public function baseUrl(): string
                {
                    return 'https://oauth.example.com';
                }
            };

            $connector->addResponse(MockResponse::json([
                'id' => 123,
                'name' => 'John Doe',
            ]));

            $auth = new AccessTokenAuthenticator('access-token');
            $connector->getUser($auth, requestModifier: function (GetUserRequest $request) use (&$modifierCalled): void {
                $modifierCalled = true;
            });

            expect($modifierCalled)->toBeTrue();
        });
    });

    describe('GetAccessTokenRequest', function (): void {
        it('returns correct token endpoint', function (): void {
            $config = OAuthConfig::make()
                ->setClientId('test-client')
                ->setClientSecret('test-secret')
                ->setRedirectUri('https://example.com/callback')
                ->setTokenEndpoint('oauth/token');

            $request = new GetAccessTokenRequest('auth-code-123', $config);

            expect($request->endpoint())->toBe('oauth/token');
        });

        it('includes required OAuth2 parameters in body', function (): void {
            $config = OAuthConfig::make()
                ->setClientId('test-client')
                ->setClientSecret('test-secret')
                ->setRedirectUri('https://example.com/callback');

            $request = new GetAccessTokenRequest('auth-code-123', $config);

            $body = $request->body();

            expect($body)->toHaveKey('grant_type', 'authorization_code');
            expect($body)->toHaveKey('code', 'auth-code-123');
            expect($body)->toHaveKey('client_id', 'test-client');
            expect($body)->toHaveKey('client_secret', 'test-secret');
            expect($body)->toHaveKey('redirect_uri', 'https://example.com/callback');
        });

        it('includes code verifier when provided', function (): void {
            $config = OAuthConfig::make()
                ->setClientId('test-client')
                ->setClientSecret('test-secret')
                ->setRedirectUri('https://example.com/callback');

            $request = new GetAccessTokenRequest(
                'auth-code-123',
                $config,
                'code-verifier-12345678901234567890123456789012345678',
            );

            $body = $request->body();

            expect($body)->toHaveKey('code_verifier', 'code-verifier-12345678901234567890123456789012345678');
        });

        it('excludes code verifier when not provided', function (): void {
            $config = OAuthConfig::make()
                ->setClientId('test-client')
                ->setClientSecret('test-secret')
                ->setRedirectUri('https://example.com/callback');

            $request = new GetAccessTokenRequest('auth-code-123', $config);

            $body = $request->body();

            expect($body)->not->toHaveKey('code_verifier');
        });
    });

    describe('GetClientCredentialsTokenRequest', function (): void {
        it('returns correct token endpoint', function (): void {
            $config = OAuthConfig::make()
                ->setClientId('test-client')
                ->setClientSecret('test-secret')
                ->setTokenEndpoint('oauth/token');

            $request = new GetClientCredentialsTokenRequest($config);

            expect($request->endpoint())->toBe('oauth/token');
        });

        it('includes required client credentials parameters in body', function (): void {
            $config = OAuthConfig::make()
                ->setClientId('test-client')
                ->setClientSecret('test-secret');

            $request = new GetClientCredentialsTokenRequest($config);

            $body = $request->body();

            expect($body)->toHaveKey('grant_type', 'client_credentials');
            expect($body)->toHaveKey('client_id', 'test-client');
            expect($body)->toHaveKey('client_secret', 'test-secret');
        });

        it('includes scopes when provided', function (): void {
            $config = OAuthConfig::make()
                ->setClientId('test-client')
                ->setClientSecret('test-secret');

            $request = new GetClientCredentialsTokenRequest($config, ['read', 'write']);

            $body = $request->body();

            expect($body)->toHaveKey('scope', 'read write');
        });

        it('excludes scope when no scopes provided', function (): void {
            $config = OAuthConfig::make()
                ->setClientId('test-client')
                ->setClientSecret('test-secret');

            $request = new GetClientCredentialsTokenRequest($config);

            $body = $request->body();

            expect($body)->not->toHaveKey('scope');
        });
    });

    describe('GetRefreshTokenRequest', function (): void {
        it('returns correct token endpoint', function (): void {
            $config = OAuthConfig::make()
                ->setClientId('test-client')
                ->setClientSecret('test-secret')
                ->setTokenEndpoint('oauth/token');

            $request = new GetRefreshTokenRequest($config, 'refresh-token-123');

            expect($request->endpoint())->toBe('oauth/token');
        });

        it('includes required refresh token parameters in body', function (): void {
            $config = OAuthConfig::make()
                ->setClientId('test-client')
                ->setClientSecret('test-secret');

            $request = new GetRefreshTokenRequest($config, 'refresh-token-123');

            $body = $request->body();

            expect($body)->toHaveKey('grant_type', 'refresh_token');
            expect($body)->toHaveKey('refresh_token', 'refresh-token-123');
            expect($body)->toHaveKey('client_id', 'test-client');
            expect($body)->toHaveKey('client_secret', 'test-secret');
        });
    });

    describe('GetUserRequest', function (): void {
        it('returns correct user endpoint', function (): void {
            $config = OAuthConfig::make()
                ->setClientId('test-client')
                ->setClientSecret('test-secret')
                ->setUserEndpoint('api/me');

            $request = new GetUserRequest($config);

            expect($request->endpoint())->toBe('api/me');
        });

        it('uses default user endpoint when not specified', function (): void {
            $config = OAuthConfig::make()
                ->setClientId('test-client')
                ->setClientSecret('test-secret');

            $request = new GetUserRequest($config);

            expect($request->endpoint())->toBe('user');
        });
    });
});

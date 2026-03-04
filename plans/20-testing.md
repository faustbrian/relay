# Testing

## Mock Responses

```php
// Mock responses
$connector = ApiConnector::fake([
    GetUser::class => Response::make(['id' => 1, 'name' => 'John']),
    CreateUser::class => Response::make(['id' => 2], 201),
    '*' => Response::make([], 404), // Catch-all
]);

// Sequence
$connector = ApiConnector::fake([
    GetUsers::class => Response::sequence([
        Response::make(['page' => 1]),
        Response::make(['page' => 2]),
        Response::make([]), // Empty = end
    ]),
]);
```

## Assertions

```php
$connector->assertSent(CreateUser::class);
$connector->assertSent(fn(Request $r) => $r->body()['name'] === 'John');
$connector->assertNotSent(DeleteUser::class);
$connector->assertSentCount(3);
```

## Record & Replay

For integration tests:

```php
$connector = ApiConnector::record('./fixtures');
// ... run tests ...
$connector = ApiConnector::replay('./fixtures');

// Choose storage format for recorder (default: json)
$connector = ApiConnector::record('./fixtures', format: 'json');
$connector = ApiConnector::record('./fixtures', format: 'xml');
```

## Testing OAuth2 Flows

### Fake Token Responses

```php
$connector = OAuthConnector::fake([
    // Mock authorization code exchange
    ExchangeAuthorizationCode::class => Response::make([
        'access_token' => 'fake_access_token',
        'refresh_token' => 'fake_refresh_token',
        'expires_in' => 3600,
        'token_type' => 'Bearer',
    ]),

    // Mock token refresh
    RefreshAccessToken::class => Response::make([
        'access_token' => 'new_access_token',
        'refresh_token' => 'new_refresh_token',
        'expires_in' => 3600,
        'token_type' => 'Bearer',
    ]),
]);
```

### Testing Token Expiry

```php
it('refreshes expired token automatically', function () {
    $connector = ApiConnector::fake([
        // First request fails with 401
        GetUser::class => Response::sequence([
            Response::make(['error' => 'Token expired'], 401),
            Response::make(['id' => 1, 'name' => 'John']), // After refresh
        ]),

        // Token refresh succeeds
        RefreshAccessToken::class => Response::make([
            'access_token' => 'new_token',
            'expires_in' => 3600,
        ]),
    ]);

    $response = $connector->send(new GetUser(1));

    expect($response->json('name'))->toBe('John');
    $connector->assertSent(RefreshAccessToken::class);
});
```

### Testing Authorization URL

```php
it('generates correct authorization url', function () {
    $connector = new OAuthConnector();

    $url = $connector->getAuthorizationUrl(
        redirectUri: 'https://app.test/callback',
        scopes: ['read', 'write'],
        state: 'random_state',
    );

    expect($url)
        ->toContain('client_id=')
        ->toContain('redirect_uri=https%3A%2F%2Fapp.test%2Fcallback')
        ->toContain('scope=read+write')
        ->toContain('state=random_state');
});
```

### Testing PKCE Flow

```php
it('generates valid pkce challenge', function () {
    $connector = new OAuthConnector();
    $pkce = $connector->generatePkce();

    expect($pkce)
        ->toHaveKey('code_verifier')
        ->toHaveKey('code_challenge')
        ->toHaveKey('code_challenge_method');

    expect($pkce['code_challenge_method'])->toBe('S256');
    expect(strlen($pkce['code_verifier']))->toBeGreaterThanOrEqual(43);
});

it('exchanges code with pkce verifier', function () {
    $connector = OAuthConnector::fake([
        ExchangeAuthorizationCode::class => Response::make([
            'access_token' => 'token',
        ]),
    ]);

    $connector->exchangeCode(
        code: 'auth_code',
        codeVerifier: 'stored_verifier',
    );

    $connector->assertSent(function (Request $request) {
        return $request->body()['code_verifier'] === 'stored_verifier';
    });
});
```

### Mock OAuth Provider

```php
class FakeOAuthProvider
{
    public function __construct(
        public string $accessToken = 'fake_token',
        public string $refreshToken = 'fake_refresh',
        public int $expiresIn = 3600,
    ) {}

    public function tokenResponse(): Response
    {
        return Response::make([
            'access_token' => $this->accessToken,
            'refresh_token' => $this->refreshToken,
            'expires_in' => $this->expiresIn,
            'token_type' => 'Bearer',
        ]);
    }

    public function expiredTokenResponse(): Response
    {
        return Response::make([
            'error' => 'invalid_token',
            'error_description' => 'Token has expired',
        ], 401);
    }
}

// Usage in tests
$provider = new FakeOAuthProvider(accessToken: 'test_token');
$connector = OAuthConnector::fake([
    ExchangeAuthorizationCode::class => $provider->tokenResponse(),
]);
```

### Testing Token Storage

```php
it('stores tokens after successful exchange', function () {
    $store = new ArrayTokenStore();

    $connector = new OAuthConnector(tokenStore: $store);
    $connector = $connector->fake([
        ExchangeAuthorizationCode::class => Response::make([
            'access_token' => 'new_token',
            'refresh_token' => 'new_refresh',
            'expires_in' => 3600,
        ]),
    ]);

    $connector->exchangeCode('auth_code');

    expect($store->getAccessToken())->toBe('new_token');
    expect($store->getRefreshToken())->toBe('new_refresh');
});
```

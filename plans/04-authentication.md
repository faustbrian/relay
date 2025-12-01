# Authentication

## Authentication Strategies

Built-in authenticators (no traits):

```php
// Token auth
$connector = new ApiConnector(
    auth: new BearerToken($token),
);

// Basic auth
$connector = new ApiConnector(
    auth: new BasicAuth($username, $password),
);

// Query param auth
$connector = new ApiConnector(
    auth: new QueryAuth('api_key', $key),
);

// Header auth
$connector = new ApiConnector(
    auth: new HeaderAuth('X-API-Key', $key),
);

// Custom
$connector = new ApiConnector(
    auth: new CallableAuth(fn(Request $r) => $r->withHeader('X-Custom', sign($r))),
);
```

## OAuth2

Full OAuth2 flow support for authorization code, client credentials, and refresh token grants.

### Authorization Code Flow

```php
class SpotifyConnector extends Connector
{
    use OAuth2Connector;

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {}

    public function baseUrl(): string
    {
        return 'https://api.spotify.com/v1';
    }

    // OAuth2 configuration
    public function oauthConfig(): OAuth2Config
    {
        return new OAuth2Config(
            clientId: $this->clientId,
            clientSecret: $this->clientSecret,
            authorizeUrl: 'https://accounts.spotify.com/authorize',
            tokenUrl: 'https://accounts.spotify.com/api/token',
            redirectUri: 'https://myapp.com/callback',
            scopes: ['user-read-email', 'playlist-read-private'],
        );
    }
}

// Step 1: Generate authorization URL
$connector = new SpotifyConnector($clientId, $clientSecret);

$authUrl = $connector->oauth()->authorizationUrl(
    scopes: ['user-read-email', 'playlist-read-private'],
    state: $csrfToken, // Optional, auto-generated if not provided
);
// Redirect user to $authUrl

// Step 2: Handle callback and exchange code for tokens
$tokens = $connector->oauth()->exchangeCode(
    code: $_GET['code'],
    state: $_GET['state'], // Validates against expected state
);

$tokens->accessToken();   // string
$tokens->refreshToken();  // ?string
$tokens->expiresAt();     // ?Carbon
$tokens->scopes();        // array

// Step 3: Use the connector with tokens
$connector->oauth()->setTokens($tokens);
$response = $connector->send(new GetCurrentUser());
```

### PKCE Support

```php
// Generate PKCE challenge
$pkce = $connector->oauth()->generatePkce();

$authUrl = $connector->oauth()->authorizationUrl(
    scopes: ['read', 'write'],
    codeChallenge: $pkce->challenge,
    codeChallengeMethod: 'S256',
);

// Store $pkce->verifier in session

// On callback
$tokens = $connector->oauth()->exchangeCode(
    code: $_GET['code'],
    codeVerifier: session('pkce_verifier'),
);
```

### Client Credentials Flow

```php
// For server-to-server auth (no user involved)
$tokens = $connector->oauth()->clientCredentials(
    scopes: ['read:users', 'write:orders'],
);

$connector->oauth()->setTokens($tokens);
```

### Automatic Token Refresh

```php
class SpotifyConnector extends Connector
{
    use OAuth2Connector;

    public function oauthConfig(): OAuth2Config
    {
        return new OAuth2Config(
            // ... config
            autoRefresh: true, // Automatically refresh expired tokens
            refreshBuffer: 300, // Refresh 5 minutes before expiry
            onTokenRefresh: function (OAuth2Tokens $newTokens) {
                // Persist new tokens
                $this->tokenStore->save($newTokens);
            },
        );
    }
}

// Tokens refresh automatically when needed
$connector->oauth()->setTokens($storedTokens);
$response = $connector->send($request); // Auto-refreshes if expired
```

### Token Refresh on 401 Response

Intercept 401 responses and automatically refresh + retry:

```php
class SpotifyConnector extends Connector
{
    use OAuth2Connector;

    public function oauthConfig(): OAuth2Config
    {
        return new OAuth2Config(
            // ... config

            // Refresh on 401 response (token rejected by server)
            refreshOn401: true,

            // Max retry attempts after refresh
            maxRefreshRetries: 1,

            // Custom condition for when to refresh
            shouldRefresh: function (Response $response): bool {
                // Some APIs return 403 or custom error codes
                if ($response->status() === 401) {
                    return true;
                }

                // Check for specific error in body
                $error = $response->json('error.code');
                return $error === 'token_expired';
            },
        );
    }
}
```

### Manual Token Refresh Handling

```php
class ApiConnector extends Connector
{
    use OAuth2Connector;

    public function send(Request $request): Response
    {
        $response = parent::send($request);

        // Check if token was rejected
        if ($response->status() === 401 && $this->canRefreshToken()) {
            // Refresh the token
            $newTokens = $this->oauth()->refreshToken();

            // Retry the request with new token
            return parent::send($request->clone());
        }

        return $response;
    }

    private function canRefreshToken(): bool
    {
        return $this->oauth()->hasRefreshToken()
            && !$this->oauth()->isRefreshing(); // Prevent loops
    }
}
```

### Concurrent Request Token Refresh

Handle token refresh when multiple requests are in-flight:

```php
class SpotifyConnector extends Connector
{
    use OAuth2Connector;

    public function oauthConfig(): OAuth2Config
    {
        return new OAuth2Config(
            // ... config

            // Queue requests while refreshing (prevents multiple refresh calls)
            queueDuringRefresh: true,

            // Or: fail fast during refresh
            // failDuringRefresh: true,
        );
    }
}
```

### Token Storage Integration

```php
// Laravel integration
class SpotifyConnector extends Connector
{
    use OAuth2Connector;

    public function __construct(
        private readonly User $user,
    ) {}

    protected function resolveTokens(): ?OAuth2Tokens
    {
        return $this->user->spotifyTokens; // Load from DB
    }

    protected function storeTokens(OAuth2Tokens $tokens): void
    {
        $this->user->update(['spotify_tokens' => $tokens]);
    }
}

// Tokens auto-load and auto-persist
$connector = new SpotifyConnector($user);
$response = $connector->send(new GetPlaylists()); // Just works
```

### Custom OAuth2 Flows

```php
// For non-standard OAuth implementations
class WeirdOAuthConnector extends Connector
{
    use OAuth2Connector;

    public function oauthConfig(): OAuth2Config
    {
        return new OAuth2Config(
            clientId: $this->clientId,
            clientSecret: $this->clientSecret,
            authorizeUrl: 'https://weird.api/oauth/auth',
            tokenUrl: 'https://weird.api/oauth/token',

            // Custom parameters
            authorizationParams: [
                'access_type' => 'offline',
                'prompt' => 'consent',
            ],
            tokenParams: [
                'audience' => 'https://api.weird.com',
            ],

            // Custom token extraction (for non-standard responses)
            tokenExtractor: function (array $response): OAuth2Tokens {
                return new OAuth2Tokens(
                    accessToken: $response['data']['access_token'],
                    refreshToken: $response['data']['refresh_token'] ?? null,
                    expiresIn: $response['data']['ttl'],
                );
            },
        );
    }
}
```

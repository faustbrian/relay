# Authentication

Relay provides multiple authentication strategies for different API requirements. All authenticators implement the `Authenticator` contract and can be used in connector's `authenticate()` method.

## Using Authentication

Implement the `authenticate()` method in your connector:

```php
use Cline\Relay\Features\Auth\BearerToken;
use Cline\Relay\Core\Connector;
use Cline\Relay\Core\Request;

class GitHubConnector extends Connector
{
    public function __construct(
        private readonly string $token,
    ) {}

    public function baseUrl(): string
    {
        return 'https://api.github.com';
    }

    public function authenticate(Request $request): Request
    {
        return (new BearerToken($this->token))->authenticate($request);
    }
}
```

## Bearer Token

The most common authentication method for modern APIs:

```php
use Cline\Relay\Features\Auth\BearerToken;

public function authenticate(Request $request): Request
{
    return (new BearerToken($this->token))->authenticate($request);
}

// Adds header: Authorization: Bearer <token>
```

## Basic Authentication

HTTP Basic Authentication with username and password:

```php
use Cline\Relay\Features\Auth\BasicAuth;

public function authenticate(Request $request): Request
{
    return (new BasicAuth(
        username: $this->username,
        password: $this->password,
    ))->authenticate($request);
}

// Adds header: Authorization: Basic base64(username:password)
```

## API Key Authentication

API key in header or query parameter:

### In Header (default)

```php
use Cline\Relay\Features\Auth\ApiKeyAuth;

public function authenticate(Request $request): Request
{
    return ApiKeyAuth::inHeader(
        key: $this->apiKey,
        headerName: 'X-API-Key', // default
    )->authenticate($request);
}

// Adds header: X-API-Key: <your-api-key>
```

### In Query Parameter

```php
public function authenticate(Request $request): Request
{
    return ApiKeyAuth::inQuery(
        key: $this->apiKey,
        paramName: 'api_key', // default
    )->authenticate($request);
}

// Adds query parameter: ?api_key=<your-api-key>
```

### Custom Placement

```php
$auth = new ApiKeyAuth(
    key: $this->apiKey,
    name: 'Authorization',
    in: ApiKeyAuth::IN_HEADER,
);

// Or
$auth = new ApiKeyAuth(
    key: $this->apiKey,
    name: 'key',
    in: ApiKeyAuth::IN_QUERY,
);
```

## Header Authentication

Custom header-based authentication:

```php
use Cline\Relay\Features\Auth\HeaderAuth;

public function authenticate(Request $request): Request
{
    return (new HeaderAuth(
        header: 'X-Auth-Token',
        value: $this->token,
    ))->authenticate($request);
}

// Adds header: X-Auth-Token: <token>
```

## Query Authentication

Authentication via query parameter:

```php
use Cline\Relay\Features\Auth\QueryAuth;

public function authenticate(Request $request): Request
{
    return (new QueryAuth(
        parameter: 'access_token',
        value: $this->token,
    ))->authenticate($request);
}

// Adds query parameter: ?access_token=<token>
```

## JWT Authentication

JSON Web Token authentication with expiry tracking:

### Static Token

```php
use Cline\Relay\Features\Auth\JwtAuth;

public function authenticate(Request $request): Request
{
    return JwtAuth::token(
        token: $this->jwtToken,
        expiresAt: $this->tokenExpiresAt, // optional DateTimeImmutable
    )->authenticate($request);
}
```

### Dynamic Token Provider

For automatic token refresh:

```php
public function authenticate(Request $request): Request
{
    return JwtAuth::withProvider(function () {
        // Fetch fresh token if expired
        if ($this->isTokenExpired()) {
            $this->refreshToken();
        }
        return $this->currentToken;
    })->authenticate($request);
}
```

### Token Expiry Checking

```php
$jwt = JwtAuth::token($token, $expiresAt);

if ($jwt->hasExpired()) {
    // Token has expired
}

if ($jwt->isValid()) {
    // Token is still valid
}

// Get expiry time
$expiresAt = $jwt->getExpiresAt();

// Update token
$jwt->setToken($newToken, $newExpiresAt);
```

## Digest Authentication

HTTP Digest Authentication (requires Guzzle-level configuration):

```php
use Cline\Relay\Features\Auth\DigestAuth;

class MyConnector extends Connector
{
    private DigestAuth $digestAuth;

    public function __construct(string $username, string $password)
    {
        $this->digestAuth = new DigestAuth($username, $password);
    }

    // Digest auth requires Guzzle config, not request modification
    public function authenticate(Request $request): Request
    {
        return $request; // No-op for digest
    }

    public function defaultConfig(): array
    {
        return [
            'auth' => $this->digestAuth->toGuzzleAuth(),
        ];
    }
}
```

The `toGuzzleAuth()` method returns the array format Guzzle expects:

```php
$auth->toGuzzleAuth(); // ['username', 'password', 'digest']
```

## Callable Authentication

For custom authentication logic:

```php
use Cline\Relay\Features\Auth\CallableAuth;

public function authenticate(Request $request): Request
{
    return (new CallableAuth(function (Request $request) {
        // Custom authentication logic
        $signature = $this->generateSignature($request);

        return $request
            ->withHeader('X-Signature', $signature)
            ->withHeader('X-Timestamp', (string) time());
    }))->authenticate($request);
}
```

## Access Token Authenticator

The `AccessTokenAuthenticator` is an immutable OAuth authenticator for storing and applying access tokens:

```php
use Cline\Relay\Features\Auth\AccessTokenAuthenticator;
use DateTimeImmutable;

// Create with all token details
$auth = new AccessTokenAuthenticator(
    accessToken: 'your-access-token',
    refreshToken: 'your-refresh-token', // optional
    expiresAt: new DateTimeImmutable('+1 hour'), // optional
);

// Apply to requests
public function authenticate(Request $request): Request
{
    return $this->auth->authenticate($request);
}
```

### Token Information

```php
// Get tokens
$accessToken = $auth->getAccessToken();
$refreshToken = $auth->getRefreshToken();

// Get expiry
$expiresAt = $auth->getExpiresAt();

// Check expiry status
if ($auth->hasExpired()) {
    // Token has expired
}

if ($auth->hasNotExpired()) {
    // Token is still valid
}

// Check if refreshable
if ($auth->isRefreshable()) {
    // Has a refresh token
}

if ($auth->isNotRefreshable()) {
    // No refresh token available
}
```

### Serialization

For persisting tokens (e.g., to cache or database):

```php
// Serialize for storage
$serialized = $auth->serialize();
cache()->put('oauth_token', $serialized, 3600);

// Restore from storage
$serialized = cache()->get('oauth_token');
$auth = AccessTokenAuthenticator::unserialize($serialized);
```

## Auto-Refresh Authenticator

The `AutoRefreshAuthenticator` wraps an `OAuthAuthenticator` and automatically refreshes expired tokens:

```php
use Cline\Relay\Features\Auth\AutoRefreshAuthenticator;
use Cline\Relay\Features\OAuth2\AuthorizationCodeGrant;

class MyConnector extends Connector
{
    use AuthorizationCodeGrant;

    private AutoRefreshAuthenticator $auth;

    public function setAuthenticator(OAuthAuthenticator $authenticator): void
    {
        $this->auth = new AutoRefreshAuthenticator(
            connector: $this,
            authenticator: $authenticator,
            onRefresh: fn (OAuthAuthenticator $new) => $this->saveToken($new),
        );
    }

    public function authenticate(Request $request): Request
    {
        // Automatically refreshes if expired
        return $this->auth->authenticate($request);
    }

    private function saveToken(OAuthAuthenticator $auth): void
    {
        cache()->put('oauth_token', $auth->serialize(), 3600);
    }
}
```

### Manual Refresh

```php
// Check status
if ($auth->hasExpired()) {
    // Token needs refresh
}

if ($auth->isRefreshable()) {
    // Can be refreshed
}

// Manually trigger refresh
$auth->refresh();

// Get current tokens
$accessToken = $auth->getAccessToken();
$refreshToken = $auth->getRefreshToken();

// Get wrapped authenticator
$inner = $auth->getAuthenticator();
```

## Request-Level Authentication

Add authentication to specific requests:

```php
// Using request methods
$request = (new GetUserRequest(1))
    ->withBearerToken('token')
    ->withBasicAuth('user', 'pass');

// Override connector auth
$response = $connector->send(
    (new GetUserRequest(1))->withHeader('Authorization', 'Custom xyz')
);
```

## Multiple Authentication Methods

Combine multiple authentication methods:

```php
public function authenticate(Request $request): Request
{
    // Apply multiple auth methods
    $request = (new BearerToken($this->token))->authenticate($request);
    $request = (new HeaderAuth('X-Api-Key', $this->apiKey))->authenticate($request);

    return $request;
}
```

## Custom Authenticator

Create a custom authenticator by implementing the `Authenticator` contract:

```php
<?php

namespace App\Auth;

use Cline\Relay\Support\Contracts\Authenticator;
use Cline\Relay\Core\Request;

class HmacAuth implements Authenticator
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $secretKey,
    ) {}

    public function authenticate(Request $request): Request
    {
        $timestamp = time();
        $signature = $this->generateSignature($request, $timestamp);

        return $request
            ->withHeader('X-Api-Key', $this->apiKey)
            ->withHeader('X-Timestamp', (string) $timestamp)
            ->withHeader('X-Signature', $signature);
    }

    private function generateSignature(Request $request, int $timestamp): string
    {
        $payload = implode("\n", [
            $request->method(),
            $request->endpoint(),
            $timestamp,
        ]);

        return hash_hmac('sha256', $payload, $this->secretKey);
    }
}
```

Usage:

```php
public function authenticate(Request $request): Request
{
    return (new HmacAuth($this->apiKey, $this->secretKey))
        ->authenticate($request);
}
```

## Full Example

Complete connector with OAuth2 and API key authentication:

```php
<?php

namespace App\Http\Connectors;

use Cline\Relay\Features\Auth\ApiKeyAuth;
use Cline\Relay\Features\Auth\AutoRefreshAuthenticator;
use Cline\Relay\Core\Connector;
use Cline\Relay\Support\Contracts\OAuthAuthenticator;
use Cline\Relay\Features\OAuth2\ClientCredentialsGrant;
use Cline\Relay\Features\OAuth2\OAuthConfig;
use Cline\Relay\Core\Request;

class MultiAuthConnector extends Connector
{
    use ClientCredentialsGrant;

    private ?AutoRefreshAuthenticator $auth = null;

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $apiKey,
    ) {}

    public function baseUrl(): string
    {
        return 'https://api.example.com/v1';
    }

    public function oauthConfig(): OAuthConfig
    {
        return new OAuthConfig(
            clientId: $this->clientId,
            clientSecret: $this->clientSecret,
            tokenEndpoint: '/oauth/token',
        );
    }

    public function authenticate(Request $request): Request
    {
        // Initialize OAuth token on first request
        if ($this->auth === null) {
            $authenticator = $this->getAccessToken();
            $this->auth = new AutoRefreshAuthenticator(
                connector: $this,
                authenticator: $authenticator,
                onRefresh: fn (OAuthAuthenticator $new) => $this->saveToken($new),
            );
        }

        // Apply OAuth token (auto-refreshes if expired)
        $request = $this->auth->authenticate($request);

        // Also apply API key in header
        $request = ApiKeyAuth::inHeader($this->apiKey)->authenticate($request);

        return $request;
    }

    private function saveToken(OAuthAuthenticator $auth): void
    {
        cache()->put('oauth_token', $auth->serialize(), 3600);
    }
}
```

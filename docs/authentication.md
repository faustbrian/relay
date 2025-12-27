---
title: Authentication
description: Configure authentication strategies including Bearer tokens, API keys, OAuth2, and custom authenticators
---

Relay provides multiple authentication strategies for different API requirements. All authenticators implement the `Authenticator` contract.

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

```php
use Cline\Relay\Features\Auth\BearerToken;

public function authenticate(Request $request): Request
{
    return (new BearerToken($this->token))->authenticate($request);
}

// Adds: Authorization: Bearer <token>
```

## Basic Authentication

```php
use Cline\Relay\Features\Auth\BasicAuth;

public function authenticate(Request $request): Request
{
    return (new BasicAuth(
        username: $this->username,
        password: $this->password,
    ))->authenticate($request);
}

// Adds: Authorization: Basic base64(username:password)
```

## API Key Authentication

### In Header

```php
use Cline\Relay\Features\Auth\ApiKeyAuth;

public function authenticate(Request $request): Request
{
    return ApiKeyAuth::inHeader(
        key: $this->apiKey,
        headerName: 'X-API-Key',
    )->authenticate($request);
}

// Adds: X-API-Key: <your-api-key>
```

### In Query Parameter

```php
public function authenticate(Request $request): Request
{
    return ApiKeyAuth::inQuery(
        key: $this->apiKey,
        paramName: 'api_key',
    )->authenticate($request);
}

// Adds: ?api_key=<your-api-key>
```

## Header Authentication

```php
use Cline\Relay\Features\Auth\HeaderAuth;

public function authenticate(Request $request): Request
{
    return (new HeaderAuth(
        header: 'X-Auth-Token',
        value: $this->token,
    ))->authenticate($request);
}
```

## Query Authentication

```php
use Cline\Relay\Features\Auth\QueryAuth;

public function authenticate(Request $request): Request
{
    return (new QueryAuth(
        parameter: 'access_token',
        value: $this->token,
    ))->authenticate($request);
}
```

## JWT Authentication

### Static Token

```php
use Cline\Relay\Features\Auth\JwtAuth;

public function authenticate(Request $request): Request
{
    return JwtAuth::token(
        token: $this->jwtToken,
        expiresAt: $this->tokenExpiresAt,
    )->authenticate($request);
}
```

### Dynamic Token Provider

```php
public function authenticate(Request $request): Request
{
    return JwtAuth::withProvider(function () {
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

$expiresAt = $jwt->getExpiresAt();
$jwt->setToken($newToken, $newExpiresAt);
```

## Digest Authentication

```php
use Cline\Relay\Features\Auth\DigestAuth;

class MyConnector extends Connector
{
    private DigestAuth $digestAuth;

    public function __construct(string $username, string $password)
    {
        $this->digestAuth = new DigestAuth($username, $password);
    }

    public function defaultConfig(): array
    {
        return [
            'auth' => $this->digestAuth->toGuzzleAuth(),
        ];
    }
}
```

## Callable Authentication

```php
use Cline\Relay\Features\Auth\CallableAuth;

public function authenticate(Request $request): Request
{
    return (new CallableAuth(function (Request $request) {
        $signature = $this->generateSignature($request);

        return $request
            ->withHeader('X-Signature', $signature)
            ->withHeader('X-Timestamp', (string) time());
    }))->authenticate($request);
}
```

## Access Token Authenticator

```php
use Cline\Relay\Features\Auth\AccessTokenAuthenticator;
use DateTimeImmutable;

$auth = new AccessTokenAuthenticator(
    accessToken: 'your-access-token',
    refreshToken: 'your-refresh-token',
    expiresAt: new DateTimeImmutable('+1 hour'),
);

public function authenticate(Request $request): Request
{
    return $this->auth->authenticate($request);
}
```

### Token Information

```php
$accessToken = $auth->getAccessToken();
$refreshToken = $auth->getRefreshToken();
$expiresAt = $auth->getExpiresAt();

if ($auth->hasExpired()) { /* Token expired */ }
if ($auth->hasNotExpired()) { /* Token valid */ }
if ($auth->isRefreshable()) { /* Has refresh token */ }
```

### Serialization

```php
$serialized = $auth->serialize();
cache()->put('oauth_token', $serialized, 3600);

$serialized = cache()->get('oauth_token');
$auth = AccessTokenAuthenticator::unserialize($serialized);
```

## Auto-Refresh Authenticator

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
        return $this->auth->authenticate($request);
    }
}
```

## Request-Level Authentication

```php
$request = (new GetUserRequest(1))
    ->withBearerToken('token')
    ->withBasicAuth('user', 'pass');

$response = $connector->send(
    (new GetUserRequest(1))->withHeader('Authorization', 'Custom xyz')
);
```

## Multiple Authentication Methods

```php
public function authenticate(Request $request): Request
{
    $request = (new BearerToken($this->token))->authenticate($request);
    $request = (new HeaderAuth('X-Api-Key', $this->apiKey))->authenticate($request);

    return $request;
}
```

## Custom Authenticator

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

## Full Example

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
        if ($this->auth === null) {
            $authenticator = $this->getAccessToken();
            $this->auth = new AutoRefreshAuthenticator(
                connector: $this,
                authenticator: $authenticator,
                onRefresh: fn (OAuthAuthenticator $new) => $this->saveToken($new),
            );
        }

        $request = $this->auth->authenticate($request);
        $request = ApiKeyAuth::inHeader($this->apiKey)->authenticate($request);

        return $request;
    }

    private function saveToken(OAuthAuthenticator $auth): void
    {
        cache()->put('oauth_token', $auth->serialize(), 3600);
    }
}
```

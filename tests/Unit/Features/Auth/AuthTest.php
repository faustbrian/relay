<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Carbon\CarbonImmutable;
use Cline\Relay\Core\Request;
use Cline\Relay\Features\Auth\ApiKeyAuth;
use Cline\Relay\Features\Auth\BasicAuth;
use Cline\Relay\Features\Auth\BearerToken;
use Cline\Relay\Features\Auth\CallableAuth;
use Cline\Relay\Features\Auth\DigestAuth;
use Cline\Relay\Features\Auth\HeaderAuth;
use Cline\Relay\Features\Auth\JwtAuth;
use Cline\Relay\Features\Auth\QueryAuth;
use Cline\Relay\Support\Attributes\Methods\Get;

function createAuthTestRequest(): Request
{
    return new #[Get()] class() extends Request
    {
        public function endpoint(): string
        {
            return '/test';
        }
    };
}

describe('BearerToken', function (): void {
    describe('Happy Paths', function (): void {
        it('adds Authorization header with Bearer token', function (): void {
            $auth = new BearerToken('my-secret-token');
            $request = createAuthTestRequest();

            $authenticated = $auth->authenticate($request);

            expect($authenticated->allHeaders())
                ->toHaveKey('Authorization')
                ->and($authenticated->allHeaders()['Authorization'])->toBe('Bearer my-secret-token');
        });

        it('preserves existing headers when adding Bearer token', function (): void {
            $auth = new BearerToken('test-token');
            $request = createAuthTestRequest()->withHeader('X-Custom', 'value');

            $authenticated = $auth->authenticate($request);

            expect($authenticated->allHeaders())
                ->toHaveKey('Authorization')
                ->toHaveKey('X-Custom')
                ->and($authenticated->allHeaders()['X-Custom'])->toBe('value');
        });

        it('creates immutable request with Bearer token', function (): void {
            $auth = new BearerToken('token-123');
            $original = createAuthTestRequest();

            $authenticated = $auth->authenticate($original);

            expect($original->allHeaders())->not->toHaveKey('Authorization')
                ->and($authenticated->allHeaders())->toHaveKey('Authorization');
        });
    });

    describe('Edge Cases', function (): void {
        it('handles empty token string', function (): void {
            $auth = new BearerToken('');
            $request = createAuthTestRequest();

            $authenticated = $auth->authenticate($request);

            expect($authenticated->allHeaders()['Authorization'])->toBe('Bearer ');
        });

        it('handles token with special characters', function (): void {
            $auth = new BearerToken('token-with-special_chars.123!@#');
            $request = createAuthTestRequest();

            $authenticated = $auth->authenticate($request);

            expect($authenticated->allHeaders()['Authorization'])
                ->toBe('Bearer token-with-special_chars.123!@#');
        });

        it('handles very long token', function (): void {
            $longToken = str_repeat('a', 1_000);
            $auth = new BearerToken($longToken);
            $request = createAuthTestRequest();

            $authenticated = $auth->authenticate($request);

            expect($authenticated->allHeaders()['Authorization'])
                ->toBe('Bearer '.$longToken);
        });

        it('overwrites existing Authorization header', function (): void {
            $auth = new BearerToken('new-token');
            $request = createAuthTestRequest()->withHeader('Authorization', 'Basic old-auth');

            $authenticated = $auth->authenticate($request);

            expect($authenticated->allHeaders()['Authorization'])->toBe('Bearer new-token');
        });
    });
});

describe('HeaderAuth', function (): void {
    describe('Happy Paths', function (): void {
        it('adds custom header with value', function (): void {
            $auth = new HeaderAuth('X-API-Key', 'api-key-value');
            $request = createAuthTestRequest();

            $authenticated = $auth->authenticate($request);

            expect($authenticated->allHeaders())
                ->toHaveKey('X-API-Key')
                ->and($authenticated->allHeaders()['X-API-Key'])->toBe('api-key-value');
        });

        it('preserves existing headers when adding custom header', function (): void {
            $auth = new HeaderAuth('X-Custom-Auth', 'secret');
            $request = createAuthTestRequest()->withHeader('Content-Type', 'application/json');

            $authenticated = $auth->authenticate($request);

            expect($authenticated->allHeaders())
                ->toHaveKey('X-Custom-Auth')
                ->toHaveKey('Content-Type')
                ->and($authenticated->allHeaders()['Content-Type'])->toBe('application/json');
        });

        it('creates immutable request with custom header', function (): void {
            $auth = new HeaderAuth('X-Token', 'value');
            $original = createAuthTestRequest();

            $authenticated = $auth->authenticate($original);

            expect($original->allHeaders())->not->toHaveKey('X-Token')
                ->and($authenticated->allHeaders())->toHaveKey('X-Token');
        });
    });

    describe('Edge Cases', function (): void {
        it('handles empty header value', function (): void {
            $auth = new HeaderAuth('X-Empty', '');
            $request = createAuthTestRequest();

            $authenticated = $auth->authenticate($request);

            expect($authenticated->allHeaders()['X-Empty'])->toBe('');
        });

        it('handles header name with special characters', function (): void {
            $auth = new HeaderAuth('X-Special-Header_123', 'value');
            $request = createAuthTestRequest();

            $authenticated = $auth->authenticate($request);

            expect($authenticated->allHeaders()['X-Special-Header_123'])->toBe('value');
        });

        it('handles very long header value', function (): void {
            $longValue = str_repeat('x', 5_000);
            $auth = new HeaderAuth('X-Long', $longValue);
            $request = createAuthTestRequest();

            $authenticated = $auth->authenticate($request);

            expect($authenticated->allHeaders()['X-Long'])->toBe($longValue);
        });

        it('overwrites existing header with same name', function (): void {
            $auth = new HeaderAuth('X-Override', 'new-value');
            $request = createAuthTestRequest()->withHeader('X-Override', 'old-value');

            $authenticated = $auth->authenticate($request);

            expect($authenticated->allHeaders()['X-Override'])->toBe('new-value');
        });

        it('handles unicode characters in header value', function (): void {
            $auth = new HeaderAuth('X-Unicode', 'José García-Müller');
            $request = createAuthTestRequest();

            $authenticated = $auth->authenticate($request);

            expect($authenticated->allHeaders()['X-Unicode'])->toBe('José García-Müller');
        });
    });
});

describe('QueryAuth', function (): void {
    describe('Happy Paths', function (): void {
        it('adds query parameter with value', function (): void {
            $auth = new QueryAuth('api_key', 'my-api-key');
            $request = createAuthTestRequest();

            $authenticated = $auth->authenticate($request);

            expect($authenticated->allQuery())
                ->toHaveKey('api_key')
                ->and($authenticated->allQuery()['api_key'])->toBe('my-api-key');
        });

        it('preserves existing query parameters when adding new parameter', function (): void {
            $auth = new QueryAuth('token', 'secret-token');
            $request = createAuthTestRequest()->withQuery('page', '1');

            $authenticated = $auth->authenticate($request);

            expect($authenticated->allQuery())
                ->toHaveKey('token')
                ->toHaveKey('page')
                ->and($authenticated->allQuery()['page'])->toBe('1');
        });

        it('creates immutable request with query parameter', function (): void {
            $auth = new QueryAuth('key', 'value');
            $original = createAuthTestRequest();

            $authenticated = $auth->authenticate($original);

            expect($original->allQuery())->not->toHaveKey('key')
                ->and($authenticated->allQuery())->toHaveKey('key');
        });
    });

    describe('Edge Cases', function (): void {
        it('handles empty parameter value', function (): void {
            $auth = new QueryAuth('empty', '');
            $request = createAuthTestRequest();

            $authenticated = $auth->authenticate($request);

            expect($authenticated->allQuery()['empty'])->toBe('');
        });

        it('handles parameter name with underscores and numbers', function (): void {
            $auth = new QueryAuth('api_key_123', 'value');
            $request = createAuthTestRequest();

            $authenticated = $auth->authenticate($request);

            expect($authenticated->allQuery()['api_key_123'])->toBe('value');
        });

        it('handles very long parameter value', function (): void {
            $longValue = str_repeat('y', 3_000);
            $auth = new QueryAuth('long_param', $longValue);
            $request = createAuthTestRequest();

            $authenticated = $auth->authenticate($request);

            expect($authenticated->allQuery()['long_param'])->toBe($longValue);
        });

        it('overwrites existing parameter with same name', function (): void {
            $auth = new QueryAuth('param', 'new-value');
            $request = createAuthTestRequest()->withQuery('param', 'old-value');

            $authenticated = $auth->authenticate($request);

            expect($authenticated->allQuery()['param'])->toBe('new-value');
        });

        it('handles unicode characters in parameter value', function (): void {
            $auth = new QueryAuth('name', 'José García');
            $request = createAuthTestRequest();

            $authenticated = $auth->authenticate($request);

            expect($authenticated->allQuery()['name'])->toBe('José García');
        });

        it('handles special characters in parameter value', function (): void {
            $auth = new QueryAuth('key', 'value-with-special_chars.123!');
            $request = createAuthTestRequest();

            $authenticated = $auth->authenticate($request);

            expect($authenticated->allQuery()['key'])->toBe('value-with-special_chars.123!');
        });
    });
});

describe('CallableAuth', function (): void {
    describe('Happy Paths', function (): void {
        it('uses callback to authenticate request with custom header', function (): void {
            $auth = new CallableAuth(fn (Request $request): Request => $request->withHeader('X-Custom', 'value'));
            $request = createAuthTestRequest();

            $authenticated = $auth->authenticate($request);

            expect($authenticated->allHeaders())
                ->toHaveKey('X-Custom')
                ->and($authenticated->allHeaders()['X-Custom'])->toBe('value');
        });

        it('uses callback to authenticate request with query parameter', function (): void {
            $auth = new CallableAuth(fn (Request $request): Request => $request->withQuery('auth', 'token'));
            $request = createAuthTestRequest();

            $authenticated = $auth->authenticate($request);

            expect($authenticated->allQuery())
                ->toHaveKey('auth')
                ->and($authenticated->allQuery()['auth'])->toBe('token');
        });

        it('uses callback to add multiple headers', function (): void {
            $auth = new CallableAuth(fn (Request $request): Request => $request
                ->withHeader('X-Header-1', 'value1')
                ->withHeader('X-Header-2', 'value2'));
            $request = createAuthTestRequest();

            $authenticated = $auth->authenticate($request);

            expect($authenticated->allHeaders())
                ->toHaveKey('X-Header-1')
                ->toHaveKey('X-Header-2')
                ->and($authenticated->allHeaders()['X-Header-1'])->toBe('value1')
                ->and($authenticated->allHeaders()['X-Header-2'])->toBe('value2');
        });

        it('uses callback to add both headers and query parameters', function (): void {
            $auth = new CallableAuth(fn (Request $request): Request => $request
                ->withHeader('X-Auth', 'header-value')
                ->withQuery('api_key', 'query-value'));
            $request = createAuthTestRequest();

            $authenticated = $auth->authenticate($request);

            expect($authenticated->allHeaders())->toHaveKey('X-Auth')
                ->and($authenticated->allQuery())->toHaveKey('api_key')
                ->and($authenticated->allHeaders()['X-Auth'])->toBe('header-value')
                ->and($authenticated->allQuery()['api_key'])->toBe('query-value');
        });

        it('creates immutable request through callback', function (): void {
            $auth = new CallableAuth(fn (Request $request): Request => $request->withHeader('X-Modified', 'yes'));
            $original = createAuthTestRequest();

            $authenticated = $auth->authenticate($original);

            expect($original->allHeaders())->not->toHaveKey('X-Modified')
                ->and($authenticated->allHeaders())->toHaveKey('X-Modified');
        });
    });

    describe('Edge Cases', function (): void {
        it('handles callback that returns request unchanged', function (): void {
            $auth = new CallableAuth(fn (Request $request): Request => $request);
            $request = createAuthTestRequest();

            $authenticated = $auth->authenticate($request);

            expect($authenticated->allHeaders())->toBe($request->allHeaders())
                ->and($authenticated->allQuery())->toBe($request->allQuery());
        });

        it('handles callback with complex logic using Bearer token', function (): void {
            $auth = new CallableAuth(function (Request $request): Request {
                $token = base64_encode('username:password');

                return $request->withBearerToken($token);
            });
            $request = createAuthTestRequest();

            $authenticated = $auth->authenticate($request);

            expect($authenticated->allHeaders())
                ->toHaveKey('Authorization')
                ->and($authenticated->allHeaders()['Authorization'])
                ->toBe('Bearer '.base64_encode('username:password'));
        });

        it('handles callback that chains multiple operations', function (): void {
            $auth = new CallableAuth(fn (Request $request): Request => $request
                ->withHeader('X-Step-1', 'complete')
                ->withQuery('step', '1')
                ->withHeader('X-Step-2', 'complete')
                ->withQuery('version', 'v2'));
            $request = createAuthTestRequest();

            $authenticated = $auth->authenticate($request);

            expect($authenticated->allHeaders())->toHaveKey('X-Step-1')
                ->and($authenticated->allHeaders())->toHaveKey('X-Step-2')
                ->and($authenticated->allQuery())->toHaveKey('step')
                ->and($authenticated->allQuery())->toHaveKey('version');
        });

        it('preserves existing request data through callback', function (): void {
            $auth = new CallableAuth(fn (Request $request): Request => $request->withHeader('X-New', 'added'));
            $request = createAuthTestRequest()
                ->withHeader('X-Existing', 'value')
                ->withQuery('existing', 'param');

            $authenticated = $auth->authenticate($request);

            expect($authenticated->allHeaders())->toHaveKey('X-Existing')
                ->and($authenticated->allHeaders())->toHaveKey('X-New')
                ->and($authenticated->allQuery())->toHaveKey('existing');
        });
    });
});

describe('BasicAuth', function (): void {
    describe('Happy Paths', function (): void {
        test('adds Authorization header with Basic auth credentials', function (): void {
            // Arrange
            $auth = new BasicAuth('testuser', 'testpass');
            $request = createAuthTestRequest();

            // Act
            $authenticated = $auth->authenticate($request);

            // Assert
            $headers = $authenticated->allHeaders();
            expect($headers)->toHaveKey('Authorization')
                ->and($headers['Authorization'])->toBe('Basic '.base64_encode('testuser:testpass'));
        });

        test('preserves existing headers when adding Basic auth', function (): void {
            // Arrange
            $auth = new BasicAuth('user', 'pass');
            $request = createAuthTestRequest()->withHeader('X-Custom', 'value');

            // Act
            $authenticated = $auth->authenticate($request);

            // Assert
            expect($authenticated->allHeaders())
                ->toHaveKey('Authorization')
                ->toHaveKey('X-Custom')
                ->and($authenticated->allHeaders()['X-Custom'])->toBe('value');
        });

        test('creates immutable request with Basic auth', function (): void {
            // Arrange
            $auth = new BasicAuth('username', 'password');
            $original = createAuthTestRequest();

            // Act
            $authenticated = $auth->authenticate($original);

            // Assert
            expect($original->allHeaders())->not->toHaveKey('Authorization')
                ->and($authenticated->allHeaders())->toHaveKey('Authorization');
        });

        test('correctly encodes username and password with base64', function (): void {
            // Arrange
            $auth = new BasicAuth('admin', 'secret123');
            $request = createAuthTestRequest();

            // Act
            $authenticated = $auth->authenticate($request);

            // Assert
            $expectedAuth = 'Basic '.base64_encode('admin:secret123');
            expect($authenticated->allHeaders()['Authorization'])->toBe($expectedAuth);
        });
    });

    describe('Sad Paths', function (): void {
        test('handles empty username with Basic auth', function (): void {
            // Arrange
            $auth = new BasicAuth('', 'password');
            $request = createAuthTestRequest();

            // Act
            $authenticated = $auth->authenticate($request);

            // Assert
            $expectedAuth = 'Basic '.base64_encode(':password');
            expect($authenticated->allHeaders()['Authorization'])->toBe($expectedAuth);
        });

        test('handles empty password with Basic auth', function (): void {
            // Arrange
            $auth = new BasicAuth('username', '');
            $request = createAuthTestRequest();

            // Act
            $authenticated = $auth->authenticate($request);

            // Assert
            $expectedAuth = 'Basic '.base64_encode('username:');
            expect($authenticated->allHeaders()['Authorization'])->toBe($expectedAuth);
        });

        test('handles both empty username and password', function (): void {
            // Arrange
            $auth = new BasicAuth('', '');
            $request = createAuthTestRequest();

            // Act
            $authenticated = $auth->authenticate($request);

            // Assert
            $expectedAuth = 'Basic '.base64_encode(':');
            expect($authenticated->allHeaders()['Authorization'])->toBe($expectedAuth);
        });
    });

    describe('Edge Cases', function (): void {
        test('handles username with special characters', function (): void {
            // Arrange
            $auth = new BasicAuth('user@example.com', 'pass123');
            $request = createAuthTestRequest();

            // Act
            $authenticated = $auth->authenticate($request);

            // Assert
            $expectedAuth = 'Basic '.base64_encode('user@example.com:pass123');
            expect($authenticated->allHeaders()['Authorization'])->toBe($expectedAuth);
        });

        test('handles password with special characters', function (): void {
            // Arrange
            $auth = new BasicAuth('user', 'p@ss!w0rd#123');
            $request = createAuthTestRequest();

            // Act
            $authenticated = $auth->authenticate($request);

            // Assert
            $expectedAuth = 'Basic '.base64_encode('user:p@ss!w0rd#123');
            expect($authenticated->allHeaders()['Authorization'])->toBe($expectedAuth);
        });

        test('handles username with colon character', function (): void {
            // Arrange
            $auth = new BasicAuth('user:name', 'password');
            $request = createAuthTestRequest();

            // Act
            $authenticated = $auth->authenticate($request);

            // Assert
            $expectedAuth = 'Basic '.base64_encode('user:name:password');
            expect($authenticated->allHeaders()['Authorization'])->toBe($expectedAuth);
        });

        test('handles unicode characters in username', function (): void {
            // Arrange
            $auth = new BasicAuth('José García', 'password');
            $request = createAuthTestRequest();

            // Act
            $authenticated = $auth->authenticate($request);

            // Assert
            $expectedAuth = 'Basic '.base64_encode('José García:password');
            expect($authenticated->allHeaders()['Authorization'])->toBe($expectedAuth);
        });

        test('handles unicode characters in password', function (): void {
            // Arrange
            $auth = new BasicAuth('user', 'pässwörd');
            $request = createAuthTestRequest();

            // Act
            $authenticated = $auth->authenticate($request);

            // Assert
            $expectedAuth = 'Basic '.base64_encode('user:pässwörd');
            expect($authenticated->allHeaders()['Authorization'])->toBe($expectedAuth);
        });

        test('handles very long username', function (): void {
            // Arrange
            $longUsername = str_repeat('u', 1_000);
            $auth = new BasicAuth($longUsername, 'pass');
            $request = createAuthTestRequest();

            // Act
            $authenticated = $auth->authenticate($request);

            // Assert
            $expectedAuth = 'Basic '.base64_encode($longUsername.':pass');
            expect($authenticated->allHeaders()['Authorization'])->toBe($expectedAuth);
        });

        test('handles very long password', function (): void {
            // Arrange
            $longPassword = str_repeat('p', 1_000);
            $auth = new BasicAuth('user', $longPassword);
            $request = createAuthTestRequest();

            // Act
            $authenticated = $auth->authenticate($request);

            // Assert
            $expectedAuth = 'Basic '.base64_encode('user:'.$longPassword);
            expect($authenticated->allHeaders()['Authorization'])->toBe($expectedAuth);
        });

        test('overwrites existing Authorization header', function (): void {
            // Arrange
            $auth = new BasicAuth('newuser', 'newpass');
            $request = createAuthTestRequest()->withHeader('Authorization', 'Bearer old-token');

            // Act
            $authenticated = $auth->authenticate($request);

            // Assert
            $expectedAuth = 'Basic '.base64_encode('newuser:newpass');
            expect($authenticated->allHeaders()['Authorization'])->toBe($expectedAuth);
        });

        test('handles whitespace in username', function (): void {
            // Arrange
            $auth = new BasicAuth('user name', 'password');
            $request = createAuthTestRequest();

            // Act
            $authenticated = $auth->authenticate($request);

            // Assert
            $expectedAuth = 'Basic '.base64_encode('user name:password');
            expect($authenticated->allHeaders()['Authorization'])->toBe($expectedAuth);
        });

        test('handles whitespace in password', function (): void {
            // Arrange
            $auth = new BasicAuth('user', 'pass word');
            $request = createAuthTestRequest();

            // Act
            $authenticated = $auth->authenticate($request);

            // Assert
            $expectedAuth = 'Basic '.base64_encode('user:pass word');
            expect($authenticated->allHeaders()['Authorization'])->toBe($expectedAuth);
        });
    });
});

describe('DigestAuth', function (): void {
    describe('Happy Paths', function (): void {
        it('stores username and password', function (): void {
            $auth = new DigestAuth('admin', 'secret');

            expect($auth->username())->toBe('admin')
                ->and($auth->password())->toBe('secret');
        });

        it('returns Guzzle auth config array', function (): void {
            $auth = new DigestAuth('user', 'pass');

            $config = $auth->toGuzzleAuth();

            expect($config)->toBe(['user', 'pass', 'digest']);
        });

        it('returns request unchanged from authenticate', function (): void {
            $auth = new DigestAuth('user', 'pass');
            $request = createAuthTestRequest();

            $authenticated = $auth->authenticate($request);

            // Digest auth is a no-op at the request level
            expect($authenticated->allHeaders())->toBe($request->allHeaders());
        });
    });
});

describe('ApiKeyAuth', function (): void {
    describe('Happy Paths', function (): void {
        it('adds API key in header by default', function (): void {
            $auth = new ApiKeyAuth('my-api-key');
            $request = createAuthTestRequest();

            $authenticated = $auth->authenticate($request);

            expect($authenticated->allHeaders())
                ->toHaveKey('X-API-Key')
                ->and($authenticated->allHeaders()['X-API-Key'])->toBe('my-api-key');
        });

        it('adds API key in custom header', function (): void {
            $auth = new ApiKeyAuth('my-key', 'Authorization');
            $request = createAuthTestRequest();

            $authenticated = $auth->authenticate($request);

            expect($authenticated->allHeaders())
                ->toHaveKey('Authorization')
                ->and($authenticated->allHeaders()['Authorization'])->toBe('my-key');
        });

        it('adds API key in query parameter', function (): void {
            $auth = new ApiKeyAuth('my-key', 'api_key', ApiKeyAuth::IN_QUERY);
            $request = createAuthTestRequest();

            $authenticated = $auth->authenticate($request);

            expect($authenticated->allQuery())
                ->toHaveKey('api_key')
                ->and($authenticated->allQuery()['api_key'])->toBe('my-key');
        });

        it('creates header auth using factory method', function (): void {
            $auth = ApiKeyAuth::inHeader('secret-key', 'X-Custom-Key');
            $request = createAuthTestRequest();

            $authenticated = $auth->authenticate($request);

            expect($authenticated->allHeaders())
                ->toHaveKey('X-Custom-Key')
                ->and($authenticated->allHeaders()['X-Custom-Key'])->toBe('secret-key');
        });

        it('creates query auth using factory method', function (): void {
            $auth = ApiKeyAuth::inQuery('secret-key', 'key');
            $request = createAuthTestRequest();

            $authenticated = $auth->authenticate($request);

            expect($authenticated->allQuery())
                ->toHaveKey('key')
                ->and($authenticated->allQuery()['key'])->toBe('secret-key');
        });

        it('exposes key, name, and location', function (): void {
            $auth = new ApiKeyAuth('my-key', 'X-Key', ApiKeyAuth::IN_HEADER);

            expect($auth->key())->toBe('my-key')
                ->and($auth->name())->toBe('X-Key')
                ->and($auth->in())->toBe(ApiKeyAuth::IN_HEADER);
        });
    });
});

describe('JwtAuth', function (): void {
    describe('Happy Paths', function (): void {
        it('adds JWT as Bearer token', function (): void {
            $auth = new JwtAuth('eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIn0.dozjgNryP4J3jVmNHl0w5N_XgL0n3I9PlFUP0THsR8U');
            $request = createAuthTestRequest();

            $authenticated = $auth->authenticate($request);

            expect($authenticated->allHeaders())
                ->toHaveKey('Authorization')
                ->and($authenticated->allHeaders()['Authorization'])->toStartWith('Bearer ');
        });

        it('creates auth using static token factory', function (): void {
            $auth = JwtAuth::token('my-jwt-token');
            $request = createAuthTestRequest();

            $authenticated = $auth->authenticate($request);

            expect($authenticated->allHeaders()['Authorization'])->toBe('Bearer my-jwt-token');
        });

        it('creates auth with expiry time', function (): void {
            $expiresAt = CarbonImmutable::now()->addHours(1);
            $auth = JwtAuth::token('token', $expiresAt);

            expect($auth->getExpiresAt())->toBe($expiresAt)
                ->and($auth->hasExpired())->toBeFalse()
                ->and($auth->isValid())->toBeTrue();
        });

        it('detects expired token', function (): void {
            $expiresAt = CarbonImmutable::now()->subHours(1);
            $auth = JwtAuth::token('token', $expiresAt);

            expect($auth->hasExpired())->toBeTrue()
                ->and($auth->isValid())->toBeFalse();
        });

        it('returns false for hasExpired when no expiry set', function (): void {
            $auth = JwtAuth::token('token');

            expect($auth->hasExpired())->toBeFalse()
                ->and($auth->isValid())->toBeTrue()
                ->and($auth->getExpiresAt())->toBeNull();
        });

        it('uses token provider when set', function (): void {
            $callCount = 0;
            $auth = JwtAuth::withProvider(function () use (&$callCount): string {
                ++$callCount;

                return 'dynamic-token-'.$callCount;
            });

            expect($auth->getToken())->toBe('dynamic-token-1')
                ->and($auth->getToken())->toBe('dynamic-token-2');
        });

        it('can update token', function (): void {
            $auth = JwtAuth::token('old-token');
            $newExpiry = CarbonImmutable::now()->addHours(2);

            $auth->setToken('new-token', $newExpiry);

            expect($auth->getToken())->toBe('new-token')
                ->and($auth->getExpiresAt())->toBe($newExpiry);
        });
    });
});

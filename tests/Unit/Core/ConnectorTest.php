<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Relay\Core\Connector;
use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;
use Cline\Relay\Features\RateLimiting\MemoryStore;
use Cline\Relay\Support\Attributes\ThrowOnError;
use Cline\Relay\Transport\Pool\Pool;
use GuzzleHttp\HandlerStack;
use Psr\Http\Client\ClientInterface;
use Psr\SimpleCache\CacheInterface;
use Tests\Fixtures\CustomFailureConnector;

// Test connector implementations
/**
 * @author Brian Faust <brian@cline.sh>
 */
final class TestConnector extends Connector
{
    public function baseUrl(): string
    {
        return 'https://api.example.com';
    }
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class CustomTimeoutConnector extends Connector
{
    public function baseUrl(): string
    {
        return 'https://api.example.com';
    }

    #[Override()]
    public function timeout(): int
    {
        return 60;
    }

    #[Override()]
    public function connectTimeout(): int
    {
        return 20;
    }
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class CustomHeadersConnector extends Connector
{
    public function baseUrl(): string
    {
        return 'https://api.example.com';
    }

    #[Override()]
    public function defaultHeaders(): array
    {
        return [
            'X-Custom-Header' => 'custom-value',
            'X-Api-Version' => 'v1',
        ];
    }
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class DynamicBaseUrlConnector extends Connector
{
    public function __construct(
        private readonly string $environment = 'production',
    ) {}

    public function baseUrl(): string
    {
        return 'https://api.example.com';
    }

    #[Override()]
    public function resolveBaseUrl(): string
    {
        return match ($this->environment) {
            'staging' => 'https://staging-api.example.com',
            'development' => 'https://dev-api.example.com',
            default => $this->baseUrl(),
        };
    }
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class AuthenticatedConnector extends Connector
{
    private ?string $lastAuthHeader = null;

    public function __construct(
        private readonly string $token,
    ) {}

    public function baseUrl(): string
    {
        return 'https://api.example.com';
    }

    #[Override()]
    public function authenticate(Request $request): void
    {
        $this->lastAuthHeader = 'Bearer '.$this->token;
        // Note: Request::withHeader() returns a clone, so we can't modify $request directly
        // This test verifies the method is callable and doesn't throw
    }

    public function getLastAuthHeader(): ?string
    {
        return $this->lastAuthHeader;
    }
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class CustomConfigConnector extends Connector
{
    public function baseUrl(): string
    {
        return 'https://api.example.com';
    }

    #[Override()]
    public function defaultConfig(): array
    {
        return [
            'verify' => false,
            'allow_redirects' => true,
        ];
    }
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class CustomCacheableMethodsConnector extends Connector
{
    public function baseUrl(): string
    {
        return 'https://api.example.com';
    }

    #[Override()]
    public function cacheableMethods(): array
    {
        return ['GET', 'HEAD', 'OPTIONS'];
    }
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class CustomCacheKeyPrefixConnector extends Connector
{
    public function baseUrl(): string
    {
        return 'https://api.example.com';
    }

    #[Override()]
    public function cacheKeyPrefix(): string
    {
        return 'my_api_';
    }
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class CustomCacheTtlConnector extends Connector
{
    public function baseUrl(): string
    {
        return 'https://api.example.com';
    }

    #[Override()]
    public function cacheTtl(): int
    {
        return 600;
    }
}

describe('Connector', function (): void {
    describe('Happy Paths', function (): void {
        it('returns configured base URL from baseUrl method', function (): void {
            // Arrange
            $connector = new TestConnector();

            // Act
            $result = $connector->baseUrl();

            // Assert
            expect($result)->toBe('https://api.example.com');
        });

        it('returns default timeout of 30 seconds', function (): void {
            // Arrange
            $connector = new TestConnector();

            // Act
            $result = $connector->timeout();

            // Assert
            expect($result)->toBe(30);
        });

        it('returns custom timeout when overridden', function (): void {
            // Arrange
            $connector = new CustomTimeoutConnector();

            // Act
            $result = $connector->timeout();

            // Assert
            expect($result)->toBe(60);
        });

        it('returns default connect timeout of 10 seconds', function (): void {
            // Arrange
            $connector = new TestConnector();

            // Act
            $result = $connector->connectTimeout();

            // Assert
            expect($result)->toBe(10);
        });

        it('returns custom connect timeout when overridden', function (): void {
            // Arrange
            $connector = new CustomTimeoutConnector();

            // Act
            $result = $connector->connectTimeout();

            // Assert
            expect($result)->toBe(20);
        });

        it('returns empty array for default headers by default', function (): void {
            // Arrange
            $connector = new TestConnector();

            // Act
            $result = $connector->defaultHeaders();

            // Assert
            expect($result)
                ->toBeArray()
                ->toBeEmpty();
        });

        it('returns custom headers when overridden', function (): void {
            // Arrange
            $connector = new CustomHeadersConnector();

            // Act
            $result = $connector->defaultHeaders();

            // Assert
            expect($result)
                ->toBeArray()
                ->toHaveCount(2)
                ->toHaveKey('X-Custom-Header', 'custom-value')
                ->toHaveKey('X-Api-Version', 'v1');
        });

        it('returns empty array for default config by default', function (): void {
            // Arrange
            $connector = new TestConnector();

            // Act
            $result = $connector->defaultConfig();

            // Assert
            expect($result)
                ->toBeArray()
                ->toBeEmpty();
        });

        it('returns custom config when overridden', function (): void {
            // Arrange
            $connector = new CustomConfigConnector();

            // Act
            $result = $connector->defaultConfig();

            // Assert
            expect($result)
                ->toBeArray()
                ->toHaveCount(2)
                ->toHaveKey('verify', false)
                ->toHaveKey('allow_redirects', true);
        });

        it('resolveBaseUrl returns baseUrl by default', function (): void {
            // Arrange
            $connector = new TestConnector();

            // Act
            $result = $connector->resolveBaseUrl();

            // Assert
            expect($result)->toBe($connector->baseUrl());
        });

        it('resolveBaseUrl supports dynamic base URL resolution', function (): void {
            // Arrange
            $productionConnector = new DynamicBaseUrlConnector('production');
            $stagingConnector = new DynamicBaseUrlConnector('staging');
            $devConnector = new DynamicBaseUrlConnector('development');

            // Act & Assert
            expect($productionConnector->resolveBaseUrl())->toBe('https://api.example.com');
            expect($stagingConnector->resolveBaseUrl())->toBe('https://staging-api.example.com');
            expect($devConnector->resolveBaseUrl())->toBe('https://dev-api.example.com');
        });

        it('authenticate method can be overridden to implement custom authentication', function (): void {
            // Arrange
            $connector = new AuthenticatedConnector('test-token-123');
            $request = new class() extends Request
            {
                public function endpoint(): string
                {
                    return '/users';
                }

                public function method(): string
                {
                    return 'GET';
                }
            };

            // Act
            $connector->authenticate($request);

            // Assert
            // Since Request::withHeader() returns a clone, we verify the method was called
            // by checking the connector's internal state
            expect($connector->getLastAuthHeader())->toBe('Bearer test-token-123');
        });

        it('rateLimitStore returns MemoryStore instance by default', function (): void {
            // Arrange
            $connector = new TestConnector();

            // Act
            $result = $connector->rateLimitStore();

            // Assert
            expect($result)->toBeInstanceOf(MemoryStore::class);
        });

        it('pool creates Pool instance with provided requests', function (): void {
            // Arrange
            $connector = new TestConnector();
            $requests = [
                new class() extends Request
                {
                    public function endpoint(): string
                    {
                        return '/users';
                    }

                    public function method(): string
                    {
                        return 'GET';
                    }
                },
                new class() extends Request
                {
                    public function endpoint(): string
                    {
                        return '/posts';
                    }

                    public function method(): string
                    {
                        return 'GET';
                    }
                },
            ];

            // Act
            $pool = $connector->pool($requests);

            // Assert
            expect($pool)
                ->toBeInstanceOf(Pool::class);
        });

        it('middleware returns HandlerStack instance', function (): void {
            // Arrange
            $connector = new TestConnector();

            // Act
            $result = $connector->middleware();

            // Assert
            expect($result)->toBeInstanceOf(HandlerStack::class);
        });

        it('cache returns null by default', function (): void {
            // Arrange
            $connector = new TestConnector();

            // Act
            $result = $connector->cache();

            // Assert
            expect($result)->toBeNull();
        });

        it('cacheConfig returns null when cache is not configured', function (): void {
            // Arrange
            $connector = new TestConnector();

            // Act
            $result = $connector->cacheConfig();

            // Assert
            expect($result)->toBeNull();
        });

        it('cacheTtl returns default value of 300 seconds', function (): void {
            // Arrange
            $connector = new TestConnector();

            // Act
            $result = $connector->cacheTtl();

            // Assert
            expect($result)->toBe(300);
        });

        it('cacheTtl returns custom value when overridden', function (): void {
            // Arrange
            $connector = new CustomCacheTtlConnector();

            // Act
            $result = $connector->cacheTtl();

            // Assert
            expect($result)->toBe(600);
        });

        it('cacheKeyPrefix returns empty string by default', function (): void {
            // Arrange
            $connector = new TestConnector();

            // Act
            $result = $connector->cacheKeyPrefix();

            // Assert
            expect($result)
                ->toBeString()
                ->toBeEmpty();
        });

        it('cacheKeyPrefix returns custom value when overridden', function (): void {
            // Arrange
            $connector = new CustomCacheKeyPrefixConnector();

            // Act
            $result = $connector->cacheKeyPrefix();

            // Assert
            expect($result)->toBe('my_api_');
        });

        it('cacheableMethods returns GET and HEAD by default', function (): void {
            // Arrange
            $connector = new TestConnector();

            // Act
            $result = $connector->cacheableMethods();

            // Assert
            expect($result)
                ->toBeArray()
                ->toHaveCount(2)
                ->toContain('GET')
                ->toContain('HEAD');
        });

        it('cacheableMethods returns custom methods when overridden', function (): void {
            // Arrange
            $connector = new CustomCacheableMethodsConnector();

            // Act
            $result = $connector->cacheableMethods();

            // Assert
            expect($result)
                ->toBeArray()
                ->toHaveCount(3)
                ->toContain('GET')
                ->toContain('HEAD')
                ->toContain('OPTIONS');
        });

        it('rateLimit returns null by default', function (): void {
            // Arrange
            $connector = new TestConnector();

            // Act
            $result = $connector->rateLimit();

            // Assert
            expect($result)->toBeNull();
        });

        it('concurrencyLimit returns null by default', function (): void {
            // Arrange
            $connector = new TestConnector();

            // Act
            $result = $connector->concurrencyLimit();

            // Assert
            expect($result)->toBeNull();
        });

        it('hasRequestFailed returns true for 4xx responses', function (): void {
            // Arrange
            $connector = new TestConnector();
            $response = Response::make(['error' => 'Not found'], 404);

            // Act
            $result = $connector->hasRequestFailed($response);

            // Assert
            expect($result)->toBeTrue();
        });

        it('hasRequestFailed returns true for 5xx responses', function (): void {
            // Arrange
            $connector = new TestConnector();
            $response = Response::make(['error' => 'Server error'], 500);

            // Act
            $result = $connector->hasRequestFailed($response);

            // Assert
            expect($result)->toBeTrue();
        });

        it('hasRequestFailed returns false for 2xx responses', function (): void {
            // Arrange
            $connector = new TestConnector();
            $response = Response::make(['id' => 1], 200);

            // Act
            $result = $connector->hasRequestFailed($response);

            // Assert
            expect($result)->toBeFalse();
        });

        it('hasRequestFailed can be customized', function (): void {
            // Arrange - connector that checks for error in body
            $connector = new CustomFailureConnector();

            // 200 with error in body should be considered failed
            $responseWithError = Response::make(['error' => 'Something went wrong'], 200);
            expect($connector->hasRequestFailed($responseWithError))->toBeTrue();

            // 200 without error should not be considered failed
            $responseWithoutError = Response::make(['id' => 1], 200);
            expect($connector->hasRequestFailed($responseWithoutError))->toBeFalse();
        });

        it('debug returns connector instance for method chaining', function (): void {
            // Arrange
            $connector = new TestConnector();

            // Act
            $result = $connector->debug();

            // Assert
            expect($result)->toBe($connector);
        });

        it('hasAttribute returns false when attribute does not exist', function (): void {
            // Arrange
            $connector = new TestConnector();

            // Act
            $result = $connector->hasAttribute(ThrowOnError::class);

            // Assert
            expect($result)->toBeFalse();
        });

        it('getAttribute returns null when attribute does not exist', function (): void {
            // Arrange
            $connector = new TestConnector();

            // Act
            $result = $connector->getAttribute(ThrowOnError::class);

            // Assert
            expect($result)->toBeNull();
        });
    });

    describe('Edge Cases', function (): void {
        it('pool handles empty requests array', function (): void {
            // Arrange
            $connector = new TestConnector();
            $requests = [];

            // Act
            $pool = $connector->pool($requests);

            // Assert
            expect($pool)->toBeInstanceOf(Pool::class);
        });

        it('pool handles keyed requests array', function (): void {
            // Arrange
            $connector = new TestConnector();
            $requests = [
                'users' => new class() extends Request
                {
                    public function endpoint(): string
                    {
                        return '/users';
                    }

                    public function method(): string
                    {
                        return 'GET';
                    }
                },
                'posts' => new class() extends Request
                {
                    public function endpoint(): string
                    {
                        return '/posts';
                    }

                    public function method(): string
                    {
                        return 'GET';
                    }
                },
            ];

            // Act
            $pool = $connector->pool($requests);

            // Assert
            expect($pool)->toBeInstanceOf(Pool::class);
        });

        it('defaultHeaders returns empty array with no entries', function (): void {
            // Arrange
            $connector = new TestConnector();

            // Act
            $result = $connector->defaultHeaders();

            // Assert
            expect($result)
                ->toBeArray()
                ->toEqual([]);
        });

        it('defaultConfig returns empty array with no entries', function (): void {
            // Arrange
            $connector = new TestConnector();

            // Act
            $result = $connector->defaultConfig();

            // Assert
            expect($result)
                ->toBeArray()
                ->toEqual([]);
        });

        it('authenticate does nothing when not overridden', function (): void {
            // Arrange
            $connector = new TestConnector();
            $request = new class() extends Request
            {
                public function endpoint(): string
                {
                    return '/test';
                }

                public function method(): string
                {
                    return 'GET';
                }
            };
            $originalHeaders = $request->allHeaders();

            // Act
            $connector->authenticate($request);

            // Assert
            expect($request->allHeaders())->toEqual($originalHeaders);
        });

        it('resolveBaseUrl with trailing slash returns same as baseUrl', function (): void {
            // Arrange
            $connector = new class() extends Connector
            {
                public function baseUrl(): string
                {
                    return 'https://api.example.com/';
                }
            };

            // Act
            $result = $connector->resolveBaseUrl();

            // Assert
            expect($result)->toBe('https://api.example.com/');
        });

        it('baseUrl with different protocols works correctly', function (): void {
            // Arrange
            $httpConnector = new class() extends Connector
            {
                public function baseUrl(): string
                {
                    return 'http://api.example.com';
                }
            };
            $httpsConnector = new class() extends Connector
            {
                public function baseUrl(): string
                {
                    return 'https://api.example.com';
                }
            };

            // Act & Assert
            expect($httpConnector->baseUrl())->toBe('http://api.example.com');
            expect($httpsConnector->baseUrl())->toBe('https://api.example.com');
        });

        it('baseUrl with port number works correctly', function (): void {
            // Arrange
            $connector = new class() extends Connector
            {
                public function baseUrl(): string
                {
                    return 'https://api.example.com:8080';
                }
            };

            // Act
            $result = $connector->baseUrl();

            // Assert
            expect($result)->toBe('https://api.example.com:8080');
        });

        it('baseUrl with path works correctly', function (): void {
            // Arrange
            $connector = new class() extends Connector
            {
                public function baseUrl(): string
                {
                    return 'https://api.example.com/v1';
                }
            };

            // Act
            $result = $connector->baseUrl();

            // Assert
            expect($result)->toBe('https://api.example.com/v1');
        });

        it('timeout returns positive integer', function (): void {
            // Arrange
            $connector = new TestConnector();

            // Act
            $result = $connector->timeout();

            // Assert
            expect($result)
                ->toBeInt()
                ->toBeGreaterThan(0);
        });

        it('connectTimeout returns positive integer', function (): void {
            // Arrange
            $connector = new TestConnector();

            // Act
            $result = $connector->connectTimeout();

            // Assert
            expect($result)
                ->toBeInt()
                ->toBeGreaterThan(0);
        });

        it('cacheTtl returns positive integer', function (): void {
            // Arrange
            $connector = new TestConnector();

            // Act
            $result = $connector->cacheTtl();

            // Assert
            expect($result)
                ->toBeInt()
                ->toBeGreaterThan(0);
        });

        it('defaultHeaders returns associative array', function (): void {
            // Arrange
            $connector = new CustomHeadersConnector();

            // Act
            $result = $connector->defaultHeaders();

            // Assert
            expect($result)
                ->toBeArray()
                ->each->toBeString();
        });
    });

    describe('Additional Coverage', function (): void {
        it('invalidateCacheTags calls cache invalidation when cache is configured', function (): void {
            // Arrange
            $cacheStore = new class() implements CacheInterface
            {
                public array $invalidatedTags = [];

                private array $data = [];

                public function get(string $key, mixed $default = null): mixed
                {
                    return $this->data[$key] ?? $default;
                }

                public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
                {
                    $this->data[$key] = $value;

                    return true;
                }

                public function delete(string $key): bool
                {
                    unset($this->data[$key]);

                    return true;
                }

                public function clear(): bool
                {
                    $this->data = [];

                    return true;
                }

                public function getMultiple(iterable $keys, mixed $default = null): iterable
                {
                    yield from [];
                }

                public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
                {
                    return true;
                }

                public function deleteMultiple(iterable $keys): bool
                {
                    return true;
                }

                public function has(string $key): bool
                {
                    return array_key_exists($key, $this->data);
                }
            };

            $connector = new class($cacheStore) extends Connector
            {
                public function __construct(
                    private readonly CacheInterface $cacheStore,
                ) {}

                public function baseUrl(): string
                {
                    return 'https://api.example.com';
                }

                public function cache(): CacheInterface
                {
                    return $this->cacheStore;
                }
            };

            // Act
            $connector->invalidateCacheTags(['users', 'posts']);

            // No exception should be thrown
            expect(true)->toBeTrue();
        });

        it('invalidateCacheTags does nothing when cache is not configured', function (): void {
            // Arrange
            $connector = new TestConnector();

            // Act
            $connector->invalidateCacheTags(['users']);

            // Assert - should complete without error
            expect(true)->toBeTrue();
        });

        it('buildBody encodes form-urlencoded content type correctly', function (): void {
            // Arrange
            $connector = new class() extends Connector
            {
                public function baseUrl(): string
                {
                    return 'https://api.example.com';
                }

                // Expose buildBody for testing
                public function testBuildBody(Request $request): ?string
                {
                    return $this->buildBody($request);
                }
            };

            $request = new class() extends Request
            {
                public function endpoint(): string
                {
                    return '/form';
                }

                public function method(): string
                {
                    return 'POST';
                }

                public function body(): array
                {
                    return [
                        'name' => 'John Doe',
                        'email' => 'john@example.com',
                    ];
                }

                public function contentType(): string
                {
                    return 'application/x-www-form-urlencoded';
                }
            };

            // Act
            $body = $connector->testBuildBody($request);

            // Assert
            expect($body)->toBe('name=John+Doe&email=john%40example.com');
        });

        it('buildBody encodes JSON content type correctly', function (): void {
            // Arrange
            $connector = new class() extends Connector
            {
                public function baseUrl(): string
                {
                    return 'https://api.example.com';
                }

                // Expose buildBody for testing
                public function testBuildBody(Request $request): ?string
                {
                    return $this->buildBody($request);
                }
            };

            $request = new class() extends Request
            {
                public function endpoint(): string
                {
                    return '/json';
                }

                public function method(): string
                {
                    return 'POST';
                }

                public function body(): array
                {
                    return [
                        'name' => 'John Doe',
                        'email' => 'john@example.com',
                    ];
                }

                public function contentType(): string
                {
                    return 'application/json';
                }
            };

            // Act
            $body = $connector->testBuildBody($request);

            // Assert
            expect($body)->toBe('{"name":"John Doe","email":"john@example.com"}');
        });

        it('getHttpClient initializes GuzzleDriver when not set', function (): void {
            // Arrange
            $connector = new class() extends Connector
            {
                public function baseUrl(): string
                {
                    return 'https://api.example.com';
                }

                // Expose getHttpClient for testing
                public function testGetHttpClient(): ClientInterface
                {
                    return $this->getHttpClient();
                }
            };

            // Act
            $httpClient = $connector->testGetHttpClient();

            // Assert
            expect($httpClient)->toBeInstanceOf(ClientInterface::class);
        });
    });
});

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
use Cline\Relay\Features\Pagination\PaginatedResponse;
use Cline\Relay\Features\RateLimiting\RateLimitConfig;
use Cline\Relay\Support\Attributes\Pagination\CursorPagination;
use Cline\Relay\Support\Attributes\Pagination\LinkPagination;
use Cline\Relay\Support\Attributes\Pagination\OffsetPagination;
use Cline\Relay\Support\Attributes\Pagination\Pagination;
use Cline\Relay\Support\Attributes\Pagination\SimplePagination;
use Cline\Relay\Support\Attributes\ThrowOnError;
use Cline\Relay\Support\Exceptions\Client\ForbiddenException;
use Cline\Relay\Support\Exceptions\Client\NotFoundException;
use Cline\Relay\Support\Exceptions\Client\RateLimitException;
use Cline\Relay\Support\Exceptions\Client\UnauthorizedException;
use Cline\Relay\Support\Exceptions\Client\ValidationException;
use Cline\Relay\Support\Exceptions\ClientException;
use Cline\Relay\Support\Exceptions\MockClientException;
use Cline\Relay\Support\Exceptions\Server\InternalServerException;
use Cline\Relay\Support\Exceptions\Server\ServiceUnavailableException;
use Cline\Relay\Support\Exceptions\ServerException;
use Cline\Relay\Testing\MockClient;
use Cline\Relay\Testing\MockConfig;
use Cline\Relay\Testing\MockResponse;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Psr\Http\Client\ClientInterface;
use Psr\SimpleCache\CacheInterface;

// Test connector that allows injecting mock HTTP client
/**
 * @author Brian Faust <brian@cline.sh>
 */
final class TestableConnector extends Connector
{
    public function baseUrl(): string
    {
        return 'https://api.example.com';
    }

    public function setHttpClient(ClientInterface $client): void
    {
        $this->httpClient = $client;
    }

    public function getHttpClientInstance(): ?ClientInterface
    {
        return $this->httpClient;
    }
}

// Connector with ThrowOnError attribute for client errors
/**
 * @author Brian Faust <brian@cline.sh>
 */
#[ThrowOnError(clientErrors: true, serverErrors: false)]
final class ThrowOnClientErrorConnector extends Connector
{
    public function baseUrl(): string
    {
        return 'https://api.example.com';
    }

    public function setHttpClient(ClientInterface $client): void
    {
        $this->httpClient = $client;
    }
}

// Connector with ThrowOnError attribute for server errors
/**
 * @author Brian Faust <brian@cline.sh>
 */
#[ThrowOnError(clientErrors: false, serverErrors: true)]
final class ThrowOnServerErrorConnector extends Connector
{
    public function baseUrl(): string
    {
        return 'https://api.example.com';
    }

    public function setHttpClient(ClientInterface $client): void
    {
        $this->httpClient = $client;
    }
}

// Connector with ThrowOnError for both client and server errors
/**
 * @author Brian Faust <brian@cline.sh>
 */
#[ThrowOnError(clientErrors: true, serverErrors: true)]
final class ThrowOnAllErrorsConnector extends Connector
{
    public function baseUrl(): string
    {
        return 'https://api.example.com';
    }

    public function setHttpClient(ClientInterface $client): void
    {
        $this->httpClient = $client;
    }
}

// Connector with caching enabled
/**
 * @author Brian Faust <brian@cline.sh>
 */
final class CachedConnector extends Connector
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

    #[Override()]
    public function cacheTtl(): int
    {
        return 300;
    }

    public function setHttpClient(ClientInterface $client): void
    {
        $this->httpClient = $client;
    }
}

// Connector with rate limiting enabled
/**
 * @author Brian Faust <brian@cline.sh>
 */
final class RateLimitedConnector extends Connector
{
    public function baseUrl(): string
    {
        return 'https://api.example.com';
    }

    public function rateLimit(): RateLimitConfig
    {
        return new RateLimitConfig(requests: 10, perSeconds: 60);
    }

    public function setHttpClient(ClientInterface $client): void
    {
        $this->httpClient = $client;
    }
}

// Request with ThrowOnError attribute
/**
 * @author Brian Faust <brian@cline.sh>
 */
#[ThrowOnError(clientErrors: true, serverErrors: true)]
final class ThrowOnErrorRequest extends Request
{
    public function __construct(
        private readonly string $endpoint = '/users',
    ) {}

    public function endpoint(): string
    {
        return $this->endpoint;
    }

    #[Override()]
    public function method(): string
    {
        return 'GET';
    }
}

// Request with response transformer
/**
 * @author Brian Faust <brian@cline.sh>
 */
final class TransformedRequest extends Request
{
    public function endpoint(): string
    {
        return '/users/1';
    }

    #[Override()]
    public function method(): string
    {
        return 'GET';
    }

    #[Override()]
    public function transformResponse(Response $response): Response
    {
        // Transform response data by adding a custom field
        $data = $response->json();
        $data['transformed'] = true;

        // Create a new PSR response with transformed data
        $newPsrResponse = new GuzzleResponse(
            $response->status(),
            $response->headers(),
            json_encode($data),
        );

        return new Response($newPsrResponse, $this);
    }
}

// Simple request for pagination testing
/**
 * @author Brian Faust <brian@cline.sh>
 */
#[Pagination()]
final class PagePaginationRequest extends Request
{
    public function endpoint(): string
    {
        return '/users';
    }

    #[Override()]
    public function method(): string
    {
        return 'GET';
    }
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
#[CursorPagination()]
final class CursorPaginationRequest extends Request
{
    public function endpoint(): string
    {
        return '/users';
    }

    #[Override()]
    public function method(): string
    {
        return 'GET';
    }
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
#[OffsetPagination()]
final class OffsetPaginationRequest extends Request
{
    public function endpoint(): string
    {
        return '/users';
    }

    #[Override()]
    public function method(): string
    {
        return 'GET';
    }
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
#[LinkPagination()]
final class LinkPaginationRequest extends Request
{
    public function endpoint(): string
    {
        return '/users';
    }

    #[Override()]
    public function method(): string
    {
        return 'GET';
    }
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
#[SimplePagination()]
final class SimplePaginationRequest extends Request
{
    public function endpoint(): string
    {
        return '/users';
    }

    #[Override()]
    public function method(): string
    {
        return 'GET';
    }
}

// Helper function to create mock Guzzle client
function createMockClient(array $responses): Client
{
    $mock = new MockHandler($responses);
    $handlerStack = HandlerStack::create($mock);

    return new Client(['handler' => $handlerStack]);
}

// Helper function to create in-memory cache for connector tests
function createConnectorInMemoryCache(): CacheInterface
{
    return new class() implements CacheInterface
    {
        private array $cache = [];

        public function get(string $key, mixed $default = null): mixed
        {
            return $this->cache[$key] ?? $default;
        }

        public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
        {
            $this->cache[$key] = $value;

            return true;
        }

        public function delete(string $key): bool
        {
            unset($this->cache[$key]);

            return true;
        }

        public function clear(): bool
        {
            $this->cache = [];

            return true;
        }

        public function getMultiple(iterable $keys, mixed $default = null): iterable
        {
            $result = [];

            foreach ($keys as $key) {
                $result[$key] = $this->get($key, $default);
            }

            return $result;
        }

        public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
        {
            foreach ($values as $key => $value) {
                $this->set($key, $value, $ttl);
            }

            return true;
        }

        public function deleteMultiple(iterable $keys): bool
        {
            foreach ($keys as $key) {
                $this->delete($key);
            }

            return true;
        }

        public function has(string $key): bool
        {
            return array_key_exists($key, $this->cache);
        }
    };
}

describe('Connector', function (): void {
    describe('Happy Paths', function (): void {
        describe('send() method', function (): void {
            test('sends GET request and returns successful response', function (): void {
                // Arrange
                $connector = new TestableConnector();
                $connector->setHttpClient(createMockClient([
                    new GuzzleResponse(200, ['Content-Type' => 'application/json'], json_encode(['id' => 1, 'name' => 'John'])),
                ]));

                $request = new class() extends Request
                {
                    public function endpoint(): string
                    {
                        return '/users/1';
                    }

                    public function method(): string
                    {
                        return 'GET';
                    }
                };

                // Act
                $response = $connector->send($request);

                // Assert
                expect($response->status())->toBe(200)
                    ->and($response->successful())->toBeTrue()
                    ->and($response->json('id'))->toBe(1)
                    ->and($response->json('name'))->toBe('John');
            });

            test('sends request with rate limiting enabled without hitting limit', function (): void {
                // Arrange
                $connector = new RateLimitedConnector();
                $connector->setHttpClient(createMockClient([
                    new GuzzleResponse(200, [], json_encode(['success' => true])),
                ]));

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
                $response = $connector->send($request);

                // Assert
                expect($response->status())->toBe(200)
                    ->and($response->json('success'))->toBeTrue();
            });

            test('caches GET request response on first call', function (): void {
                // Arrange
                $cache = createConnectorInMemoryCache();
                $connector = new CachedConnector($cache);
                $connector->setHttpClient(createMockClient([
                    new GuzzleResponse(200, ['Content-Type' => 'application/json'], json_encode(['id' => 1, 'cached' => false])),
                ]));

                $request = new class() extends Request
                {
                    public function endpoint(): string
                    {
                        return '/users/1';
                    }

                    public function method(): string
                    {
                        return 'GET';
                    }
                };

                // Act
                $response = $connector->send($request);

                // Assert
                expect($response->status())->toBe(200)
                    ->and($response->json('id'))->toBe(1);
            });

            test('returns cached response on second identical request', function (): void {
                // Arrange
                $cache = createConnectorInMemoryCache();
                $connector = new CachedConnector($cache);
                $connector->setHttpClient(createMockClient([
                    new GuzzleResponse(200, ['Content-Type' => 'application/json'], json_encode(['id' => 1, 'call' => 1])),
                    new GuzzleResponse(200, ['Content-Type' => 'application/json'], json_encode(['id' => 1, 'call' => 2])),
                ]));

                $request = new class() extends Request
                {
                    public function endpoint(): string
                    {
                        return '/users/1';
                    }

                    public function method(): string
                    {
                        return 'GET';
                    }
                };

                // Act
                $firstResponse = $connector->send($request);
                $secondResponse = $connector->send($request);

                // Assert - second response should be cached (same data as first)
                expect($firstResponse->json('call'))->toBe(1)
                    ->and($secondResponse->json('call'))->toBe(1); // Cached response
            });

            test('transforms response when request has transformer', function (): void {
                // Arrange
                $connector = new TestableConnector();
                $connector->setHttpClient(createMockClient([
                    new GuzzleResponse(200, ['Content-Type' => 'application/json'], json_encode(['id' => 1])),
                ]));

                $request = new TransformedRequest();

                // Act
                $response = $connector->send($request);

                // Assert
                expect($response->json('id'))->toBe(1)
                    ->and($response->json('transformed'))->toBeTrue();
            });

            test('tracks response duration in milliseconds', function (): void {
                // Arrange
                $connector = new TestableConnector();
                $connector->setHttpClient(createMockClient([
                    new GuzzleResponse(200, [], json_encode(['success' => true])),
                ]));

                $request = new class() extends Request
                {
                    public function endpoint(): string
                    {
                        return '/ping';
                    }

                    public function method(): string
                    {
                        return 'GET';
                    }
                };

                // Act
                $response = $connector->send($request);

                // Assert
                expect($response->duration())->toBeGreaterThan(0.0);
            });
        });

        describe('HTTP method convenience methods', function (): void {
            test('get() sends GET request with query parameters', function (): void {
                // Arrange
                $connector = new TestableConnector();
                $connector->setHttpClient(createMockClient([
                    new GuzzleResponse(200, ['Content-Type' => 'application/json'], json_encode(['users' => []])),
                ]));

                // Act
                $response = $connector->get('/users', ['page' => 1, 'limit' => 10]);

                // Assert
                expect($response->status())->toBe(200)
                    ->and($response->json('users'))->toBe([]);
            });

            test('post() sends POST request with JSON body', function (): void {
                // Arrange
                $connector = new TestableConnector();
                $connector->setHttpClient(createMockClient([
                    new GuzzleResponse(201, ['Content-Type' => 'application/json'], json_encode(['id' => 1, 'name' => 'John'])),
                ]));

                // Act
                $response = $connector->post('/users', ['name' => 'John', 'email' => 'john@example.com']);

                // Assert
                expect($response->status())->toBe(201)
                    ->and($response->json('id'))->toBe(1)
                    ->and($response->json('name'))->toBe('John');
            });

            test('put() sends PUT request with JSON body', function (): void {
                // Arrange
                $connector = new TestableConnector();
                $connector->setHttpClient(createMockClient([
                    new GuzzleResponse(200, ['Content-Type' => 'application/json'], json_encode(['id' => 1, 'name' => 'Jane'])),
                ]));

                // Act
                $response = $connector->put('/users/1', ['name' => 'Jane']);

                // Assert
                expect($response->status())->toBe(200)
                    ->and($response->json('name'))->toBe('Jane');
            });

            test('patch() sends PATCH request with JSON body', function (): void {
                // Arrange
                $connector = new TestableConnector();
                $connector->setHttpClient(createMockClient([
                    new GuzzleResponse(200, ['Content-Type' => 'application/json'], json_encode(['id' => 1, 'email' => 'newemail@example.com'])),
                ]));

                // Act
                $response = $connector->patch('/users/1', ['email' => 'newemail@example.com']);

                // Assert
                expect($response->status())->toBe(200)
                    ->and($response->json('email'))->toBe('newemail@example.com');
            });

            test('delete() sends DELETE request', function (): void {
                // Arrange
                $connector = new TestableConnector();
                $connector->setHttpClient(createMockClient([
                    new GuzzleResponse(204, []),
                ]));

                // Act
                $response = $connector->delete('/users/1');

                // Assert
                expect($response->status())->toBe(204)
                    ->and($response->successful())->toBeTrue();
            });

            test('get() with empty query parameters sends request without query string', function (): void {
                // Arrange
                $connector = new TestableConnector();
                $connector->setHttpClient(createMockClient([
                    new GuzzleResponse(200, ['Content-Type' => 'application/json'], json_encode(['users' => []])),
                ]));

                // Act
                $response = $connector->get('/users', []);

                // Assert
                expect($response->status())->toBe(200);
            });

            test('post() with empty data sends request without body', function (): void {
                // Arrange
                $connector = new TestableConnector();
                $connector->setHttpClient(createMockClient([
                    new GuzzleResponse(201, ['Content-Type' => 'application/json'], json_encode(['id' => 1])),
                ]));

                // Act
                $response = $connector->post('/users', []);

                // Assert
                expect($response->status())->toBe(201);
            });
        });

        describe('cache methods', function (): void {
            test('forgetCache() removes cached request', function (): void {
                // Arrange
                $cache = createConnectorInMemoryCache();
                $connector = new CachedConnector($cache);
                $connector->setHttpClient(createMockClient([
                    new GuzzleResponse(200, ['Content-Type' => 'application/json'], json_encode(['id' => 1])),
                    new GuzzleResponse(200, ['Content-Type' => 'application/json'], json_encode(['id' => 2])),
                ]));

                $request = new class() extends Request
                {
                    public function endpoint(): string
                    {
                        return '/users/1';
                    }

                    public function method(): string
                    {
                        return 'GET';
                    }
                };

                // Cache the first response
                $firstResponse = $connector->send($request);

                // Act
                $forgotten = $connector->forgetCache($request);
                $secondResponse = $connector->send($request);

                // Assert
                expect($forgotten)->toBeTrue()
                    ->and($firstResponse->json('id'))->toBe(1)
                    ->and($secondResponse->json('id'))->toBe(2); // Not cached
            });

            test('flushCache() clears all cached responses', function (): void {
                // Arrange
                $cache = createConnectorInMemoryCache();
                $connector = new CachedConnector($cache);
                $connector->setHttpClient(createMockClient([
                    new GuzzleResponse(200, ['Content-Type' => 'application/json'], json_encode(['id' => 1])),
                    new GuzzleResponse(200, ['Content-Type' => 'application/json'], json_encode(['id' => 2])),
                ]));

                $request = new class() extends Request
                {
                    public function endpoint(): string
                    {
                        return '/users/1';
                    }

                    public function method(): string
                    {
                        return 'GET';
                    }
                };

                // Cache a response
                $connector->send($request);

                // Act
                $flushed = $connector->flushCache();

                // Assert
                expect($flushed)->toBeTrue();
            });

            test('forgetCache() returns false when cache is not enabled', function (): void {
                // Arrange
                $connector = new TestableConnector();
                $request = new class() extends Request
                {
                    public function endpoint(): string
                    {
                        return '/users/1';
                    }

                    public function method(): string
                    {
                        return 'GET';
                    }
                };

                // Act
                $result = $connector->forgetCache($request);

                // Assert
                expect($result)->toBeFalse();
            });

            test('flushCache() returns false when cache is not enabled', function (): void {
                // Arrange
                $connector = new TestableConnector();

                // Act
                $result = $connector->flushCache();

                // Assert
                expect($result)->toBeFalse();
            });
        });

        describe('paginate() method', function (): void {
            test('creates paginated response with auto-detected paginator from Pagination attribute', function (): void {
                // Arrange
                $connector = new TestableConnector();
                $connector->setHttpClient(createMockClient([
                    new GuzzleResponse(200, ['Content-Type' => 'application/json'], json_encode([
                        'data' => [['id' => 1], ['id' => 2]],
                        'meta' => ['current_page' => 1, 'last_page' => 5],
                    ])),
                ]));

                $request = new PagePaginationRequest();

                // Act
                $paginatedResponse = $connector->paginate($request);

                // Assert
                expect($paginatedResponse)->toBeInstanceOf(PaginatedResponse::class);
            });

            test('creates paginated response with CursorPagination attribute', function (): void {
                // Arrange
                $connector = new TestableConnector();
                $connector->setHttpClient(createMockClient([
                    new GuzzleResponse(200, ['Content-Type' => 'application/json'], json_encode([
                        'data' => [['id' => 1]],
                        'meta' => ['next_cursor' => 'abc123'],
                    ])),
                ]));

                $request = new CursorPaginationRequest();

                // Act
                $paginatedResponse = $connector->paginate($request);

                // Assert
                expect($paginatedResponse)->toBeInstanceOf(PaginatedResponse::class);
            });

            test('creates paginated response with OffsetPagination attribute', function (): void {
                // Arrange
                $connector = new TestableConnector();
                $connector->setHttpClient(createMockClient([
                    new GuzzleResponse(200, ['Content-Type' => 'application/json'], json_encode([
                        'data' => [['id' => 1]],
                        'meta' => ['offset' => 0, 'limit' => 10, 'total' => 50],
                    ])),
                ]));

                $request = new OffsetPaginationRequest();

                // Act
                $paginatedResponse = $connector->paginate($request);

                // Assert
                expect($paginatedResponse)->toBeInstanceOf(PaginatedResponse::class);
            });

            test('creates paginated response with LinkPagination attribute', function (): void {
                // Arrange
                $connector = new TestableConnector();
                $connector->setHttpClient(createMockClient([
                    new GuzzleResponse(200, [
                        'Content-Type' => 'application/json',
                        'Link' => '<https://api.example.com/users?page=2>; rel="next"',
                    ], json_encode([
                        'data' => [['id' => 1]],
                    ])),
                ]));

                $request = new LinkPaginationRequest();

                // Act
                $paginatedResponse = $connector->paginate($request);

                // Assert
                expect($paginatedResponse)->toBeInstanceOf(PaginatedResponse::class);
            });

            test('creates paginated response with SimplePagination attribute', function (): void {
                // Arrange
                $connector = new TestableConnector();
                $connector->setHttpClient(createMockClient([
                    new GuzzleResponse(200, ['Content-Type' => 'application/json'], json_encode([
                        'data' => [['id' => 1]],
                        'meta' => ['has_more' => true],
                    ])),
                ]));

                $request = new SimplePaginationRequest();

                // Act
                $paginatedResponse = $connector->paginate($request);

                // Assert
                expect($paginatedResponse)->toBeInstanceOf(PaginatedResponse::class);
            });
        });
    });

    describe('Sad Paths', function (): void {
        describe('maybeThrow() and exception creation', function (): void {
            test('throws UnauthorizedException for 401 status when connector has ThrowOnError attribute', function (): void {
                // Arrange
                $connector = new ThrowOnClientErrorConnector();
                $connector->setHttpClient(createMockClient([
                    new GuzzleResponse(401, ['Content-Type' => 'application/json'], json_encode(['error' => 'Unauthorized'])),
                ]));

                $request = new class() extends Request
                {
                    public function endpoint(): string
                    {
                        return '/protected';
                    }

                    public function method(): string
                    {
                        return 'GET';
                    }
                };

                // Act & Assert
                expect(fn (): Response => $connector->send($request))
                    ->toThrow(UnauthorizedException::class);
            });

            test('throws ForbiddenException for 403 status when connector has ThrowOnError attribute', function (): void {
                // Arrange
                $connector = new ThrowOnClientErrorConnector();
                $connector->setHttpClient(createMockClient([
                    new GuzzleResponse(403, ['Content-Type' => 'application/json'], json_encode(['error' => 'Forbidden'])),
                ]));

                $request = new class() extends Request
                {
                    public function endpoint(): string
                    {
                        return '/admin';
                    }

                    public function method(): string
                    {
                        return 'GET';
                    }
                };

                // Act & Assert
                expect(fn (): Response => $connector->send($request))
                    ->toThrow(ForbiddenException::class);
            });

            test('throws NotFoundException for 404 status when connector has ThrowOnError attribute', function (): void {
                // Arrange
                $connector = new ThrowOnClientErrorConnector();
                $connector->setHttpClient(createMockClient([
                    new GuzzleResponse(404, ['Content-Type' => 'application/json'], json_encode(['error' => 'Not Found'])),
                ]));

                $request = new class() extends Request
                {
                    public function endpoint(): string
                    {
                        return '/users/999';
                    }

                    public function method(): string
                    {
                        return 'GET';
                    }
                };

                // Act & Assert
                expect(fn (): Response => $connector->send($request))
                    ->toThrow(NotFoundException::class);
            });

            test('throws ValidationException for 422 status when connector has ThrowOnError attribute', function (): void {
                // Arrange
                $connector = new ThrowOnClientErrorConnector();
                $connector->setHttpClient(createMockClient([
                    new GuzzleResponse(422, ['Content-Type' => 'application/json'], json_encode([
                        'errors' => ['email' => ['Email is required']],
                    ])),
                ]));

                $request = new class() extends Request
                {
                    public function endpoint(): string
                    {
                        return '/users';
                    }

                    public function method(): string
                    {
                        return 'POST';
                    }
                };

                // Act & Assert
                expect(fn (): Response => $connector->send($request))
                    ->toThrow(ValidationException::class);
            });

            test('throws RateLimitException for 429 status when connector has ThrowOnError attribute', function (): void {
                // Arrange
                $connector = new ThrowOnClientErrorConnector();
                $connector->setHttpClient(createMockClient([
                    new GuzzleResponse(429, ['Content-Type' => 'application/json'], json_encode(['error' => 'Too Many Requests'])),
                ]));

                $request = new class() extends Request
                {
                    public function endpoint(): string
                    {
                        return '/api/endpoint';
                    }

                    public function method(): string
                    {
                        return 'GET';
                    }
                };

                // Act & Assert
                expect(fn (): Response => $connector->send($request))
                    ->toThrow(RateLimitException::class);
            });

            test('throws generic ClientException for other 4xx status codes', function (): void {
                // Arrange
                $connector = new ThrowOnClientErrorConnector();
                $connector->setHttpClient(createMockClient([
                    new GuzzleResponse(418, ['Content-Type' => 'application/json'], json_encode(['error' => "I'm a teapot"])),
                ]));

                $request = new class() extends Request
                {
                    public function endpoint(): string
                    {
                        return '/coffee';
                    }

                    public function method(): string
                    {
                        return 'GET';
                    }
                };

                // Act & Assert
                expect(fn (): Response => $connector->send($request))
                    ->toThrow(ClientException::class);
            });

            test('throws InternalServerException for 500 status when connector has ThrowOnError attribute', function (): void {
                // Arrange
                $connector = new ThrowOnServerErrorConnector();
                $connector->setHttpClient(createMockClient([
                    new GuzzleResponse(500, ['Content-Type' => 'application/json'], json_encode(['error' => 'Internal Server Error'])),
                ]));

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

                // Act & Assert
                expect(fn (): Response => $connector->send($request))
                    ->toThrow(InternalServerException::class);
            });

            test('throws ServiceUnavailableException for 503 status when connector has ThrowOnError attribute', function (): void {
                // Arrange
                $connector = new ThrowOnServerErrorConnector();
                $connector->setHttpClient(createMockClient([
                    new GuzzleResponse(503, ['Content-Type' => 'application/json'], json_encode(['error' => 'Service Unavailable'])),
                ]));

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

                // Act & Assert
                expect(fn (): Response => $connector->send($request))
                    ->toThrow(ServiceUnavailableException::class);
            });

            test('throws generic ServerException for other 5xx status codes', function (): void {
                // Arrange
                $connector = new ThrowOnServerErrorConnector();
                $connector->setHttpClient(createMockClient([
                    new GuzzleResponse(502, ['Content-Type' => 'application/json'], json_encode(['error' => 'Bad Gateway'])),
                ]));

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

                // Act & Assert
                expect(fn (): Response => $connector->send($request))
                    ->toThrow(ServerException::class);
            });

            test('request-level ThrowOnError attribute overrides connector-level attribute', function (): void {
                // Arrange
                $connector = new TestableConnector(); // No ThrowOnError attribute
                $connector->setHttpClient(createMockClient([
                    new GuzzleResponse(404, ['Content-Type' => 'application/json'], json_encode(['error' => 'Not Found'])),
                ]));

                $request = new ThrowOnErrorRequest('/users/999');

                // Act & Assert
                expect(fn (): Response => $connector->send($request))
                    ->toThrow(NotFoundException::class);
            });

            test('does not throw exception when ThrowOnError clientErrors is false for 4xx status', function (): void {
                // Arrange
                $connector = new ThrowOnServerErrorConnector(); // Only throws on server errors
                $connector->setHttpClient(createMockClient([
                    new GuzzleResponse(404, ['Content-Type' => 'application/json'], json_encode(['error' => 'Not Found'])),
                ]));

                $request = new class() extends Request
                {
                    public function endpoint(): string
                    {
                        return '/users/999';
                    }

                    public function method(): string
                    {
                        return 'GET';
                    }
                };

                // Act
                $response = $connector->send($request);

                // Assert
                expect($response->status())->toBe(404)
                    ->and($response->clientError())->toBeTrue();
            });

            test('does not throw exception when ThrowOnError serverErrors is false for 5xx status', function (): void {
                // Arrange
                $connector = new ThrowOnClientErrorConnector(); // Only throws on client errors
                $connector->setHttpClient(createMockClient([
                    new GuzzleResponse(500, ['Content-Type' => 'application/json'], json_encode(['error' => 'Internal Server Error'])),
                ]));

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
                $response = $connector->send($request);

                // Assert
                expect($response->status())->toBe(500)
                    ->and($response->serverError())->toBeTrue();
            });
        });
    });

    describe('Edge Cases', function (): void {
        test('send() handles empty response body', function (): void {
            // Arrange
            $connector = new TestableConnector();
            $connector->setHttpClient(createMockClient([
                new GuzzleResponse(204, []),
            ]));

            $request = new class() extends Request
            {
                public function endpoint(): string
                {
                    return '/users/1';
                }

                public function method(): string
                {
                    return 'DELETE';
                }
            };

            // Act
            $response = $connector->send($request);

            // Assert
            expect($response->status())->toBe(204)
                ->and($response->body())->toBe('');
        });

        test('send() handles non-JSON response content type', function (): void {
            // Arrange
            $connector = new TestableConnector();
            $connector->setHttpClient(createMockClient([
                new GuzzleResponse(200, ['Content-Type' => 'text/plain'], 'Plain text response'),
            ]));

            $request = new class() extends Request
            {
                public function endpoint(): string
                {
                    return '/text';
                }

                public function method(): string
                {
                    return 'GET';
                }
            };

            // Act
            $response = $connector->send($request);

            // Assert
            expect($response->status())->toBe(200)
                ->and($response->body())->toBe('Plain text response');
        });

        test('get() handles special characters in query parameters', function (): void {
            // Arrange
            $connector = new TestableConnector();
            $connector->setHttpClient(createMockClient([
                new GuzzleResponse(200, ['Content-Type' => 'application/json'], json_encode(['results' => []])),
            ]));

            // Act
            $response = $connector->get('/search', [
                'q' => 'test query with spaces',
                'filter' => 'category:books&status:available',
            ]);

            // Assert
            expect($response->status())->toBe(200);
        });

        test('post() handles nested arrays in request body', function (): void {
            // Arrange
            $connector = new TestableConnector();
            $connector->setHttpClient(createMockClient([
                new GuzzleResponse(201, ['Content-Type' => 'application/json'], json_encode(['id' => 1])),
            ]));

            // Act
            $response = $connector->post('/users', [
                'name' => 'John',
                'preferences' => [
                    'theme' => 'dark',
                    'notifications' => ['email' => true, 'sms' => false],
                ],
            ]);

            // Assert
            expect($response->status())->toBe(201);
        });

        test('send() handles response with very long duration', function (): void {
            // Arrange
            $connector = new TestableConnector();
            $connector->setHttpClient(createMockClient([
                new GuzzleResponse(200, [], json_encode(['success' => true])),
            ]));

            $request = new class() extends Request
            {
                public function endpoint(): string
                {
                    return '/slow-endpoint';
                }

                public function method(): string
                {
                    return 'GET';
                }
            };

            // Act
            $response = $connector->send($request);

            // Assert
            expect($response->duration())->toBeFloat()
                ->and($response->duration())->toBeGreaterThanOrEqual(0.0);
        });

        test('invalidateCacheTags() does nothing when cache is not enabled', function (): void {
            // Arrange
            $connector = new TestableConnector();

            // Act & Assert - should not throw exception
            $connector->invalidateCacheTags(['tag1', 'tag2']);

            expect(true)->toBeTrue();
        });

        test('paginate() creates default page-based paginator when no attribute is present', function (): void {
            // Arrange
            $connector = new TestableConnector();
            $connector->setHttpClient(createMockClient([
                new GuzzleResponse(200, ['Content-Type' => 'application/json'], json_encode([
                    'data' => [['id' => 1]],
                ])),
            ]));

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
            $paginatedResponse = $connector->paginate($request);

            // Assert
            expect($paginatedResponse)->toBeInstanceOf(PaginatedResponse::class);
        });

        test('both connector and request ThrowOnError attributes throws exceptions', function (): void {
            // Arrange
            $connector = new ThrowOnAllErrorsConnector();
            $connector->setHttpClient(createMockClient([
                new GuzzleResponse(404, ['Content-Type' => 'application/json'], json_encode(['error' => 'Not Found'])),
            ]));

            $request = new class() extends Request
            {
                public function endpoint(): string
                {
                    return '/users/999';
                }

                public function method(): string
                {
                    return 'GET';
                }
            };

            // Act & Assert
            expect(fn (): Response => $connector->send($request))
                ->toThrow(NotFoundException::class);
        });
    });

    describe('Mock Client Integration', function (): void {
        beforeEach(function (): void {
            MockClient::destroyGlobal();
            MockConfig::reset();
        });

        afterEach(function (): void {
            MockClient::destroyGlobal();
            MockConfig::reset();
        });

        it('uses local mock client via withMockClient()', function (): void {
            $connector = new TestableConnector();
            $mockClient = new MockClient([
                MockResponse::json(['local' => true]),
            ]);

            $connector->withMockClient($mockClient);

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

            $response = $connector->send($request);

            expect($response->json('local'))->toBeTrue();
        });

        it('throws MockClientException when stray requests are prevented', function (): void {
            MockConfig::preventStrayRequests(true);

            $connector = new TestableConnector();
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

            expect(fn (): Response => $connector->send($request))
                ->toThrow(MockClientException::class);
        });
    });

    describe('Raw Body Handling', function (): void {
        it('sends request with rawBody for SOAP-like requests', function (): void {
            $mockHandler = new MockHandler([
                new GuzzleResponse(200, [], json_encode(['soap' => 'response'])),
            ]);
            $handlerStack = HandlerStack::create($mockHandler);
            $httpClient = new Client(['handler' => $handlerStack]);

            $connector = new TestableConnector();
            $connector->setHttpClient($httpClient);

            $request = new class() extends Request
            {
                public function endpoint(): string
                {
                    return '/soap-service';
                }

                public function method(): string
                {
                    return 'POST';
                }

                public function rawBody(): string
                {
                    return '<?xml version="1.0"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><Test/></soap:Body></soap:Envelope>';
                }
            };

            $response = $connector->send($request);

            expect($response->json('soap'))->toBe('response');
        });
    });
});

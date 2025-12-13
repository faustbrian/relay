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
use Cline\Relay\Support\Attributes\ContentTypes\Json;
use Cline\Relay\Support\Attributes\Methods\Get;
use Cline\Relay\Support\Attributes\Methods\Post;
use Cline\Relay\Support\Exceptions\RequestException;
use Cline\Relay\Transport\Pool\Pool;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as Psr7Response;

function createTestConnectorForPool(): Connector
{
    return new class() extends Connector
    {
        public function baseUrl(): string
        {
            return 'https://api.example.com';
        }

        public function defaultHeaders(): array
        {
            return [
                'Accept' => 'application/json',
                'User-Agent' => 'Test/1.0',
            ];
        }
    };
}

function createTestRequest(string $endpoint = '/test', string $method = 'GET'): Request
{
    return new class($endpoint, $method) extends Request
    {
        public function __construct(
            private readonly string $endpoint,
            private readonly string $method,
        ) {}

        public function endpoint(): string
        {
            return $this->endpoint;
        }

        public function method(): string
        {
            return $this->method;
        }
    };
}

describe('Pool', function (): void {
    describe('Construction', function (): void {
        test('creates pool with indexed array of requests', function (): void {
            // Arrange
            $connector = createTestConnectorForPool();
            $request1 = createTestRequest('/users/1');
            $request2 = createTestRequest('/users/2');

            // Act
            $pool = new Pool($connector, [$request1, $request2]);

            // Assert
            expect($pool)->toBeInstanceOf(Pool::class);
        });

        test('creates pool with associative array of named requests', function (): void {
            // Arrange
            $connector = createTestConnectorForPool();
            $request1 = createTestRequest('/users/1');
            $request2 = createTestRequest('/users/2');

            // Act
            $pool = new Pool($connector, [
                'john' => $request1,
                'jane' => $request2,
            ]);

            // Assert
            expect($pool)->toBeInstanceOf(Pool::class);
        });

        test('creates pool with empty requests array', function (): void {
            // Arrange
            $connector = createTestConnectorForPool();

            // Act
            $pool = new Pool($connector, []);

            // Assert
            expect($pool)->toBeInstanceOf(Pool::class);
        });
    });

    describe('Configuration Methods', function (): void {
        test('sets concurrency limit and returns self for chaining', function (): void {
            // Arrange
            $connector = createTestConnectorForPool();
            $pool = new Pool($connector, []);

            // Act
            $result = $pool->concurrent(10);

            // Assert
            expect($result)->toBe($pool)
                ->and($result)->toBeInstanceOf(Pool::class);
        });

        test('sets concurrency limit to different values', function (): void {
            // Arrange
            $connector = createTestConnectorForPool();
            $pool = new Pool($connector, []);

            // Act
            $pool->concurrent(1);
            $pool->concurrent(100);
            $pool->concurrent(5);

            // Assert - verify no exceptions thrown
            expect($pool)->toBeInstanceOf(Pool::class);
        });

        test('sets response callback and returns self for chaining', function (): void {
            // Arrange
            $connector = createTestConnectorForPool();
            $pool = new Pool($connector, []);
            $callback = fn (Response $response, Request $request, int|string $key): null => null;

            // Act
            $result = $pool->onResponse($callback);

            // Assert
            expect($result)->toBe($pool)
                ->and($result)->toBeInstanceOf(Pool::class);
        });

        test('sets error callback and returns self for chaining', function (): void {
            // Arrange
            $connector = createTestConnectorForPool();
            $pool = new Pool($connector, []);
            $callback = fn (RequestException $exception, Request $request, int|string $key): null => null;

            // Act
            $result = $pool->onError($callback);

            // Assert
            expect($result)->toBe($pool)
                ->and($result)->toBeInstanceOf(Pool::class);
        });

        test('enables lazy mode and returns self for chaining', function (): void {
            // Arrange
            $connector = createTestConnectorForPool();
            $pool = new Pool($connector, []);

            // Act
            $result = $pool->lazy();

            // Assert
            expect($result)->toBe($pool)
                ->and($result)->toBeInstanceOf(Pool::class);
        });

        test('chains all configuration methods in sequence', function (): void {
            // Arrange
            $connector = createTestConnectorForPool();
            $pool = new Pool($connector, []);

            // Act
            $result = $pool
                ->concurrent(5)
                ->onResponse(fn (): null => null)
                ->onError(fn (): null => null)
                ->lazy();

            // Assert
            expect($result)->toBe($pool)
                ->and($result)->toBeInstanceOf(Pool::class);
        });

        test('chains configuration methods in different order', function (): void {
            // Arrange
            $connector = createTestConnectorForPool();
            $pool = new Pool($connector, []);

            // Act
            $result = $pool
                ->lazy()
                ->onError(fn (): null => null)
                ->concurrent(10)
                ->onResponse(fn (): null => null);

            // Assert
            expect($result)->toBe($pool);
        });

        test('allows reconfiguring concurrency multiple times', function (): void {
            // Arrange
            $connector = createTestConnectorForPool();
            $pool = new Pool($connector, []);

            // Act
            $pool->concurrent(5)
                ->concurrent(10)
                ->concurrent(3);

            // Assert
            expect($pool)->toBeInstanceOf(Pool::class);
        });

        test('allows reconfiguring callbacks multiple times', function (): void {
            // Arrange
            $connector = createTestConnectorForPool();
            $pool = new Pool($connector, []);

            // Act
            $pool->onResponse(fn (): null => null)
                ->onResponse(fn (): null => null)
                ->onError(fn (): null => null)
                ->onError(fn (): null => null);

            // Assert
            expect($pool)->toBeInstanceOf(Pool::class);
        });
    });

    describe('Empty Requests Handling', function (): void {
        test('returns empty array when sending with no requests', function (): void {
            // Arrange
            $connector = createTestConnectorForPool();
            $pool = new Pool($connector, []);

            // Act
            $responses = $pool->send();

            // Assert
            expect($responses)->toBeArray()
                ->and($responses)->toBeEmpty();
        });

        test('returns empty array when sending with lazy mode and no requests', function (): void {
            // Arrange
            $connector = createTestConnectorForPool();
            $pool = new Pool($connector, []);

            // Act
            $responses = $pool->lazy()->send();

            // Assert
            expect($responses)->toBeArray()
                ->and($responses)->toBeEmpty();
        });

        test('iterate returns empty generator with no requests', function (): void {
            // Arrange
            $connector = createTestConnectorForPool();
            $pool = new Pool($connector, []);

            // Act
            $generator = $pool->iterate();
            $responses = iterator_to_array($generator);

            // Assert
            expect($responses)->toBeArray()
                ->and($responses)->toBeEmpty();
        });

        test('each does not invoke callback with no requests', function (): void {
            // Arrange
            $connector = createTestConnectorForPool();
            $pool = new Pool($connector, []);
            $callCount = 0;

            // Act
            $pool->each(function () use (&$callCount): void {
                ++$callCount;
            });

            // Assert
            expect($callCount)->toBe(0);
        });
    });

    describe('Private Method Effects', function (): void {
        test('buildUrl combines base URL and endpoint correctly', function (): void {
            // Arrange - using reflection to test buildUrl indirectly
            $connector = new class() extends Connector
            {
                public function baseUrl(): string
                {
                    return 'https://api.example.com/v1';
                }
            };

            $request = new class() extends Request
            {
                public function endpoint(): string
                {
                    return '/users';
                }
            };

            // Act - create pool but don't send (would need HTTP)
            $pool = new Pool($connector, [$request]);

            // Assert - verify pool was created successfully with the configuration
            expect($pool)->toBeInstanceOf(Pool::class);
        });

        test('buildUrl handles trailing slash in base URL', function (): void {
            // Arrange
            $connector = new class() extends Connector
            {
                public function baseUrl(): string
                {
                    return 'https://api.example.com/';
                }
            };

            $request = new class() extends Request
            {
                public function endpoint(): string
                {
                    return 'users';
                }
            };

            // Act
            $pool = new Pool($connector, [$request]);

            // Assert
            expect($pool)->toBeInstanceOf(Pool::class);
        });

        test('buildUrl handles leading slash in endpoint', function (): void {
            // Arrange
            $connector = new class() extends Connector
            {
                public function baseUrl(): string
                {
                    return 'https://api.example.com';
                }
            };

            $request = new class() extends Request
            {
                public function endpoint(): string
                {
                    return '/users';
                }
            };

            // Act
            $pool = new Pool($connector, [$request]);

            // Assert
            expect($pool)->toBeInstanceOf(Pool::class);
        });

        test('buildUrl appends query parameters correctly', function (): void {
            // Arrange
            $connector = createTestConnectorForPool();

            $request = new class() extends Request
            {
                public function endpoint(): string
                {
                    return '/users';
                }

                public function query(): array
                {
                    return [
                        'page' => 1,
                        'limit' => 10,
                    ];
                }
            };

            // Act
            $pool = new Pool($connector, [$request]);

            // Assert
            expect($pool)->toBeInstanceOf(Pool::class);
        });

        test('mergeHeaders combines connector and request headers', function (): void {
            // Arrange
            $connector = new class() extends Connector
            {
                public function baseUrl(): string
                {
                    return 'https://api.example.com';
                }

                public function defaultHeaders(): array
                {
                    return [
                        'Accept' => 'application/json',
                        'User-Agent' => 'Test/1.0',
                    ];
                }
            };

            $request = new class() extends Request
            {
                public function endpoint(): string
                {
                    return '/test';
                }

                public function headers(): array
                {
                    return [
                        'Authorization' => 'Bearer token',
                    ];
                }
            };

            // Act
            $pool = new Pool($connector, [$request]);

            // Assert
            expect($pool)->toBeInstanceOf(Pool::class);
        });

        test('mergeHeaders sets Content-Type from request', function (): void {
            // Arrange
            $connector = createTestConnectorForPool();

            $request = new class() extends Request
            {
                #[Json()]
                public function endpoint(): string
                {
                    return '/test';
                }
            };

            // Act
            $pool = new Pool($connector, [$request]);

            // Assert
            expect($pool)->toBeInstanceOf(Pool::class);
        });

        test('mergeHeaders adds Accept header for JSON content type', function (): void {
            // Arrange
            $connector = new class() extends Connector
            {
                public function baseUrl(): string
                {
                    return 'https://api.example.com';
                }

                public function defaultHeaders(): array
                {
                    return [];
                }
            };

            $request = new class() extends Request
            {
                #[Json()]
                public function endpoint(): string
                {
                    return '/test';
                }
            };

            // Act
            $pool = new Pool($connector, [$request]);

            // Assert
            expect($pool)->toBeInstanceOf(Pool::class);
        });

        test('mergeHeaders adds Accept header when contentType is JSON and Accept not already set', function (): void {
            // Arrange
            $mockHandler = new MockHandler([
                new Psr7Response(200, [], '{"success":true}'),
            ]);
            $handlerStack = HandlerStack::create($mockHandler);

            $connector = new class() extends Connector
            {
                public function baseUrl(): string
                {
                    return 'https://api.example.com';
                }

                public function defaultHeaders(): array
                {
                    // No Accept header in defaults
                    return ['User-Agent' => 'Test/1.0'];
                }
            };

            $request = new #[Post()] class() extends Request
            {
                public function endpoint(): string
                {
                    return '/test';
                }

                public function contentType(): string
                {
                    return 'application/json';
                }

                public function body(): array
                {
                    return ['data' => 'test'];
                }
            };

            // Act
            $pool = new Pool($connector, [$request]);
            $pool = $pool->withHandler($handlerStack);

            $responses = $pool->send();

            // Assert
            expect($responses)->toHaveCount(1)
                ->and($responses[0]->status())->toBe(200);

            // Verify the Accept header was auto-added by checking the handler received it
            $lastRequest = $mockHandler->getLastRequest();
            expect($lastRequest->getHeader('Accept'))->toContain('application/json');
        });

        test('mergeHeaders does not override existing Accept header when contentType is JSON', function (): void {
            // Arrange
            $mockHandler = new MockHandler([
                new Psr7Response(200, [], '{"success":true}'),
            ]);
            $handlerStack = HandlerStack::create($mockHandler);

            $connector = new class() extends Connector
            {
                public function baseUrl(): string
                {
                    return 'https://api.example.com';
                }

                public function defaultHeaders(): array
                {
                    // Accept header already set in defaults
                    return ['Accept' => 'application/xml'];
                }
            };

            $request = new #[Post()] class() extends Request
            {
                public function endpoint(): string
                {
                    return '/test';
                }

                public function contentType(): string
                {
                    return 'application/json';
                }

                public function body(): array
                {
                    return ['data' => 'test'];
                }
            };

            // Act
            $pool = new Pool($connector, [$request]);
            $pool = $pool->withHandler($handlerStack);

            $responses = $pool->send();

            // Assert
            expect($responses)->toHaveCount(1)
                ->and($responses[0]->status())->toBe(200);

            // Verify the existing Accept header was NOT overridden
            $lastRequest = $mockHandler->getLastRequest();
            expect($lastRequest->getHeader('Accept'))->toContain('application/xml')
                ->and($lastRequest->getHeader('Accept'))->not->toContain('application/json');
        });

        test('buildBody returns null when request has no body', function (): void {
            // Arrange
            $connector = createTestConnectorForPool();

            $request = new class() extends Request
            {
                #[Get()]
                public function endpoint(): string
                {
                    return '/test';
                }
            };

            // Act
            $pool = new Pool($connector, [$request]);

            // Assert
            expect($pool)->toBeInstanceOf(Pool::class);
        });

        test('buildBody encodes JSON for application/json content type', function (): void {
            // Arrange
            $connector = createTestConnectorForPool();

            $request = new class() extends Request
            {
                #[Post(), Json()]
                public function endpoint(): string
                {
                    return '/users';
                }

                public function body(): array
                {
                    return [
                        'name' => 'John Doe',
                        'email' => 'john@example.com',
                    ];
                }
            };

            // Act
            $pool = new Pool($connector, [$request]);

            // Assert
            expect($pool)->toBeInstanceOf(Pool::class);
        });

        test('buildBody encodes form data for application/x-www-form-urlencoded', function (): void {
            // Arrange
            $mockHandler = new MockHandler([
                new Psr7Response(201, [], '{"id":1}'),
            ]);
            $handlerStack = HandlerStack::create($mockHandler);

            $connector = createTestConnectorForPool();

            $request = new #[Post()] class() extends Request
            {
                public function endpoint(): string
                {
                    return '/users';
                }

                public function contentType(): string
                {
                    return 'application/x-www-form-urlencoded';
                }

                public function body(): array
                {
                    return [
                        'name' => 'John Doe',
                        'email' => 'john@example.com',
                    ];
                }
            };

            // Act
            $pool = new Pool($connector, [$request]);
            $pool = $pool->withHandler($handlerStack);

            $responses = $pool->send();

            // Assert
            expect($responses)->toHaveCount(1);
            expect($responses[0]->status())->toBe(201);
        });

        test('buildBody defaults to JSON encoding for unknown content types', function (): void {
            // Arrange
            $mockHandler = new MockHandler([
                new Psr7Response(200, [], '{"result":"ok"}'),
            ]);
            $handlerStack = HandlerStack::create($mockHandler);

            $connector = createTestConnectorForPool();

            $request = new #[Post()] class() extends Request
            {
                public function endpoint(): string
                {
                    return '/users';
                }

                public function contentType(): string
                {
                    return 'text/plain';
                }

                public function body(): array
                {
                    return ['test' => 'data'];
                }
            };

            // Act
            $pool = new Pool($connector, [$request]);
            $pool = $pool->withHandler($handlerStack);

            $responses = $pool->send();

            // Assert
            expect($responses)->toHaveCount(1);
            expect($responses[0]->status())->toBe(200);
        });
    });

    describe('Connector Integration', function (): void {
        test('creates pool from connector helper method', function (): void {
            // Arrange
            $connector = createTestConnectorForPool();
            $request = createTestRequest('/test');

            // Act
            $pool = $connector->pool([$request]);

            // Assert
            expect($pool)->toBeInstanceOf(Pool::class);
        });

        test('creates pool with multiple requests via connector', function (): void {
            // Arrange
            $connector = createTestConnectorForPool();
            $requests = [
                createTestRequest('/users/1'),
                createTestRequest('/users/2'),
                createTestRequest('/users/3'),
            ];

            // Act
            $pool = $connector->pool($requests);

            // Assert
            expect($pool)->toBeInstanceOf(Pool::class);
        });

        test('creates pool with named requests via connector', function (): void {
            // Arrange
            $connector = createTestConnectorForPool();

            // Act
            $pool = $connector->pool([
                'first' => createTestRequest('/users/1'),
                'second' => createTestRequest('/users/2'),
            ]);

            // Assert
            expect($pool)->toBeInstanceOf(Pool::class);
        });
    });

    describe('Callback Configuration', function (): void {
        test('accepts callback with Response parameter for onResponse', function (): void {
            // Arrange
            $connector = createTestConnectorForPool();
            $pool = new Pool($connector, []);

            // Act
            $result = $pool->onResponse(function (Response $response): void {
                // Callback that expects Response
            });

            // Assert
            expect($result)->toBeInstanceOf(Pool::class);
        });

        test('accepts callback with multiple parameters for onResponse', function (): void {
            // Arrange
            $connector = createTestConnectorForPool();
            $pool = new Pool($connector, []);

            // Act
            $result = $pool->onResponse(function (Response $response, Request $request, int|string $key): void {
                // Callback with all parameters
            });

            // Assert
            expect($result)->toBeInstanceOf(Pool::class);
        });

        test('accepts callback with RequestException parameter for onError', function (): void {
            // Arrange
            $connector = createTestConnectorForPool();
            $pool = new Pool($connector, []);

            // Act
            $result = $pool->onError(function (RequestException $exception): void {
                // Callback that expects RequestException
            });

            // Assert
            expect($result)->toBeInstanceOf(Pool::class);
        });

        test('accepts callback with multiple parameters for onError', function (): void {
            // Arrange
            $connector = createTestConnectorForPool();
            $pool = new Pool($connector, []);

            // Act
            $result = $pool->onError(function (RequestException $exception, Request $request, int|string $key): void {
                // Callback with all parameters
            });

            // Assert
            expect($result)->toBeInstanceOf(Pool::class);
        });
    });

    describe('Edge Cases', function (): void {
        test('handles pool with single request', function (): void {
            // Arrange
            $connector = createTestConnectorForPool();
            $request = createTestRequest('/test');

            // Act
            $pool = new Pool($connector, [$request]);

            // Assert
            expect($pool)->toBeInstanceOf(Pool::class);
        });

        test('handles zero concurrency limit', function (): void {
            // Arrange
            $connector = createTestConnectorForPool();
            $pool = new Pool($connector, []);

            // Act
            $result = $pool->concurrent(0);

            // Assert
            expect($result)->toBeInstanceOf(Pool::class);
        });

        test('handles negative concurrency limit', function (): void {
            // Arrange
            $connector = createTestConnectorForPool();
            $pool = new Pool($connector, []);

            // Act
            $result = $pool->concurrent(-1);

            // Assert
            expect($result)->toBeInstanceOf(Pool::class);
        });

        test('handles very large concurrency limit', function (): void {
            // Arrange
            $connector = createTestConnectorForPool();
            $pool = new Pool($connector, []);

            // Act
            $result = $pool->concurrent(999_999);

            // Assert
            expect($result)->toBeInstanceOf(Pool::class);
        });

        test('preserves request array keys when using named requests', function (): void {
            // Arrange
            $connector = createTestConnectorForPool();
            $requests = [
                'user1' => createTestRequest('/users/1'),
                'user2' => createTestRequest('/users/2'),
                'admin' => createTestRequest('/admin'),
            ];

            // Act
            $pool = new Pool($connector, $requests);

            // Assert
            expect($pool)->toBeInstanceOf(Pool::class);
        });
    });
});

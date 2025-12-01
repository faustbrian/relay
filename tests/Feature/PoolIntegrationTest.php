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
use Cline\Relay\Support\Attributes\Methods\Get;
use Cline\Relay\Support\Attributes\Methods\Post;
use Cline\Relay\Transport\Pool\Pool;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as GuzzleResponse;

/**
 * Create a simple test connector.
 */
function createPoolTestConnector(): Connector
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
                'User-Agent' => 'Relay-Pool-Test/1.0',
            ];
        }

        public function timeout(): int
        {
            return 10;
        }

        public function connectTimeout(): int
        {
            return 5;
        }
    };
}

/**
 * Simple GET request for testing.
 */
function createPoolGetRequest(string $endpoint): Request
{
    return new #[Get()] class($endpoint) extends Request
    {
        public function __construct(
            private readonly string $endpoint,
        ) {}

        public function endpoint(): string
        {
            return $this->endpoint;
        }
    };
}

/**
 * GET request with query parameters.
 */
function createPoolGetRequestWithQuery(string $endpoint, array $params): Request
{
    return new #[Get()] class($endpoint, $params) extends Request
    {
        public function __construct(
            private readonly string $endpoint,
            private readonly array $params,
        ) {}

        public function endpoint(): string
        {
            return $this->endpoint;
        }

        public function query(): array
        {
            return $this->params;
        }
    };
}

/**
 * POST request with JSON body.
 */
function createPoolPostRequest(string $endpoint, array $data): Request
{
    return new #[Post()] class($endpoint, $data) extends Request
    {
        public function __construct(
            private readonly string $endpoint,
            private readonly array $data,
        ) {}

        public function endpoint(): string
        {
            return $this->endpoint;
        }

        public function contentType(): string
        {
            return 'application/json';
        }

        public function body(): array
        {
            return $this->data;
        }
    };
}

/**
 * Create a mock handler stack with responses.
 */
function createPoolMockHandler(array $responses): HandlerStack
{
    $mock = new MockHandler($responses);

    return HandlerStack::create($mock);
}

describe('PoolIntegrationTest', function (): void {
    describe('Happy Paths', function (): void {
        test('send returns array of responses', function (): void {
            // Arrange
            $connector = createPoolTestConnector();
            $request1 = createPoolGetRequest('/users');
            $request2 = createPoolGetRequest('/posts');

            $handler = createPoolMockHandler([
                new GuzzleResponse(200, ['Content-Type' => 'application/json'], json_encode(['id' => 1])),
                new GuzzleResponse(200, ['Content-Type' => 'application/json'], json_encode(['id' => 2])),
            ]);

            // Act
            $pool = new Pool($connector, [$request1, $request2]);
            $responses = $pool->withHandler($handler)->send();

            // Assert
            expect($responses)->toBeArray()
                ->and($responses)->toHaveCount(2)
                ->and($responses[0])->toBeInstanceOf(Response::class)
                ->and($responses[1])->toBeInstanceOf(Response::class)
                ->and($responses[0]->status())->toBe(200)
                ->and($responses[1]->status())->toBe(200);
        });

        test('send executes requests with configured concurrency limit', function (): void {
            // Arrange
            $connector = createPoolTestConnector();
            $requests = [
                createPoolGetRequest('/users'),
                createPoolGetRequest('/posts'),
                createPoolGetRequest('/comments'),
            ];

            $handler = createPoolMockHandler([
                new GuzzleResponse(200, [], '{"data": 1}'),
                new GuzzleResponse(200, [], '{"data": 2}'),
                new GuzzleResponse(200, [], '{"data": 3}'),
            ]);

            // Act
            $pool = new Pool($connector, $requests);
            $responses = $pool->withHandler($handler)->concurrent(2)->send();

            // Assert
            expect($responses)->toHaveCount(3);

            foreach ($responses as $response) {
                expect($response)->toBeInstanceOf(Response::class)
                    ->and($response->status())->toBe(200);
            }
        });

        test('lazy mode returns iterator results converted to array', function (): void {
            // Arrange
            $connector = createPoolTestConnector();
            $request1 = createPoolGetRequest('/users');
            $request2 = createPoolGetRequest('/posts');

            $handler = createPoolMockHandler([
                new GuzzleResponse(200, [], '{}'),
                new GuzzleResponse(200, [], '{}'),
            ]);

            // Act
            $pool = new Pool($connector, [$request1, $request2]);
            $responses = $pool->withHandler($handler)->lazy()->send();

            // Assert
            expect($responses)->toBeArray()
                ->and($responses)->toHaveCount(2);
        });

        test('keyed requests preserve response keys', function (): void {
            // Arrange
            $connector = createPoolTestConnector();
            $requests = [
                'first' => createPoolGetRequest('/users'),
                'second' => createPoolGetRequest('/posts'),
            ];

            $handler = createPoolMockHandler([
                new GuzzleResponse(200, [], '{}'),
                new GuzzleResponse(200, [], '{}'),
            ]);

            // Act
            $pool = new Pool($connector, $requests);
            $responses = $pool->withHandler($handler)->send();

            // Assert
            expect($responses)->toHaveKey('first')
                ->and($responses)->toHaveKey('second')
                ->and($responses['first'])->toBeInstanceOf(Response::class)
                ->and($responses['second'])->toBeInstanceOf(Response::class);
        });

        test('onResponse callback receives response for each successful request', function (): void {
            // Arrange
            $connector = createPoolTestConnector();
            $requests = [
                createPoolGetRequest('/users'),
                createPoolGetRequest('/posts'),
            ];

            $handler = createPoolMockHandler([
                new GuzzleResponse(200, [], '{}'),
                new GuzzleResponse(201, [], '{}'),
            ]);

            $callbackData = [];

            // Act
            $pool = new Pool($connector, $requests);
            $pool->withHandler($handler)->onResponse(function ($response, $request, $key) use (&$callbackData): void {
                $callbackData[] = [
                    'key' => $key,
                    'status' => $response->status(),
                ];
            })->send();

            // Assert
            expect($callbackData)->toHaveCount(2);

            $keys = array_column($callbackData, 'key');
            $statuses = array_column($callbackData, 'status');

            expect($keys)->toContain(0)
                ->and($keys)->toContain(1)
                ->and($statuses)->toContain(200)
                ->and($statuses)->toContain(201);
        });

        test('buildUrl combines base URL and endpoint with query parameters', function (): void {
            // Arrange
            $connector = createPoolTestConnector();
            $request = createPoolGetRequestWithQuery('/users', ['page' => 1, 'limit' => 10]);

            $handler = createPoolMockHandler([
                new GuzzleResponse(200, ['Content-Type' => 'application/json'], json_encode([
                    'args' => ['page' => '1', 'limit' => '10'],
                ])),
            ]);

            // Act
            $pool = new Pool($connector, [$request]);
            $responses = $pool->withHandler($handler)->send();

            // Assert
            expect($responses)->toHaveCount(1)
                ->and($responses[0]->status())->toBe(200);
        });

        test('buildBody encodes JSON for POST requests', function (): void {
            // Arrange
            $connector = createPoolTestConnector();
            $request = createPoolPostRequest('/users', ['name' => 'John', 'email' => 'john@example.com']);

            $handler = createPoolMockHandler([
                new GuzzleResponse(201, ['Content-Type' => 'application/json'], json_encode([
                    'id' => 1,
                    'name' => 'John',
                ])),
            ]);

            // Act
            $pool = new Pool($connector, [$request]);
            $responses = $pool->withHandler($handler)->send();

            // Assert
            expect($responses)->toHaveCount(1)
                ->and($responses[0]->status())->toBe(201);

            $json = $responses[0]->json();
            expect($json)->toHaveKey('id')
                ->and($json['name'])->toBe('John');
        });
    });

    describe('Edge Cases', function (): void {
        test('iterate yields responses as generator', function (): void {
            // Arrange
            $connector = createPoolTestConnector();
            $requests = [
                createPoolGetRequest('/users'),
                createPoolGetRequest('/posts'),
            ];

            $handler = createPoolMockHandler([
                new GuzzleResponse(200, [], '{}'),
                new GuzzleResponse(200, [], '{}'),
            ]);

            // Act
            $pool = new Pool($connector, $requests);
            $generator = $pool->withHandler($handler)->iterate();

            // Assert
            expect($generator)->toBeInstanceOf(Generator::class);

            $count = 0;

            foreach ($generator as $response) {
                expect($response)->toBeInstanceOf(Response::class);
                ++$count;
            }

            expect($count)->toBe(2);
        });

        test('each calls callback for each response', function (): void {
            // Arrange
            $connector = createPoolTestConnector();
            $requests = [
                createPoolGetRequest('/users'),
                createPoolGetRequest('/posts'),
                createPoolGetRequest('/comments'),
            ];

            $handler = createPoolMockHandler([
                new GuzzleResponse(200, [], '{}'),
                new GuzzleResponse(200, [], '{}'),
                new GuzzleResponse(200, [], '{}'),
            ]);

            $callCount = 0;

            // Act
            $pool = new Pool($connector, $requests);
            $pool->withHandler($handler)->each(function ($response, $key) use (&$callCount): void {
                ++$callCount;
                expect($response)->toBeInstanceOf(Response::class);
            });

            // Assert
            expect($callCount)->toBe(3);
        });

        test('handles multiple requests with high concurrency', function (): void {
            // Arrange
            $connector = createPoolTestConnector();
            $requests = [
                createPoolGetRequest('/users'),
                createPoolGetRequest('/posts'),
                createPoolGetRequest('/comments'),
                createPoolGetRequest('/albums'),
                createPoolGetRequest('/photos'),
            ];

            $handler = createPoolMockHandler([
                new GuzzleResponse(200, [], '{"id": 1}'),
                new GuzzleResponse(200, [], '{"id": 2}'),
                new GuzzleResponse(200, [], '{"id": 3}'),
                new GuzzleResponse(200, [], '{"id": 4}'),
                new GuzzleResponse(200, [], '{"id": 5}'),
            ]);

            // Act
            $pool = new Pool($connector, $requests);
            $responses = $pool->withHandler($handler)->concurrent(3)->send();

            // Assert
            expect($responses)->toHaveCount(5);

            foreach ($responses as $response) {
                expect($response->status())->toBe(200);
            }
        });

        test('connector authentication is called for each request in pool', function (): void {
            // Arrange
            $authCallCount = new stdClass();
            $authCallCount->value = 0;

            $connector = new class($authCallCount) extends Connector
            {
                public function __construct(
                    private readonly stdClass $authCallCount,
                ) {}

                public function baseUrl(): string
                {
                    return 'https://api.example.com';
                }

                public function authenticate(Request $request): void
                {
                    ++$this->authCallCount->value;
                }
            };

            $requests = [
                createPoolGetRequest('/users'),
                createPoolGetRequest('/posts'),
            ];

            $handler = createPoolMockHandler([
                new GuzzleResponse(200, [], '{}'),
                new GuzzleResponse(200, [], '{}'),
            ]);

            // Act
            $pool = new Pool($connector, $requests);
            $pool->withHandler($handler)->send();

            // Assert
            expect($authCallCount->value)->toBe(2);
        });

        test('onError callback is called for failed requests', function (): void {
            // Arrange
            $connector = createPoolTestConnector();
            $requests = [
                createPoolGetRequest('/users'),
            ];

            $handler = createPoolMockHandler([
                new RequestException(
                    'Connection failed',
                    new GuzzleHttp\Psr7\Request('GET', 'https://api.example.com/users'),
                    new GuzzleResponse(500, [], 'Internal Server Error'),
                ),
            ]);

            $errorCalled = false;

            // Act
            $pool = new Pool($connector, $requests);
            $pool->withHandler($handler)->onError(function ($exception, $request, $key) use (&$errorCalled): void {
                $errorCalled = true;
            })->send();

            // Assert
            expect($errorCalled)->toBeTrue();
        });
    });

    describe('Regressions', function (): void {
        test('buildUrl handles both trailing slash in base URL and leading slash in endpoint', function (): void {
            // Arrange
            $connector = new class() extends Connector
            {
                public function baseUrl(): string
                {
                    return 'https://api.example.com/';
                }
            };

            $request = createPoolGetRequest('/users');

            $handler = createPoolMockHandler([
                new GuzzleResponse(200, [], '{}'),
            ]);

            // Act
            $pool = new Pool($connector, [$request]);
            $responses = $pool->withHandler($handler)->send();

            // Assert
            expect($responses)->toHaveCount(1)
                ->and($responses[0]->status())->toBe(200);
        });

        test('request initialization is called before execution', function (): void {
            // Arrange
            $connector = createPoolTestConnector();
            $initCalled = false;

            $request = new #[Get()] class('/users', $initCalled) extends Request
            {
                public function __construct(
                    private readonly string $endpoint,
                    private bool &$initCalled,
                ) {}

                public function endpoint(): string
                {
                    return $this->endpoint;
                }

                public function initialize(): void
                {
                    parent::initialize();
                    $this->initCalled = true;
                }
            };

            $handler = createPoolMockHandler([
                new GuzzleResponse(200, [], '{}'),
            ]);

            // Act
            $pool = new Pool($connector, [$request]);
            $pool->withHandler($handler)->send();

            // Assert
            expect($initCalled)->toBeTrue();
        });

        test('onResponse callback receives correct keys for associative array', function (): void {
            // Arrange
            $connector = createPoolTestConnector();
            $requests = [
                'john' => createPoolGetRequest('/users/1'),
                'jane' => createPoolGetRequest('/users/2'),
            ];

            $handler = createPoolMockHandler([
                new GuzzleResponse(200, [], '{}'),
                new GuzzleResponse(200, [], '{}'),
            ]);

            $receivedKeys = [];

            // Act
            $pool = new Pool($connector, $requests);
            $pool->withHandler($handler)->onResponse(function ($response, $request, $key) use (&$receivedKeys): void {
                $receivedKeys[] = $key;
            })->send();

            // Assert
            expect($receivedKeys)->toHaveCount(2)
                ->and($receivedKeys)->toContain('john')
                ->and($receivedKeys)->toContain('jane');
        });

        test('each passes correct keys to callback for associative array', function (): void {
            // Arrange
            $connector = createPoolTestConnector();
            $requests = [
                'first' => createPoolGetRequest('/users'),
                'second' => createPoolGetRequest('/posts'),
            ];

            $handler = createPoolMockHandler([
                new GuzzleResponse(200, [], '{}'),
                new GuzzleResponse(200, [], '{}'),
            ]);

            $keys = [];

            // Act
            $pool = new Pool($connector, $requests);
            $pool->withHandler($handler)->each(function ($response, $key) use (&$keys): void {
                $keys[] = $key;
            });

            // Assert
            expect($keys)->toHaveCount(2)
                ->and($keys)->toContain('first')
                ->and($keys)->toContain('second');
        });

        test('chaining callbacks and configuration methods works with execution', function (): void {
            // Arrange
            $connector = createPoolTestConnector();
            $requests = [
                createPoolGetRequest('/users'),
                createPoolGetRequest('/posts'),
            ];

            $handler = createPoolMockHandler([
                new GuzzleResponse(200, [], '{}'),
                new GuzzleResponse(200, [], '{}'),
            ]);

            $onResponseCalled = 0;

            // Act
            $pool = new Pool($connector, $requests);
            $results = $pool
                ->withHandler($handler)
                ->concurrent(5)
                ->onResponse(function () use (&$onResponseCalled): void {
                    ++$onResponseCalled;
                })
                ->send();

            // Assert
            expect($results)->toHaveCount(2)
                ->and($onResponseCalled)->toBe(2);
        });

        test('empty requests array returns empty responses', function (): void {
            // Arrange
            $connector = createPoolTestConnector();
            $handler = createPoolMockHandler([]);

            // Act
            $pool = new Pool($connector, []);
            $responses = $pool->withHandler($handler)->send();

            // Assert
            expect($responses)->toBeArray()
                ->and($responses)->toBeEmpty();
        });
    });
});

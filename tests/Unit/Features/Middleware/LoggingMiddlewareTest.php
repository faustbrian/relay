<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;
use Cline\Relay\Features\Middleware\LoggingMiddleware;
use Illuminate\Support\Sleep;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Tests\Exceptions\TestSimulatedException;

describe('LoggingMiddleware', function (): void {
    beforeEach(function (): void {
        $this->mockLogger = new class() implements LoggerInterface
        {
            public array $logs = [];

            public function emergency(Stringable|string $message, array $context = []): void
            {
                $this->log(LogLevel::EMERGENCY, $message, $context);
            }

            public function alert(Stringable|string $message, array $context = []): void
            {
                $this->log(LogLevel::ALERT, $message, $context);
            }

            public function critical(Stringable|string $message, array $context = []): void
            {
                $this->log(LogLevel::CRITICAL, $message, $context);
            }

            public function error(Stringable|string $message, array $context = []): void
            {
                $this->log(LogLevel::ERROR, $message, $context);
            }

            public function warning(Stringable|string $message, array $context = []): void
            {
                $this->log(LogLevel::WARNING, $message, $context);
            }

            public function notice(Stringable|string $message, array $context = []): void
            {
                $this->log(LogLevel::NOTICE, $message, $context);
            }

            public function info(Stringable|string $message, array $context = []): void
            {
                $this->log(LogLevel::INFO, $message, $context);
            }

            public function debug(Stringable|string $message, array $context = []): void
            {
                $this->log(LogLevel::DEBUG, $message, $context);
            }

            public function log($level, Stringable|string $message, array $context = []): void
            {
                $this->logs[] = [
                    'level' => $level,
                    'message' => (string) $message,
                    'context' => $context,
                ];
            }
        };
    });

    describe('Happy Paths', function (): void {
        test('logs request and response with default settings', function (): void {
            // Arrange
            $middleware = new LoggingMiddleware($this->mockLogger);

            $request = new class() extends Request
            {
                public function endpoint(): string
                {
                    return '/api/users';
                }

                public function method(): string
                {
                    return 'GET';
                }
            };

            $expectedResponse = Response::make(['users' => []], 200);

            // Act
            $response = $middleware->handle($request, fn ($r): Response => $expectedResponse);

            // Assert
            expect($this->mockLogger->logs)->toHaveCount(2)
                ->and($this->mockLogger->logs[0]['level'])->toBe(LogLevel::INFO)
                ->and($this->mockLogger->logs[0]['message'])->toBe('HTTP Request')
                ->and($this->mockLogger->logs[0]['context']['method'])->toBe('GET')
                ->and($this->mockLogger->logs[0]['context']['endpoint'])->toBe('/api/users')
                ->and($this->mockLogger->logs[1]['level'])->toBe(LogLevel::INFO)
                ->and($this->mockLogger->logs[1]['message'])->toBe('HTTP Response')
                ->and($this->mockLogger->logs[1]['context']['status'])->toBe(200)
                ->and($this->mockLogger->logs[1]['context']['duration_ms'])->toBeFloat()
                ->and($response)->toBe($expectedResponse);
        });

        test('logs request body when enabled', function (): void {
            // Arrange
            $middleware = new LoggingMiddleware($this->mockLogger, logRequestBody: true);

            $request = new class() extends Request
            {
                public function endpoint(): string
                {
                    return '/api/users';
                }

                public function method(): string
                {
                    return 'POST';
                }

                public function body(): array
                {
                    return ['name' => 'John Doe', 'email' => 'john@example.com'];
                }
            };

            // Act
            $middleware->handle($request, fn ($r): Response => Response::make([], 201));

            // Assert
            expect($this->mockLogger->logs[0]['context'])->toHaveKey('request_body')
                ->and($this->mockLogger->logs[0]['context']['request_body'])->toBe(['name' => 'John Doe', 'email' => 'john@example.com']);
        });

        test('logs response body when enabled', function (): void {
            // Arrange
            $middleware = new LoggingMiddleware($this->mockLogger, logResponseBody: true);

            $request = new class() extends Request
            {
                public function endpoint(): string
                {
                    return '/api/users/1';
                }

                public function method(): string
                {
                    return 'GET';
                }
            };

            $responseData = ['id' => 1, 'name' => 'John Doe'];

            // Act
            $middleware->handle($request, fn ($r): Response => Response::make($responseData, 200));

            // Assert
            expect($this->mockLogger->logs[1]['context'])->toHaveKey('response_body')
                ->and($this->mockLogger->logs[1]['context']['response_body'])->toBe($responseData);
        });

        test('logs both request and response bodies when both enabled', function (): void {
            // Arrange
            $middleware = new LoggingMiddleware(
                $this->mockLogger,
                logRequestBody: true,
                logResponseBody: true,
            );

            $request = new class() extends Request
            {
                public function endpoint(): string
                {
                    return '/api/users';
                }

                public function method(): string
                {
                    return 'POST';
                }

                public function body(): array
                {
                    return ['name' => 'Jane Doe'];
                }
            };

            $responseData = ['id' => 2, 'name' => 'Jane Doe'];

            // Act
            $middleware->handle($request, fn ($r): Response => Response::make($responseData, 201));

            // Assert
            expect($this->mockLogger->logs[0]['context'])->toHaveKey('request_body')
                ->and($this->mockLogger->logs[0]['context']['request_body'])->toBe(['name' => 'Jane Doe'])
                ->and($this->mockLogger->logs[1]['context'])->toHaveKey('response_body')
                ->and($this->mockLogger->logs[1]['context']['response_body'])->toBe($responseData);
        });

        test('includes duration in milliseconds in response log', function (): void {
            // Arrange
            $middleware = new LoggingMiddleware($this->mockLogger);

            $request = new class() extends Request
            {
                public function endpoint(): string
                {
                    return '/api/slow';
                }

                public function method(): string
                {
                    return 'GET';
                }
            };

            // Act
            $middleware->handle($request, function ($r): Response {
                Sleep::usleep(10_000); // Sleep for 10ms

                return Response::make([], 200);
            });

            // Assert
            expect($this->mockLogger->logs[1]['context']['duration_ms'])->toBeFloat()
                ->and($this->mockLogger->logs[1]['context']['duration_ms'])->toBeGreaterThan(0);
        });

        test('passes through response unchanged', function (): void {
            // Arrange
            $middleware = new LoggingMiddleware($this->mockLogger);

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

            $expectedResponse = Response::make(['data' => 'test'], 200, ['X-Custom' => 'header']);

            // Act
            $response = $middleware->handle($request, fn ($r): Response => $expectedResponse);

            // Assert
            expect($response)->toBe($expectedResponse);
        });
    });

    describe('Sad Paths', function (): void {
        test('logs response even when handler throws exception', function (): void {
            // Arrange
            $middleware = new LoggingMiddleware($this->mockLogger);

            $request = new class() extends Request
            {
                public function endpoint(): string
                {
                    return '/api/error';
                }

                public function method(): string
                {
                    return 'GET';
                }
            };

            // Act & Assert
            try {
                $middleware->handle($request, function ($r): void {
                    throw TestSimulatedException::somethingWentWrong();
                });
            } catch (TestSimulatedException) {
                // Expected exception
            }

            // Should still log the request
            expect($this->mockLogger->logs)->toHaveCount(1)
                ->and($this->mockLogger->logs[0]['message'])->toBe('HTTP Request');
        });
    });

    describe('Edge Cases', function (): void {
        test('does not log request body when disabled even if body exists', function (): void {
            // Arrange
            $middleware = new LoggingMiddleware($this->mockLogger, logRequestBody: false);

            $request = new class() extends Request
            {
                public function endpoint(): string
                {
                    return '/api/users';
                }

                public function method(): string
                {
                    return 'POST';
                }

                public function body(): array
                {
                    return ['sensitive' => 'data'];
                }
            };

            // Act
            $middleware->handle($request, fn ($r): Response => Response::make([], 201));

            // Assert
            expect($this->mockLogger->logs[0]['context'])->not->toHaveKey('request_body');
        });

        test('does not log response body when disabled', function (): void {
            // Arrange
            $middleware = new LoggingMiddleware($this->mockLogger, logResponseBody: false);

            $request = new class() extends Request
            {
                public function endpoint(): string
                {
                    return '/api/users/1';
                }

                public function method(): string
                {
                    return 'GET';
                }
            };

            // Act
            $middleware->handle($request, fn ($r): Response => Response::make(['secret' => 'data'], 200));

            // Assert
            expect($this->mockLogger->logs[1]['context'])->not->toHaveKey('response_body');
        });

        test('handles request with null body', function (): void {
            // Arrange
            $middleware = new LoggingMiddleware($this->mockLogger, logRequestBody: true);

            $request = new class() extends Request
            {
                public function endpoint(): string
                {
                    return '/api/ping';
                }

                public function method(): string
                {
                    return 'GET';
                }

                public function body(): ?array
                {
                    return null;
                }
            };

            // Act
            $middleware->handle($request, fn ($r): Response => Response::make([], 200));

            // Assert
            expect($this->mockLogger->logs[0]['context'])->not->toHaveKey('request_body');
        });

        test('logs response with JSON data when logResponseBody enabled', function (): void {
            // Arrange
            $middleware = new LoggingMiddleware($this->mockLogger, logResponseBody: true);

            $request = new class() extends Request
            {
                public function endpoint(): string
                {
                    return '/api/data';
                }

                public function method(): string
                {
                    return 'GET';
                }
            };

            $responseData = ['message' => 'Success', 'code' => 100];

            // Act
            $middleware->handle($request, fn ($r): Response => Response::make($responseData, 200));

            // Assert
            expect($this->mockLogger->logs[1]['context']['response_body'])->toBe($responseData);
        });

        test('handles different HTTP methods', function (): void {
            // Arrange
            $middleware = new LoggingMiddleware($this->mockLogger);

            foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
                $request = new class($method) extends Request
                {
                    public function __construct(
                        private readonly string $httpMethod,
                    ) {}

                    public function endpoint(): string
                    {
                        return '/api/resource';
                    }

                    public function method(): string
                    {
                        return $this->httpMethod;
                    }
                };

                // Act
                $middleware->handle($request, fn ($r): Response => Response::make([], 200));

                // Assert
                $lastRequestLog = $this->mockLogger->logs[count($this->mockLogger->logs) - 2];
                expect($lastRequestLog['context']['method'])->toBe($method);

                $this->mockLogger->logs = []; // Clear for next iteration
            }
        });

        test('handles different status codes', function (): void {
            // Arrange
            $middleware = new LoggingMiddleware($this->mockLogger);

            $request = new class() extends Request
            {
                public function endpoint(): string
                {
                    return '/api/test';
                }

                public function method(): string
                {
                    return 'GET';
                }
            };

            foreach ([200, 201, 400, 404, 500] as $statusCode) {
                // Act
                $middleware->handle($request, fn ($r): Response => Response::make([], $statusCode));

                // Assert
                $lastResponseLog = $this->mockLogger->logs[count($this->mockLogger->logs) - 1];
                expect($lastResponseLog['context']['status'])->toBe($statusCode);

                $this->mockLogger->logs = []; // Clear for next iteration
            }
        });

        test('rounds duration to two decimal places', function (): void {
            // Arrange
            $middleware = new LoggingMiddleware($this->mockLogger);

            $request = new class() extends Request
            {
                public function endpoint(): string
                {
                    return '/api/test';
                }

                public function method(): string
                {
                    return 'GET';
                }
            };

            // Act
            $middleware->handle($request, fn ($r): Response => Response::make([], 200));

            // Assert
            $duration = $this->mockLogger->logs[1]['context']['duration_ms'];
            expect($duration)->toBe(round($duration, 2));
        });
    });
});

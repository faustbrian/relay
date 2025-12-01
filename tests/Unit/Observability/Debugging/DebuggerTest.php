<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;
use Cline\Relay\Observability\Debugging\Debugger;

describe('Debugger', function (): void {
    describe('Happy Paths', function (): void {
        test('formats request with query params correctly', function (): void {
            // Arrange
            $debugger = new Debugger();
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

                public function query(): array
                {
                    return ['page' => 1, 'limit' => 10];
                }
            };

            // Act
            $output = $debugger->formatRequest($request, 'https://api.example.com');

            // Assert
            expect($output)
                ->toContain('GET')
                ->toContain('https://api.example.com/users')
                ->toContain('Query: page=1&limit=10');
        });

        test('formats request with headers correctly', function (): void {
            // Arrange
            $debugger = new Debugger();
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

                public function headers(): array
                {
                    return ['X-Custom-Header' => 'value'];
                }
            };

            // Act
            $output = $debugger->formatRequest($request, 'https://api.example.com');

            // Assert
            expect($output)
                ->toContain('Headers:')
                ->toContain('X-Custom-Header: value');
        });

        test('formats request with body correctly', function (): void {
            // Arrange
            $debugger = new Debugger();
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

                public function body(): array
                {
                    return ['name' => 'John Doe', 'email' => 'john@example.com'];
                }
            };

            // Act
            $output = $debugger->formatRequest($request, 'https://api.example.com');

            // Assert
            expect($output)
                ->toContain('Body:')
                ->toContain('John Doe')
                ->toContain('john@example.com');
        });

        test('formats response with status code and text correctly', function (): void {
            // Arrange
            $debugger = new Debugger();
            $response = Response::make(['id' => 1, 'name' => 'John'], 200);
            $response->setDuration(123.45);

            // Act
            $output = $debugger->formatResponse($response);

            // Assert
            expect($output)
                ->toContain('200 OK')
                ->toContain('123.45ms');
        });

        test('formats response with 201 Created status text', function (): void {
            // Arrange
            $debugger = new Debugger();
            $response = Response::make(['id' => 1], 201);

            // Act
            $output = $debugger->formatResponse($response);

            // Assert
            expect($output)->toContain('201 Created');
        });

        test('formats response with 204 No Content status text', function (): void {
            // Arrange
            $debugger = new Debugger();
            $response = Response::make([], 204);

            // Act
            $output = $debugger->formatResponse($response);

            // Assert
            expect($output)->toContain('204 No Content');
        });

        test('formats response with 301 Moved Permanently status text', function (): void {
            // Arrange
            $debugger = new Debugger();
            $response = Response::make([], 301);

            // Act
            $output = $debugger->formatResponse($response);

            // Assert
            expect($output)->toContain('301 Moved Permanently');
        });

        test('formats response with 302 Found status text', function (): void {
            // Arrange
            $debugger = new Debugger();
            $response = Response::make([], 302);

            // Act
            $output = $debugger->formatResponse($response);

            // Assert
            expect($output)->toContain('302 Found');
        });

        test('formats response with 304 Not Modified status text', function (): void {
            // Arrange
            $debugger = new Debugger();
            $response = Response::make([], 304);

            // Act
            $output = $debugger->formatResponse($response);

            // Assert
            expect($output)->toContain('304 Not Modified');
        });

        test('formats response with 400 Bad Request status text', function (): void {
            // Arrange
            $debugger = new Debugger();
            $response = Response::make(['error' => 'Bad request'], 400);

            // Act
            $output = $debugger->formatResponse($response);

            // Assert
            expect($output)->toContain('400 Bad Request');
        });

        test('formats response with 401 Unauthorized status text', function (): void {
            // Arrange
            $debugger = new Debugger();
            $response = Response::make(['error' => 'Unauthorized'], 401);

            // Act
            $output = $debugger->formatResponse($response);

            // Assert
            expect($output)->toContain('401 Unauthorized');
        });

        test('formats response with 403 Forbidden status text', function (): void {
            // Arrange
            $debugger = new Debugger();
            $response = Response::make(['error' => 'Forbidden'], 403);

            // Act
            $output = $debugger->formatResponse($response);

            // Assert
            expect($output)->toContain('403 Forbidden');
        });

        test('formats response with 404 Not Found status text', function (): void {
            // Arrange
            $debugger = new Debugger();
            $response = Response::make(['error' => 'Not found'], 404);

            // Act
            $output = $debugger->formatResponse($response);

            // Assert
            expect($output)->toContain('404 Not Found');
        });

        test('formats response with 422 Unprocessable Entity status text', function (): void {
            // Arrange
            $debugger = new Debugger();
            $response = Response::make(['errors' => []], 422);

            // Act
            $output = $debugger->formatResponse($response);

            // Assert
            expect($output)->toContain('422 Unprocessable Entity');
        });

        test('formats response with 429 Too Many Requests status text', function (): void {
            // Arrange
            $debugger = new Debugger();
            $response = Response::make(['error' => 'Rate limit exceeded'], 429);

            // Act
            $output = $debugger->formatResponse($response);

            // Assert
            expect($output)->toContain('429 Too Many Requests');
        });

        test('formats response with 500 Internal Server Error status text', function (): void {
            // Arrange
            $debugger = new Debugger();
            $response = Response::make(['error' => 'Server error'], 500);

            // Act
            $output = $debugger->formatResponse($response);

            // Assert
            expect($output)->toContain('500 Internal Server Error');
        });

        test('formats response with 502 Bad Gateway status text', function (): void {
            // Arrange
            $debugger = new Debugger();
            $response = Response::make(['error' => 'Bad gateway'], 502);

            // Act
            $output = $debugger->formatResponse($response);

            // Assert
            expect($output)->toContain('502 Bad Gateway');
        });

        test('formats response with 503 Service Unavailable status text', function (): void {
            // Arrange
            $debugger = new Debugger();
            $response = Response::make(['error' => 'Service unavailable'], 503);

            // Act
            $output = $debugger->formatResponse($response);

            // Assert
            expect($output)->toContain('503 Service Unavailable');
        });

        test('formats response with 504 Gateway Timeout status text', function (): void {
            // Arrange
            $debugger = new Debugger();
            $response = Response::make(['error' => 'Gateway timeout'], 504);

            // Act
            $output = $debugger->formatResponse($response);

            // Assert
            expect($output)->toContain('504 Gateway Timeout');
        });

        test('formats response with unknown status code returns empty status text', function (): void {
            // Arrange
            $debugger = new Debugger();
            // Using 418 which is valid but not in the match statement
            $response = Response::make(['error' => 'Unknown'], 418);

            // Act
            $output = $debugger->formatResponse($response);

            // Assert
            // The output should just have "418 " with nothing after the space
            expect($output)
                ->toContain('418 ')
                ->not->toContain('OK')
                ->not->toContain('Created')
                ->not->toContain('Found'); // Should not contain any known status texts
        });

        test('redacts sensitive headers in request output', function (): void {
            // Arrange
            $debugger = new Debugger();
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

                public function headers(): array
                {
                    return [
                        'Authorization' => 'Bearer secret-token',
                        'X-API-Key' => 'api-key-123',
                        'X-Custom' => 'visible',
                    ];
                }
            };

            // Act
            $output = $debugger->formatRequest($request, 'https://api.example.com');

            // Assert
            expect($output)
                ->toContain('Authorization: ***REDACTED***')
                ->toContain('X-API-Key: ***REDACTED***')
                ->toContain('X-Custom: visible')
                ->not->toContain('secret-token')
                ->not->toContain('api-key-123');
        });

        test('redacts sensitive body keys in request output', function (): void {
            // Arrange
            $debugger = new Debugger();
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

                public function body(): array
                {
                    return [
                        'username' => 'john',
                        'password' => 'secret123',
                        'api_key' => 'key123',
                        'name' => 'John Doe',
                    ];
                }
            };

            // Act
            $output = $debugger->formatRequest($request, 'https://api.example.com');

            // Assert
            expect($output)
                ->toContain('username')
                ->toContain('john')
                ->toContain('name')
                ->toContain('John Doe')
                ->toContain('***REDACTED***')
                ->not->toContain('secret123')
                ->not->toContain('key123');
        });

        test('allows custom sensitive headers to be configured', function (): void {
            // Arrange
            $debugger = new Debugger();
            $debugger->setSensitiveHeaders(['X-Custom-Secret']);

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

                public function headers(): array
                {
                    return [
                        'Authorization' => 'Bearer token',
                        'X-Custom-Secret' => 'secret-value',
                    ];
                }
            };

            // Act
            $output = $debugger->formatRequest($request, 'https://api.example.com');

            // Assert
            expect($output)
                ->toContain('Authorization: Bearer token') // Not redacted anymore
                ->toContain('X-Custom-Secret: ***REDACTED***'); // Now redacted
        });

        test('allows custom sensitive body keys to be configured', function (): void {
            // Arrange
            $debugger = new Debugger();
            $debugger->setSensitiveBodyKeys(['custom_secret']);

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

                public function body(): array
                {
                    return [
                        'password' => 'secret123',
                        'custom_secret' => 'my-secret',
                    ];
                }
            };

            // Act
            $output = $debugger->formatRequest($request, 'https://api.example.com');

            // Assert
            expect($output)
                ->toContain('password')
                ->toContain('secret123') // Not redacted anymore
                ->toContain('***REDACTED***'); // custom_secret is redacted
        });
    });

    describe('Edge Cases', function (): void {
        test('formats response without duration when not set', function (): void {
            // Arrange
            $debugger = new Debugger();
            $response = Response::make(['id' => 1], 200);

            // Act
            $output = $debugger->formatResponse($response);

            // Assert
            expect($output)
                ->toContain('200 OK')
                ->not->toContain('ms');
        });

        test('formats response with empty JSON body', function (): void {
            // Arrange
            $debugger = new Debugger();
            $response = Response::make([], 200);

            // Act
            $output = $debugger->formatResponse($response);

            // Assert
            expect($output)
                ->toContain('200 OK')
                ->not->toContain('Body:');
        });

        test('formats request with empty query params', function (): void {
            // Arrange
            $debugger = new Debugger();
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

                public function query(): array
                {
                    return [];
                }
            };

            // Act
            $output = $debugger->formatRequest($request, 'https://api.example.com');

            // Assert
            expect($output)
                ->not->toContain('Query:');
        });

        test('formats request with no headers', function (): void {
            // Arrange
            $debugger = new Debugger();
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
            $output = $debugger->formatRequest($request, 'https://api.example.com');

            // Assert
            expect($output)->toContain('GET https://api.example.com/users');
        });

        test('redacts nested sensitive body keys', function (): void {
            // Arrange
            $debugger = new Debugger();
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

                public function body(): array
                {
                    return [
                        'user' => [
                            'name' => 'John',
                            'password' => 'secret',
                        ],
                    ];
                }
            };

            // Act
            $output = $debugger->formatRequest($request, 'https://api.example.com');

            // Assert
            expect($output)
                ->toContain('John')
                ->toContain('***REDACTED***')
                ->not->toContain('secret');
        });

        test('handles array header values correctly', function (): void {
            // Arrange
            $debugger = new Debugger();
            $psrResponse = new GuzzleHttp\Psr7\Response(200, [
                'X-Multiple' => ['value1', 'value2'],
            ], json_encode(['id' => 1]));
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
            $response = new Response($psrResponse, $request);

            // Act
            $output = $debugger->formatResponse($response);

            // Assert
            expect($output)
                ->toContain('X-Multiple: value1, value2');
        });
    });
});

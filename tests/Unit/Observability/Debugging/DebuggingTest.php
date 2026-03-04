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
use Cline\Relay\Support\Attributes\ContentTypes\Json;
use Cline\Relay\Support\Attributes\Methods\Get;
use Cline\Relay\Support\Attributes\Methods\Post;
use GuzzleHttp\Psr7\Response as Psr7Response;

function createDebugRequest(string $endpoint = '/users', string $method = 'GET'): Request
{
    return match ($method) {
        'POST' => new #[Post(), Json()] class($endpoint) extends Request
        {
            public function __construct(
                private readonly string $ep,
            ) {}

            public function endpoint(): string
            {
                return $this->ep;
            }

            public function body(): array
            {
                return ['name' => 'John', 'password' => 'secret123'];
            }

            public function headers(): array
            {
                return ['Authorization' => 'Bearer token123', 'X-Custom' => 'value'];
            }
        },
        default => new #[Get()] class($endpoint) extends Request
        {
            public function __construct(
                private readonly string $ep,
            ) {}

            public function endpoint(): string
            {
                return $this->ep;
            }

            public function query(): array
            {
                return ['page' => 1, 'limit' => 10];
            }
        },
    };
}

describe('Debugger', function (): void {
    describe('formatRequest()', function (): void {
        it('formats GET request with query params', function (): void {
            $debugger = new Debugger();
            $request = createDebugRequest('/users');

            $output = $debugger->formatRequest($request, 'https://api.example.com');

            expect($output)->toContain('Request');
            expect($output)->toContain('GET https://api.example.com/users');
            expect($output)->toContain('Query:');
            expect($output)->toContain('page=1');
            expect($output)->toContain('limit=10');
        });

        it('formats POST request with body', function (): void {
            $debugger = new Debugger();
            $request = createDebugRequest('/users', 'POST');

            $output = $debugger->formatRequest($request, 'https://api.example.com');

            expect($output)->toContain('POST https://api.example.com/users');
            expect($output)->toContain('Body:');
            expect($output)->toContain('"name": "John"');
        });

        it('redacts sensitive headers', function (): void {
            $debugger = new Debugger();
            $request = createDebugRequest('/users', 'POST');

            $output = $debugger->formatRequest($request, 'https://api.example.com');

            expect($output)->toContain('Authorization: ***REDACTED***');
            expect($output)->toContain('X-Custom: value');
            expect($output)->not->toContain('Bearer token123');
        });

        it('redacts sensitive body keys', function (): void {
            $debugger = new Debugger();
            $request = createDebugRequest('/users', 'POST');

            $output = $debugger->formatRequest($request, 'https://api.example.com');

            expect($output)->toContain('"password": "***REDACTED***"');
            expect($output)->not->toContain('secret123');
        });
    });

    describe('formatResponse()', function (): void {
        it('formats successful response', function (): void {
            $debugger = new Debugger();
            $request = createDebugRequest('/users');
            $psrResponse = new Psr7Response(200, ['Content-Type' => 'application/json'], '{"id": 1}');
            $response = new Response($psrResponse, $request);

            $output = $debugger->formatResponse($response);

            expect($output)->toContain('Response');
            expect($output)->toContain('200 OK');
            expect($output)->toContain('Headers:');
            expect($output)->toContain('Content-Type: application/json');
        });

        it('formats error response', function (): void {
            $debugger = new Debugger();
            $request = createDebugRequest('/users');
            $psrResponse = new Psr7Response(404, [], '{"error": "Not found"}');
            $response = new Response($psrResponse, $request);

            $output = $debugger->formatResponse($response);

            expect($output)->toContain('404 Not Found');
        });

        it('redacts sensitive response headers', function (): void {
            $debugger = new Debugger();
            $request = createDebugRequest('/users');
            $psrResponse = new Psr7Response(200, [
                'Set-Cookie' => 'session=abc123',
                'X-Request-Id' => '12345',
            ], '{}');
            $response = new Response($psrResponse, $request);

            $output = $debugger->formatResponse($response);

            expect($output)->toContain('Set-Cookie: ***REDACTED***');
            expect($output)->toContain('X-Request-Id: 12345');
            expect($output)->not->toContain('session=abc123');
        });

        it('redacts sensitive response body keys', function (): void {
            $debugger = new Debugger();
            $request = createDebugRequest('/users');
            $psrResponse = new Psr7Response(200, [], '{"token": "eyJhbG...", "user": "john"}');
            $response = new Response($psrResponse, $request);

            $output = $debugger->formatResponse($response);

            expect($output)->toContain('"token": "***REDACTED***"');
            expect($output)->toContain('"user": "john"');
            expect($output)->not->toContain('eyJhbG');
        });

        it('shows response duration when available', function (): void {
            $debugger = new Debugger();
            $request = createDebugRequest('/users');
            $psrResponse = new Psr7Response(200, [], '{}');
            $response = new Response($psrResponse, $request)->setDuration(150);

            $output = $debugger->formatResponse($response);

            expect($output)->toContain('150ms');
        });
    });

    describe('setSensitiveHeaders()', function (): void {
        it('allows custom sensitive headers', function (): void {
            $debugger = new Debugger();
            $debugger->setSensitiveHeaders(['X-Custom-Secret']);

            $request = new #[Post(), Json()] class extends Request
            {
                public function endpoint(): string
                {
                    return '/test';
                }

                public function headers(): array
                {
                    return [
                        'Authorization' => 'Bearer token',
                        'X-Custom-Secret' => 'my-secret',
                    ];
                }
            };

            $output = $debugger->formatRequest($request, 'https://api.example.com');

            // Custom header should be redacted
            expect($output)->toContain('X-Custom-Secret: ***REDACTED***');
            // Default Authorization should NOT be redacted (we replaced the list)
            expect($output)->toContain('Authorization: Bearer token');
        });
    });

    describe('setSensitiveBodyKeys()', function (): void {
        it('allows custom sensitive body keys', function (): void {
            $debugger = new Debugger();
            $debugger->setSensitiveBodyKeys(['my_secret_field']);

            $request = new #[Post(), Json()] class extends Request
            {
                public function endpoint(): string
                {
                    return '/test';
                }

                public function body(): array
                {
                    return [
                        'password' => 'visible-now',
                        'my_secret_field' => 'hidden',
                    ];
                }
            };

            $output = $debugger->formatRequest($request, 'https://api.example.com');

            // Custom key should be redacted
            expect($output)->toContain('"my_secret_field": "***REDACTED***"');
            // Default password should NOT be redacted (we replaced the list)
            expect($output)->toContain('"password": "visible-now"');
        });
    });

    describe('nested body redaction', function (): void {
        it('redacts nested sensitive keys', function (): void {
            $debugger = new Debugger();

            $request = new #[Post(), Json()] class extends Request
            {
                public function endpoint(): string
                {
                    return '/test';
                }

                public function body(): array
                {
                    return [
                        'user' => [
                            'name' => 'John',
                            'password' => 'nested-secret',
                            'credentials' => [
                                'api_key' => 'deep-secret',
                            ],
                        ],
                    ];
                }
            };

            $output = $debugger->formatRequest($request, 'https://api.example.com');

            expect($output)->toContain('"password": "***REDACTED***"');
            expect($output)->toContain('"api_key": "***REDACTED***"');
            expect($output)->toContain('"name": "John"');
            expect($output)->not->toContain('nested-secret');
            expect($output)->not->toContain('deep-secret');
        });
    });
});

describe('Request debugging', function (): void {
    it('has dump method', function (): void {
        $request = createDebugRequest('/users');

        expect(method_exists($request, 'dump'))->toBeTrue();
    });

    it('has dd method', function (): void {
        $request = createDebugRequest('/users');

        expect(method_exists($request, 'dd'))->toBeTrue();
    });
});

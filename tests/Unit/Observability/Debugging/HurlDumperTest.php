<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Relay\Core\Request;
use Cline\Relay\Observability\Debugging\HurlDumper;
use Cline\Relay\Support\Attributes\ContentTypes\Json;
use Cline\Relay\Support\Attributes\Methods\Delete;
use Cline\Relay\Support\Attributes\Methods\Get;
use Cline\Relay\Support\Attributes\Methods\Post;
use Cline\Relay\Support\Attributes\Methods\Put;

function createHurlDumperGetRequest(string $endpoint = '/api/users'): Request
{
    return new #[Get()] class($endpoint) extends Request
    {
        public function __construct(
            private readonly string $ep,
        ) {}

        public function endpoint(): string
        {
            return $this->ep;
        }
    };
}

function createHurlDumperPostRequest(string $endpoint = '/api/users'): Request
{
    return new #[Post()] #[Json()] class($endpoint) extends Request
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
            return ['name' => 'John Doe', 'email' => 'john@example.com'];
        }
    };
}

function createHurlDumperPutRequest(): Request
{
    return new #[Put()] #[Json()] class() extends Request
    {
        public function endpoint(): string
        {
            return '/api/users/1';
        }

        public function body(): array
        {
            return ['name' => 'Jane Doe'];
        }
    };
}

function createHurlDumperDeleteRequest(): Request
{
    return new #[Delete()] class() extends Request
    {
        public function endpoint(): string
        {
            return '/api/users/1';
        }
    };
}

function createHurlDumperRequestWithQuery(): Request
{
    return new #[Get()] class() extends Request
    {
        public function endpoint(): string
        {
            return '/api/users';
        }

        public function query(): array
        {
            return ['page' => 1, 'limit' => 10, 'active' => true];
        }
    };
}

function createHurlDumperRequestWithHeaders(): Request
{
    return new #[Get()] class() extends Request
    {
        public function endpoint(): string
        {
            return '/api/users';
        }

        public function headers(): array
        {
            return [
                'Authorization' => 'Bearer token123',
                'X-Custom-Header' => 'custom-value',
            ];
        }
    };
}

describe('HurlDumper', function (): void {
    describe('Basic Functionality', function (): void {
        it('dumps a simple GET request', function (): void {
            $dumper = new HurlDumper();
            $request = createHurlDumperGetRequest();

            $hurl = $dumper->dump($request, 'https://api.example.com');

            expect($hurl)->toBe('GET https://api.example.com/api/users');
        });

        it('dumps a POST request with JSON body', function (): void {
            $dumper = new HurlDumper();
            $request = createHurlDumperPostRequest();

            $hurl = $dumper->dump($request, 'https://api.example.com');

            expect($hurl)->toContain('POST https://api.example.com/api/users')
                ->and($hurl)->toContain('Content-Type: application/json')
                ->and($hurl)->toContain('"name": "John Doe"')
                ->and($hurl)->toContain('"email": "john@example.com"');
        });

        it('dumps a PUT request', function (): void {
            $dumper = new HurlDumper();
            $request = createHurlDumperPutRequest();

            $hurl = $dumper->dump($request, 'https://api.example.com');

            expect($hurl)->toContain('PUT https://api.example.com/api/users/1')
                ->and($hurl)->toContain('"name": "Jane Doe"');
        });

        it('dumps a DELETE request', function (): void {
            $dumper = new HurlDumper();
            $request = createHurlDumperDeleteRequest();

            $hurl = $dumper->dump($request, 'https://api.example.com');

            expect($hurl)->toBe('DELETE https://api.example.com/api/users/1');
        });

        it('includes headers', function (): void {
            $dumper = new HurlDumper();
            $request = createHurlDumperRequestWithHeaders();

            $hurl = $dumper->dump($request, 'https://api.example.com');

            expect($hurl)->toContain('Authorization: Bearer token123')
                ->and($hurl)->toContain('X-Custom-Header: custom-value');
        });

        it('includes query parameters in section', function (): void {
            $dumper = new HurlDumper();
            $request = createHurlDumperRequestWithQuery();

            $hurl = $dumper->dump($request, 'https://api.example.com');

            expect($hurl)->toContain('[QueryStringParams]')
                ->and($hurl)->toContain('page: 1')
                ->and($hurl)->toContain('limit: 10')
                ->and($hurl)->toContain('active: true');
        });
    });

    describe('Basic Auth', function (): void {
        it('includes BasicAuth section when credentials provided', function (): void {
            $dumper = new HurlDumper()->withBasicAuth('admin', 'secret123');
            $request = createHurlDumperGetRequest();

            $hurl = $dumper->dump($request, 'https://api.example.com');

            expect($hurl)->toContain('[BasicAuth]')
                ->and($hurl)->toContain('admin: secret123');
        });
    });

    describe('JSON Formatting', function (): void {
        it('pretty prints JSON by default', function (): void {
            $dumper = new HurlDumper();
            $request = createHurlDumperPostRequest();

            $hurl = $dumper->dump($request, 'https://api.example.com');

            // Pretty print has newlines
            expect($hurl)->toContain("\n    \"name\"");
        });

        it('can disable pretty printing', function (): void {
            $dumper = new HurlDumper()->prettyPrintJson(false);
            $request = createHurlDumperPostRequest();

            $hurl = $dumper->dump($request, 'https://api.example.com');

            // Compact JSON on single line
            expect($hurl)->toContain('{"name":"John Doe","email":"john@example.com"}');
        });
    });

    describe('Multiple Requests', function (): void {
        it('dumps multiple requests separated by blank lines', function (): void {
            $dumper = new HurlDumper();

            $hurl = $dumper->dumpMultiple([
                ['request' => createHurlDumperGetRequest('/api/users'), 'baseUrl' => 'https://api.example.com'],
                ['request' => createHurlDumperPostRequest('/api/users'), 'baseUrl' => 'https://api.example.com'],
            ]);

            expect($hurl)->toContain('GET https://api.example.com/api/users')
                ->and($hurl)->toContain('POST https://api.example.com/api/users')
                ->and($hurl)->toContain("\n\n"); // Double newline separator
        });
    });

    describe('Edge Cases', function (): void {
        it('handles empty body', function (): void {
            $dumper = new HurlDumper();
            $request = createHurlDumperGetRequest();

            $hurl = $dumper->dump($request, 'https://api.example.com');

            // Should not contain JSON body
            expect($hurl)->not->toContain('{');
        });

        it('handles array values in query params', function (): void {
            $request = new #[Get()] class() extends Request
            {
                public function endpoint(): string
                {
                    return '/api/search';
                }

                public function query(): array
                {
                    return ['tags' => ['php', 'laravel']];
                }
            };

            $dumper = new HurlDumper();
            $hurl = $dumper->dump($request, 'https://api.example.com');

            expect($hurl)->toContain('[QueryStringParams]')
                ->and($hurl)->toContain('tags: ["php","laravel"]');
        });

        it('uses additional headers from withHeader', function (): void {
            $request = createHurlDumperGetRequest()
                ->withHeader('X-Request-Id', 'abc123');

            $dumper = new HurlDumper();
            $hurl = $dumper->dump($request, 'https://api.example.com');

            expect($hurl)->toContain('X-Request-Id: abc123');
        });

        it('uses additional query from withQuery', function (): void {
            $request = createHurlDumperGetRequest()
                ->withQuery('extra', 'param');

            $dumper = new HurlDumper();
            $hurl = $dumper->dump($request, 'https://api.example.com');

            expect($hurl)->toContain('[QueryStringParams]')
                ->and($hurl)->toContain('extra: param');
        });

        it('does not duplicate Content-Type header', function (): void {
            $request = new #[Post()] #[Json()] class() extends Request
            {
                public function endpoint(): string
                {
                    return '/api/users';
                }

                public function headers(): array
                {
                    return ['Content-Type' => 'application/vnd.api+json'];
                }

                public function body(): array
                {
                    return ['name' => 'Test'];
                }
            };

            $dumper = new HurlDumper();
            $hurl = $dumper->dump($request, 'https://api.example.com');

            expect($hurl)->toContain('Content-Type: application/vnd.api+json')
                ->and(mb_substr_count($hurl, 'Content-Type'))->toBe(1);
        });

        it('handles boolean false in query params', function (): void {
            $request = new #[Get()] class() extends Request
            {
                public function endpoint(): string
                {
                    return '/api/users';
                }

                public function query(): array
                {
                    return ['active' => false];
                }
            };

            $dumper = new HurlDumper();
            $hurl = $dumper->dump($request, 'https://api.example.com');

            expect($hurl)->toContain('active: false');
        });
    });
});

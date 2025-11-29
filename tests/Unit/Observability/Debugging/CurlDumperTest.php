<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Relay\Core\Request;
use Cline\Relay\Observability\Debugging\CurlDumper;
use Cline\Relay\Support\Attributes\ContentTypes\Json;
use Cline\Relay\Support\Attributes\Methods\Delete;
use Cline\Relay\Support\Attributes\Methods\Get;
use Cline\Relay\Support\Attributes\Methods\Post;
use Cline\Relay\Support\Attributes\Methods\Put;

function createCurlDumperGetRequest(string $endpoint = '/api/users'): Request
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

function createCurlDumperPostRequest(string $endpoint = '/api/users'): Request
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

function createCurlDumperPutRequest(): Request
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

function createCurlDumperDeleteRequest(): Request
{
    return new #[Delete()] class() extends Request
    {
        public function endpoint(): string
        {
            return '/api/users/1';
        }
    };
}

function createCurlDumperRequestWithQuery(): Request
{
    return new #[Get()] class() extends Request
    {
        public function endpoint(): string
        {
            return '/api/users';
        }

        public function query(): array
        {
            return ['page' => 1, 'limit' => 10, 'filter' => 'active'];
        }
    };
}

function createCurlDumperRequestWithHeaders(): Request
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

function createCurlDumperRequestWithContentTypeHeader(): Request
{
    return new #[Post()] #[Json()] class() extends Request
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
}

describe('CurlDumper', function (): void {
    describe('Basic Functionality', function (): void {
        it('dumps a simple GET request', function (): void {
            $dumper = new CurlDumper();
            $request = createCurlDumperGetRequest();

            $curl = $dumper->dump($request, 'https://api.example.com');

            expect($curl)->toContain('curl')
                ->and($curl)->toContain("'https://api.example.com/api/users'")
                ->and($curl)->not->toContain('-X GET');
        });

        it('dumps a POST request with body', function (): void {
            $dumper = new CurlDumper();
            $request = createCurlDumperPostRequest();

            $curl = $dumper->dump($request, 'https://api.example.com');

            expect($curl)->toContain('curl')
                ->and($curl)->toContain('-X POST')
                ->and($curl)->toContain('-d')
                ->and($curl)->toContain('John Doe')
                ->and($curl)->toContain("'Content-Type: application/json'");
        });

        it('dumps a PUT request', function (): void {
            $dumper = new CurlDumper();
            $request = createCurlDumperPutRequest();

            $curl = $dumper->dump($request, 'https://api.example.com');

            expect($curl)->toContain('-X PUT')
                ->and($curl)->toContain("'https://api.example.com/api/users/1'");
        });

        it('dumps a DELETE request', function (): void {
            $dumper = new CurlDumper();
            $request = createCurlDumperDeleteRequest();

            $curl = $dumper->dump($request, 'https://api.example.com');

            expect($curl)->toContain('-X DELETE');
        });

        it('includes query parameters in URL', function (): void {
            $dumper = new CurlDumper();
            $request = createCurlDumperRequestWithQuery();

            $curl = $dumper->dump($request, 'https://api.example.com');

            expect($curl)->toContain('page=1')
                ->and($curl)->toContain('limit=10')
                ->and($curl)->toContain('filter=active');
        });

        it('includes headers', function (): void {
            $dumper = new CurlDumper();
            $request = createCurlDumperRequestWithHeaders();

            $curl = $dumper->dump($request, 'https://api.example.com');

            expect($curl)->toContain("-H 'Authorization: Bearer token123'")
                ->and($curl)->toContain("-H 'X-Custom-Header: custom-value'");
        });

        it('does not duplicate Content-Type header if already set', function (): void {
            $dumper = new CurlDumper();
            $request = createCurlDumperRequestWithContentTypeHeader();

            $curl = $dumper->dump($request, 'https://api.example.com');

            // Should only have one Content-Type header (the custom one)
            expect($curl)->toContain("'Content-Type: application/vnd.api+json'")
                ->and(mb_substr_count($curl, 'Content-Type'))->toBe(1);
        });
    });

    describe('Options', function (): void {
        it('adds --compressed flag', function (): void {
            $dumper = new CurlDumper()->compressed();
            $request = createCurlDumperGetRequest();

            $curl = $dumper->dump($request, 'https://api.example.com');

            expect($curl)->toContain('--compressed');
        });

        it('adds -k flag for insecure', function (): void {
            $dumper = new CurlDumper()->insecure();
            $request = createCurlDumperGetRequest();

            $curl = $dumper->dump($request, 'https://api.example.com');

            expect($curl)->toContain('-k');
        });

        it('adds -v flag for verbose', function (): void {
            $dumper = new CurlDumper()->verbose();
            $request = createCurlDumperGetRequest();

            $curl = $dumper->dump($request, 'https://api.example.com');

            expect($curl)->toContain('-v');
        });

        it('adds -s flag for silent', function (): void {
            $dumper = new CurlDumper()->silent();
            $request = createCurlDumperGetRequest();

            $curl = $dumper->dump($request, 'https://api.example.com');

            expect($curl)->toContain('-s');
        });

        it('adds -L flag for follow redirects', function (): void {
            $dumper = new CurlDumper()->followRedirects();
            $request = createCurlDumperGetRequest();

            $curl = $dumper->dump($request, 'https://api.example.com');

            expect($curl)->toContain('-L');
        });

        it('adds max-redirs when specified', function (): void {
            $dumper = new CurlDumper()->followRedirects()->maxRedirects(5);
            $request = createCurlDumperGetRequest();

            $curl = $dumper->dump($request, 'https://api.example.com');

            expect($curl)->toContain('-L')
                ->and($curl)->toContain('--max-redirs 5');
        });

        it('does not add max-redirs when follow redirects is disabled', function (): void {
            $dumper = new CurlDumper()->followRedirects(false)->maxRedirects(5);
            $request = createCurlDumperGetRequest();

            $curl = $dumper->dump($request, 'https://api.example.com');

            expect($curl)->not->toContain('-L')
                ->and($curl)->not->toContain('--max-redirs');
        });

        it('adds timeout flag', function (): void {
            $dumper = new CurlDumper()->timeout(30);
            $request = createCurlDumperGetRequest();

            $curl = $dumper->dump($request, 'https://api.example.com');

            expect($curl)->toContain('-m 30');
        });

        it('adds connect-timeout flag', function (): void {
            $dumper = new CurlDumper()->connectTimeout(10);
            $request = createCurlDumperGetRequest();

            $curl = $dumper->dump($request, 'https://api.example.com');

            expect($curl)->toContain('--connect-timeout 10');
        });

        it('chains multiple options', function (): void {
            $dumper = new CurlDumper()
                ->compressed()
                ->verbose()
                ->timeout(30)
                ->connectTimeout(5);
            $request = createCurlDumperGetRequest();

            $curl = $dumper->dump($request, 'https://api.example.com');

            expect($curl)->toContain('--compressed')
                ->and($curl)->toContain('-v')
                ->and($curl)->toContain('-m 30')
                ->and($curl)->toContain('--connect-timeout 5');
        });

        it('can disable options', function (): void {
            $dumper = new CurlDumper()
                ->compressed(true)
                ->compressed(false);
            $request = createCurlDumperGetRequest();

            $curl = $dumper->dump($request, 'https://api.example.com');

            expect($curl)->not->toContain('--compressed');
        });

        it('can clear timeout', function (): void {
            $dumper = new CurlDumper()
                ->timeout(30)
                ->timeout(null);
            $request = createCurlDumperGetRequest();

            $curl = $dumper->dump($request, 'https://api.example.com');

            expect($curl)->not->toContain('-m ');
        });
    });

    describe('Multiline Format', function (): void {
        it('formats output with backslash continuations', function (): void {
            $dumper = new CurlDumper();
            $request = createCurlDumperPostRequest();

            $curl = $dumper->dumpMultiline($request, 'https://api.example.com');

            expect($curl)->toContain(" \\\n")
                ->and($curl)->toContain('curl')
                ->and($curl)->toContain('-X POST');
        });

        it('indents all options in multiline format', function (): void {
            $dumper = new CurlDumper()->verbose()->compressed();
            $request = createCurlDumperPostRequest();

            $curl = $dumper->dumpMultiline($request, 'https://api.example.com');

            expect($curl)->toContain('  -v')
                ->and($curl)->toContain('  --compressed')
                ->and($curl)->toContain('  -d');
        });

        it('places URL at end in multiline format', function (): void {
            $dumper = new CurlDumper();
            $request = createCurlDumperGetRequest();

            $curl = $dumper->dumpMultiline($request, 'https://api.example.com');

            $lines = explode("\n", $curl);
            $lastLine = end($lines);

            expect($lastLine)->toContain('https://api.example.com/api/users');
        });

        it('includes query params in URL in multiline format', function (): void {
            $dumper = new CurlDumper();
            $request = createCurlDumperRequestWithQuery();

            $curl = $dumper->dumpMultiline($request, 'https://api.example.com');

            expect($curl)->toContain('page=1')
                ->and($curl)->toContain('limit=10');
        });

        it('includes headers in multiline format', function (): void {
            $dumper = new CurlDumper();
            $request = createCurlDumperRequestWithHeaders();

            $curl = $dumper->dumpMultiline($request, 'https://api.example.com');

            expect($curl)->toContain("  -H 'Authorization: Bearer token123'")
                ->and($curl)->toContain("  -H 'X-Custom-Header: custom-value'");
        });

        it('adds -k flag for insecure in multiline format', function (): void {
            $dumper = new CurlDumper()->insecure();
            $request = createCurlDumperGetRequest();

            $curl = $dumper->dumpMultiline($request, 'https://api.example.com');

            expect($curl)->toContain('  -k');
        });

        it('adds -s flag for silent in multiline format', function (): void {
            $dumper = new CurlDumper()->silent();
            $request = createCurlDumperGetRequest();

            $curl = $dumper->dumpMultiline($request, 'https://api.example.com');

            expect($curl)->toContain('  -s');
        });

        it('adds max-redirs in multiline format', function (): void {
            $dumper = new CurlDumper()->followRedirects()->maxRedirects(5);
            $request = createCurlDumperGetRequest();

            $curl = $dumper->dumpMultiline($request, 'https://api.example.com');

            expect($curl)->toContain('  -L')
                ->and($curl)->toContain('  --max-redirs 5');
        });

        it('adds timeout flag in multiline format', function (): void {
            $dumper = new CurlDumper()->timeout(30);
            $request = createCurlDumperGetRequest();

            $curl = $dumper->dumpMultiline($request, 'https://api.example.com');

            expect($curl)->toContain('  -m 30');
        });

        it('adds connect-timeout flag in multiline format', function (): void {
            $dumper = new CurlDumper()->connectTimeout(10);
            $request = createCurlDumperGetRequest();

            $curl = $dumper->dumpMultiline($request, 'https://api.example.com');

            expect($curl)->toContain('  --connect-timeout 10');
        });
    });

    describe('Edge Cases', function (): void {
        it('handles empty body', function (): void {
            $dumper = new CurlDumper();
            $request = createCurlDumperGetRequest();

            $curl = $dumper->dump($request, 'https://api.example.com');

            expect($curl)->not->toContain('-d');
        });

        it('handles special characters in body', function (): void {
            $request = new #[Post()] #[Json()] class() extends Request
            {
                public function endpoint(): string
                {
                    return '/api/test';
                }

                public function body(): array
                {
                    return ['message' => "Hello 'World' \"Test\" & <xml>"];
                }
            };

            $dumper = new CurlDumper();
            $curl = $dumper->dump($request, 'https://api.example.com');

            // Should be properly escaped
            expect($curl)->toContain('-d')
                ->and($curl)->toContain('Hello');
        });

        it('handles special characters in URL', function (): void {
            $request = new #[Get()] class() extends Request
            {
                public function endpoint(): string
                {
                    return '/api/users';
                }

                public function query(): array
                {
                    return ['filter' => 'name=John & age>20'];
                }
            };

            $dumper = new CurlDumper();
            $curl = $dumper->dump($request, 'https://api.example.com');

            // URL should be properly escaped
            expect($curl)->toContain("'https://");
        });

        it('uses additional headers from withHeader', function (): void {
            $request = createCurlDumperGetRequest()
                ->withHeader('X-Request-Id', 'abc123');

            $dumper = new CurlDumper();
            $curl = $dumper->dump($request, 'https://api.example.com');

            expect($curl)->toContain("-H 'X-Request-Id: abc123'");
        });

        it('uses additional query from withQuery', function (): void {
            $request = createCurlDumperGetRequest()
                ->withQuery('extra', 'param');

            $dumper = new CurlDumper();
            $curl = $dumper->dump($request, 'https://api.example.com');

            expect($curl)->toContain('extra=param');
        });
    });
});

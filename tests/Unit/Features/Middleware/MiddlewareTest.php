<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;
use Cline\Relay\Features\Middleware\HeaderMiddleware;
use Cline\Relay\Features\Middleware\MiddlewarePipeline;
use Cline\Relay\Features\Middleware\TimingMiddleware;
use Cline\Relay\Support\Attributes\Methods\Get;
use Cline\Relay\Support\Contracts\Middleware;
use Cline\Relay\Testing\MockResponse;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Support\Sleep;

function createMiddlewareRequest(): Request
{
    return new #[Get()] class() extends Request
    {
        public function endpoint(): string
        {
            return '/test';
        }
    };
}

describe('MiddlewarePipeline', function (): void {
    it('executes middleware in order', function (): void {
        $pipeline = new MiddlewarePipeline();
        $order = [];

        $pipeline->push(function (Request $request, Closure $next) use (&$order): Response {
            $order[] = 'first_before';
            $response = $next($request);
            $order[] = 'first_after';

            return $response;
        });

        $pipeline->push(function (Request $request, Closure $next) use (&$order): Response {
            $order[] = 'second_before';
            $response = $next($request);
            $order[] = 'second_after';

            return $response;
        });

        $core = function (Request $request) use (&$order): Response {
            $order[] = 'core';

            return MockResponse::json([]);
        };

        $pipeline->process(createMiddlewareRequest(), $core);

        expect($order)->toBe([
            'first_before',
            'second_before',
            'core',
            'second_after',
            'first_after',
        ]);
    });

    it('can prepend middleware', function (): void {
        $pipeline = new MiddlewarePipeline();
        $order = [];

        $pipeline->push(function ($req, $next) use (&$order) {
            $response = $next($req);
            $order[] = 'first';

            return $response;
        });
        $pipeline->prepend(function ($req, $next) use (&$order) {
            $response = $next($req);
            $order[] = 'prepended';

            return $response;
        });

        $pipeline->process(createMiddlewareRequest(), fn (): Response => MockResponse::json([]));

        expect($order)->toBe(['first', 'prepended']);
    });

    it('works with class-based middleware', function (): void {
        $pipeline = new MiddlewarePipeline();

        $middleware = new class() implements Middleware
        {
            public function handle(Request $request, Closure $next): Response
            {
                return $next($request->withHeader('X-Middleware', 'applied'));
            }
        };

        $pipeline->push($middleware);

        $capturedRequest = null;
        $pipeline->process(createMiddlewareRequest(), function (Request $request) use (&$capturedRequest): Response {
            $capturedRequest = $request;

            return MockResponse::json([]);
        });

        expect($capturedRequest->allHeaders())->toHaveKey('X-Middleware');
        expect($capturedRequest->allHeaders()['X-Middleware'])->toBe('applied');
    });

    it('can modify response', function (): void {
        $pipeline = new MiddlewarePipeline();

        $pipeline->push(fn (Request $request, Closure $next): Response => $next($request)->withHeader('X-Modified', 'true'));

        $response = $pipeline->process(
            createMiddlewareRequest(),
            fn (): Response => MockResponse::json([]),
        );

        expect($response->header('X-Modified'))->toBe('true');
    });

    it('counts middleware', function (): void {
        $pipeline = new MiddlewarePipeline();

        expect($pipeline->count())->toBe(0);
        expect($pipeline->hasMiddleware())->toBeFalse();

        $pipeline->push(fn ($req, $next) => $next($req));

        expect($pipeline->count())->toBe(1);
        expect($pipeline->hasMiddleware())->toBeTrue();
    });

    it('can be cleared', function (): void {
        $pipeline = new MiddlewarePipeline();
        $pipeline->push(fn ($req, $next) => $next($req));

        $pipeline->clear();

        expect($pipeline->count())->toBe(0);
    });
});

describe('TimingMiddleware', function (): void {
    it('records request duration', function (): void {
        $middleware = new TimingMiddleware();

        $response = $middleware->handle(
            createMiddlewareRequest(),
            function (Request $request): Response {
                Sleep::usleep(10_000); // 10ms

                return new Response(
                    new Psr7Response(200, [], '{}'),
                );
            },
        );

        expect($response->duration())->toBeGreaterThan(0);
    });
});

describe('HeaderMiddleware', function (): void {
    it('adds headers to request', function (): void {
        $middleware = new HeaderMiddleware([
            'X-API-Version' => 'v2',
            'X-Client' => 'test-client',
        ]);

        $capturedRequest = null;
        $middleware->handle(
            createMiddlewareRequest(),
            function (Request $request) use (&$capturedRequest): Response {
                $capturedRequest = $request;

                return MockResponse::json([]);
            },
        );

        expect($capturedRequest->allHeaders()['X-API-Version'])->toBe('v2');
        expect($capturedRequest->allHeaders()['X-Client'])->toBe('test-client');
    });
});

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Relay\Protocols\JsonRpc\IncrementingIdGenerator;
use Cline\Relay\Protocols\JsonRpc\RandomIdGenerator;
use Cline\Relay\Protocols\JsonRpc\UlidIdGenerator;
use Cline\Relay\Protocols\JsonRpc\UuidIdGenerator;
use Tests\Fixtures\Protocols\CreateUserJsonRpcRequest;
use Tests\Fixtures\Protocols\CustomEndpointJsonRpcRequest;
use Tests\Fixtures\Protocols\GetUsersJsonRpcRequest;
use Tests\Fixtures\Protocols\ListOrdersJsonRpcRequest;
use Tests\Fixtures\Protocols\NoParamsJsonRpcRequest;

describe('JsonRpcRequest', function (): void {
    afterEach(function (): void {
        // Reset static state between tests
        GetUsersJsonRpcRequest::useDefaultIdGenerator();
    });

    describe('body generation', function (): void {
        it('generates JSON-RPC 2.0 body structure', function (): void {
            $request = new GetUsersJsonRpcRequest();
            $body = $request->body();

            expect($body)->toHaveKey('jsonrpc', '2.0');
            expect($body)->toHaveKey('id');
            expect($body)->toHaveKey('method');
            expect($body)->toHaveKey('params');
        });

        it('includes params when provided', function (): void {
            $request = new GetUsersJsonRpcRequest();
            $body = $request->body();

            expect($body['params'])->toBe(['limit' => 10]);
        });

        it('excludes params key when empty', function (): void {
            $request = new NoParamsJsonRpcRequest();
            $body = $request->body();

            expect($body)->not->toHaveKey('params');
        });

        it('generates unique ID for each request', function (): void {
            $request1 = new GetUsersJsonRpcRequest();
            $request2 = new GetUsersJsonRpcRequest();

            expect($request1->body()['id'])->not->toBe($request2->body()['id']);
        });
    });

    describe('method name resolution', function (): void {
        it('derives method name from class name', function (): void {
            $request = new GetUsersJsonRpcRequest();

            expect($request->body()['method'])->toBe('get_users_json_rpc');
        });

        it('handles multi-word class names', function (): void {
            $request = new CreateUserJsonRpcRequest(['name' => 'John']);

            expect($request->body()['method'])->toBe('create_user_json_rpc');
        });

        it('adds method prefix when specified', function (): void {
            $request = new ListOrdersJsonRpcRequest();

            expect($request->body()['method'])->toBe('app.list_orders_json_rpc');
        });

        it('allows explicit method name', function (): void {
            $request = new GetUsersJsonRpcRequest()->withMethod('users.list');

            expect($request->body()['method'])->toBe('users.list');
        });
    });

    describe('ID handling', function (): void {
        it('allows explicit ID', function (): void {
            $request = new GetUsersJsonRpcRequest()->withId('custom-id-123');

            expect($request->body()['id'])->toBe('custom-id-123');
        });

        it('uses custom ID generator when set', function (): void {
            GetUsersJsonRpcRequest::useIdGenerator(
                new IncrementingIdGenerator(),
            );

            $request1 = new GetUsersJsonRpcRequest();
            $request2 = new GetUsersJsonRpcRequest();

            expect($request1->body()['id'])->toBe('1');
            expect($request2->body()['id'])->toBe('2');
        });

        it('explicit ID takes precedence over generator', function (): void {
            GetUsersJsonRpcRequest::useIdGenerator(
                new IncrementingIdGenerator(),
            );

            $request = new GetUsersJsonRpcRequest()->withId('explicit-id');

            expect($request->body()['id'])->toBe('explicit-id');
        });

        it('uses UlidIdGenerator by default', function (): void {
            expect(GetUsersJsonRpcRequest::getIdGenerator())->toBeInstanceOf(UlidIdGenerator::class);
        });

        it('caches resolved ID across multiple body() calls', function (): void {
            $request = new GetUsersJsonRpcRequest();

            $id1 = $request->body()['id'];
            $id2 = $request->body()['id'];
            $id3 = $request->body()['id'];

            expect($id1)->toBe($id2);
            expect($id2)->toBe($id3);
        });

        it('supports UuidIdGenerator', function (): void {
            GetUsersJsonRpcRequest::useIdGenerator(
                new UuidIdGenerator(),
            );

            $request = new GetUsersJsonRpcRequest();
            $id = $request->body()['id'];

            // UUID v4 format: xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
            expect($id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i');
        });

        it('supports RandomIdGenerator with custom length', function (): void {
            GetUsersJsonRpcRequest::useIdGenerator(
                new RandomIdGenerator(16),
            );

            $request = new GetUsersJsonRpcRequest();
            $id = $request->body()['id'];

            expect(mb_strlen($id))->toBe(16);
        });
    });

    describe('endpoint', function (): void {
        it('returns /rpc by default', function (): void {
            $request = new GetUsersJsonRpcRequest();

            expect($request->endpoint())->toBe('/rpc');
        });

        it('allows custom endpoint', function (): void {
            $request = new CustomEndpointJsonRpcRequest();

            expect($request->endpoint())->toBe('/api/v2/jsonrpc');
        });
    });

    describe('HTTP method and content type', function (): void {
        it('uses POST method', function (): void {
            $request = new GetUsersJsonRpcRequest();

            expect($request->method())->toBe('POST');
        });

        it('uses JSON content type', function (): void {
            $request = new GetUsersJsonRpcRequest();

            expect($request->contentType())->toBe('application/json');
        });
    });

    describe('constructor parameters', function (): void {
        it('accepts constructor parameters for dynamic requests', function (): void {
            $request = new CreateUserJsonRpcRequest(['name' => 'John', 'email' => 'john@example.com']);
            $body = $request->body();

            expect($body['params']['data'])->toBe([
                'name' => 'John',
                'email' => 'john@example.com',
            ]);
        });
    });
});

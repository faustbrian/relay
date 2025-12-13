<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Relay\Transport\Http\GuzzleDriver;
use Cline\Relay\Transport\Network\ConnectionConfig;
use Cline\Relay\Transport\Network\ProxyConfig;
use Cline\Relay\Transport\Network\SslConfig;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

describe('GuzzleDriver', function (): void {
    describe('Happy Paths', function (): void {
        test('sends request successfully and returns response', function (): void {
            // Arrange
            $mockHandler = new MockHandler([
                new Response(200, ['X-Test' => 'value'], '{"success": true}'),
            ]);
            $handlerStack = HandlerStack::create($mockHandler);
            $driver = new GuzzleDriver(handler: $handlerStack);
            $request = new Request('GET', 'https://api.test/users');

            // Act
            $response = $driver->sendRequest($request);

            // Assert
            expect($response)->toBeInstanceOf(ResponseInterface::class);
            expect($response->getStatusCode())->toBe(200);
            expect($response->getHeaderLine('X-Test'))->toBe('value');
            expect((string) $response->getBody())->toBe('{"success": true}');
        });

        test('creates driver with default settings', function (): void {
            // Act
            $driver = GuzzleDriver::create();

            // Assert
            expect($driver)->toBeInstanceOf(GuzzleDriver::class);
        });

        test('creates driver with proxy configuration', function (): void {
            // Arrange
            $proxy = new ProxyConfig('http://proxy.example.com:8080');

            // Act
            $driver = GuzzleDriver::withProxy($proxy);

            // Assert
            expect($driver)->toBeInstanceOf(GuzzleDriver::class);
        });

        test('creates driver with SSL configuration', function (): void {
            // Arrange
            $ssl = new SslConfig(verify: false);

            // Act
            $driver = GuzzleDriver::withSsl($ssl);

            // Assert
            expect($driver)->toBeInstanceOf(GuzzleDriver::class);
        });

        test('creates driver with connection configuration', function (): void {
            // Arrange
            $connection = new ConnectionConfig(keepAlive: true);

            // Act
            $driver = GuzzleDriver::withConnection($connection);

            // Assert
            expect($driver)->toBeInstanceOf(GuzzleDriver::class);
        });
    });

    describe('Sad Paths', function (): void {
        test('handles HTTP error responses without throwing', function (): void {
            // Arrange
            $mockHandler = new MockHandler([
                new Response(404, [], '{"error": "Not found"}'),
            ]);
            $handlerStack = HandlerStack::create($mockHandler);
            $driver = new GuzzleDriver(handler: $handlerStack);
            $request = new Request('GET', 'https://api.test/nonexistent');

            // Act
            $response = $driver->sendRequest($request);

            // Assert - Should NOT throw, returns error response
            expect($response->getStatusCode())->toBe(404);
            expect((string) $response->getBody())->toBe('{"error": "Not found"}');
        });

        test('handles server error responses without throwing', function (): void {
            // Arrange
            $mockHandler = new MockHandler([
                new Response(500, [], '{"error": "Internal server error"}'),
            ]);
            $handlerStack = HandlerStack::create($mockHandler);
            $driver = new GuzzleDriver(handler: $handlerStack);
            $request = new Request('GET', 'https://api.test/error');

            // Act
            $response = $driver->sendRequest($request);

            // Assert
            expect($response->getStatusCode())->toBe(500);
        });
    });

    describe('Edge Cases', function (): void {
        test('handles empty response body', function (): void {
            // Arrange
            $mockHandler = new MockHandler([
                new Response(204, []),
            ]);
            $handlerStack = HandlerStack::create($mockHandler);
            $driver = new GuzzleDriver(handler: $handlerStack);
            $request = new Request('DELETE', 'https://api.test/resource/1');

            // Act
            $response = $driver->sendRequest($request);

            // Assert
            expect($response->getStatusCode())->toBe(204);
            expect((string) $response->getBody())->toBe('');
        });

        test('applies custom timeout settings', function (): void {
            // Arrange
            $driver = new GuzzleDriver(timeout: 60, connectTimeout: 20);

            // Assert - Just verify driver is created with custom config
            expect($driver)->toBeInstanceOf(GuzzleDriver::class);
        });

        test('applies custom config array', function (): void {
            // Arrange
            $customConfig = ['debug' => false, 'allow_redirects' => true];
            $driver = new GuzzleDriver(config: $customConfig);

            // Assert
            expect($driver)->toBeInstanceOf(GuzzleDriver::class);
        });

        test('sends POST request with request body', function (): void {
            // Arrange
            $mockHandler = new MockHandler([
                new Response(201, [], '{"id": 123}'),
            ]);
            $handlerStack = HandlerStack::create($mockHandler);
            $driver = new GuzzleDriver(handler: $handlerStack);
            $request = new Request('POST', 'https://api.test/users', [], '{"name": "John"}');

            // Act
            $response = $driver->sendRequest($request);

            // Assert
            expect($response->getStatusCode())->toBe(201);
            expect((string) $response->getBody())->toBe('{"id": 123}');
        });
    });
});

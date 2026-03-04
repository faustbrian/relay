<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;
use Cline\Relay\Support\Attributes\Methods\Get;
use Cline\Relay\Support\Exceptions\GenericRequestException;
use GuzzleHttp\Exception\RequestException as GuzzleException;
use GuzzleHttp\Psr7\Request as Psr7Request;
use GuzzleHttp\Psr7\Response as Psr7Response;

function createExceptionTestRequest(string $endpoint = '/api/test'): Request
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

describe('RequestException', function (): void {
    describe('Happy Paths', function (): void {
        test('creates exception from request and response with correct status code', function (): void {
            // Arrange
            $request = createExceptionTestRequest('/api/users');
            $psrResponse = new Psr7Response(500, [], '{"error": "Server error"}');
            $response = new Response($psrResponse, $request);

            // Act
            $exception = GenericRequestException::fromResponse($request, $response);

            // Assert
            expect($exception)->toBeInstanceOf(GenericRequestException::class)
                ->and($exception->getCode())->toBe(500)
                ->and($exception->getMessage())->toContain('HTTP request returned status code 500')
                ->and($exception->request())->toBe($request)
                ->and($exception->response())->toBe($response)
                ->and($exception->status())->toBe(500);
        });

        test('creates exception from Guzzle exception with response', function (): void {
            // Arrange
            $request = createExceptionTestRequest('/api/users');
            $psrRequest = new Psr7Request('GET', '/api/users');
            $psrResponse = new Psr7Response(404, [], '{"error": "Not found"}');
            $guzzleException = new GuzzleException(
                'Client error: 404 Not Found',
                $psrRequest,
                $psrResponse,
            );

            // Act
            $exception = GenericRequestException::fromGuzzleException($guzzleException, $request);

            // Assert
            expect($exception)->toBeInstanceOf(GenericRequestException::class)
                ->and($exception->getMessage())->toBe('Client error: 404 Not Found')
                ->and($exception->request())->toBe($request)
                ->and($exception->response())->not->toBeNull()
                ->and($exception->response()->status())->toBe(404)
                ->and($exception->status())->toBe(404);
        });

        test('creates exception from Guzzle exception without response', function (): void {
            // Arrange
            $request = createExceptionTestRequest('/api/users');
            $psrRequest = new Psr7Request('GET', '/api/users');
            $guzzleException = new GuzzleException(
                'Connection timeout',
                $psrRequest,
            );

            // Act
            $exception = GenericRequestException::fromGuzzleException($guzzleException, $request);

            // Assert
            expect($exception)->toBeInstanceOf(GenericRequestException::class)
                ->and($exception->getMessage())->toBe('Connection timeout')
                ->and($exception->request())->toBe($request)
                ->and($exception->response())->toBeNull()
                ->and($exception->status())->toBe(0)
                ->and($exception->getCode())->toBe(0);
        });

        test('request method returns the original request', function (): void {
            // Arrange
            $request = createExceptionTestRequest('/api/posts');
            $psrResponse = new Psr7Response(403, [], '{"error": "Forbidden"}');
            $response = new Response($psrResponse, $request);

            // Act
            $exception = GenericRequestException::fromResponse($request, $response);

            // Assert
            expect($exception->request())->toBe($request)
                ->and($exception->request()->endpoint())->toBe('/api/posts');
        });

        test('response method returns the response when available', function (): void {
            // Arrange
            $request = createExceptionTestRequest('/api/data');
            $psrResponse = new Psr7Response(422, [], '{"errors": {}}');
            $response = new Response($psrResponse, $request);

            // Act
            $exception = GenericRequestException::fromResponse($request, $response);

            // Assert
            expect($exception->response())->toBe($response)
                ->and($exception->response()->status())->toBe(422);
        });

        test('status method returns correct status code from response', function (): void {
            // Arrange
            $request = createExceptionTestRequest('/api/users');
            $psrResponse = new Psr7Response(401, [], '{"error": "Unauthorized"}');
            $response = new Response($psrResponse, $request);

            // Act
            $exception = GenericRequestException::fromResponse($request, $response);

            // Assert
            expect($exception->status())->toBe(401);
        });
    });

    describe('Edge Cases', function (): void {
        test('handles exception with zero status code when no response', function (): void {
            // Arrange
            $request = createExceptionTestRequest('/api/timeout');
            $psrRequest = new Psr7Request('POST', '/api/timeout');
            $guzzleException = new GuzzleException(
                'Network error',
                $psrRequest,
            );

            // Act
            $exception = GenericRequestException::fromGuzzleException($guzzleException, $request);

            // Assert
            expect($exception->status())->toBe(0)
                ->and($exception->response())->toBeNull();
        });

        test('handles different HTTP status codes correctly', function (): void {
            // Arrange
            $request = createExceptionTestRequest('/api/test');
            $statuses = [200, 201, 301, 400, 401, 403, 404, 422, 429, 500, 502, 503];

            foreach ($statuses as $status) {
                // Act
                $psrResponse = new Psr7Response($status, [], '{}');
                $response = new Response($psrResponse, $request);
                $exception = GenericRequestException::fromResponse($request, $response);

                // Assert
                expect($exception->status())->toBe($status)
                    ->and($exception->getCode())->toBe($status);
            }
        });

        test('preserves Guzzle exception message in created exception', function (): void {
            // Arrange
            $request = createExceptionTestRequest('/api/test');
            $psrRequest = new Psr7Request('GET', '/api/test');
            $customMessage = 'Custom Guzzle error message with details';
            $guzzleException = new GuzzleException($customMessage, $psrRequest);

            // Act
            $exception = GenericRequestException::fromGuzzleException($guzzleException, $request);

            // Assert
            expect($exception->getMessage())->toBe($customMessage);
        });

        test('creates Response object from Guzzle PSR-7 response when available', function (): void {
            // Arrange
            $request = createExceptionTestRequest('/api/users');
            $psrRequest = new Psr7Request('GET', '/api/users');
            $psrResponse = new Psr7Response(500, ['X-Custom' => 'value'], '{"error": "test"}');
            $guzzleException = new GuzzleException('Server error', $psrRequest, $psrResponse);

            // Act
            $exception = GenericRequestException::fromGuzzleException($guzzleException, $request);

            // Assert
            expect($exception->response())->toBeInstanceOf(Response::class)
                ->and($exception->response()->header('X-Custom'))->toBe('value')
                ->and($exception->response()->body())->toBe('{"error": "test"}');
        });

        test('handles empty response body', function (): void {
            // Arrange
            $request = createExceptionTestRequest('/api/empty');
            $psrResponse = new Psr7Response(204, [], '');
            $response = new Response($psrResponse, $request);

            // Act
            $exception = GenericRequestException::fromResponse($request, $response);

            // Assert
            expect($exception->response()->body())->toBe('')
                ->and($exception->status())->toBe(204);
        });

        test('exception code defaults to response status', function (): void {
            // Arrange
            $request = createExceptionTestRequest('/api/users');
            $psrResponse = new Psr7Response(418, [], '{"error": "I\'m a teapot"}');
            $response = new Response($psrResponse, $request);

            // Act
            $exception = GenericRequestException::fromResponse($request, $response);

            // Assert
            expect($exception->getCode())->toBe(418)
                ->and($exception->status())->toBe(418);
        });
    });
});

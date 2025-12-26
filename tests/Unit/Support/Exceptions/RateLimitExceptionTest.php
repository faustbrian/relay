<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;
use Cline\Relay\Support\Exceptions\Client\RateLimitException;
use GuzzleHttp\Psr7\Response as Psr7Response;

function createRateLimitTestRequest_unique(string $endpoint = '/api/test'): Request
{
    return new class($endpoint) extends Request
    {
        public function __construct(
            private readonly string $ep,
        ) {}

        public function endpoint(): string
        {
            return $this->ep;
        }

        public function method(): string
        {
            return 'GET';
        }
    };
}

describe('RateLimitException', function (): void {
    describe('Happy Paths', function (): void {
        test('creates exception from exceeded with retry after, limit, and remaining', function (): void {
            // Arrange & Act
            $exception = RateLimitException::exceeded(60, 100, 0);

            // Assert - exceeded() creates a client-side exception (no response, so code is 0)
            expect($exception)->toBeInstanceOf(RateLimitException::class)
                ->and($exception->retryAfter())->toBe(60)
                ->and($exception->limit())->toBe(100)
                ->and($exception->remaining())->toBe(0)
                ->and($exception->getMessage())->toBe('Rate limit exceeded')
                ->and($exception->getCode())->toBe(0);
        });

        test('creates exception from exceeded with null retry after', function (): void {
            // Arrange & Act
            $exception = RateLimitException::exceeded(null, 50, 0);

            // Assert
            expect($exception)->toBeInstanceOf(RateLimitException::class)
                ->and($exception->retryAfter())->toBeNull()
                ->and($exception->limit())->toBe(50)
                ->and($exception->remaining())->toBe(0);
        });

        test('creates exception from response with rate limit headers', function (): void {
            // Arrange
            $request = createRateLimitTestRequest_unique('/api/users');
            $psrResponse = new Psr7Response(429, [
                'Retry-After' => '120',
                'X-RateLimit-Limit' => '1000',
                'X-RateLimit-Remaining' => '0',
            ], '{"error": "Too Many Requests"}');
            $response = new Response($psrResponse, $request);

            // Act
            $exception = RateLimitException::fromResponse($request, $response);

            // Assert
            expect($exception)->toBeInstanceOf(RateLimitException::class)
                ->and($exception->retryAfter())->toBe(120)
                ->and($exception->limit())->toBe(1_000)
                ->and($exception->remaining())->toBe(0)
                ->and($exception->getMessage())->toBe('Rate limit exceeded')
                ->and($exception->getCode())->toBe(429);
        });

        test('request method returns the original request when created from response', function (): void {
            // Arrange
            $request = createRateLimitTestRequest_unique('/api/posts');
            $psrResponse = new Psr7Response(429, [], '{"error": "Rate limit exceeded"}');
            $response = new Response($psrResponse, $request);

            // Act
            $exception = RateLimitException::fromResponse($request, $response);

            // Assert
            expect($exception->request())->toBe($request)
                ->and($exception->request()->endpoint())->toBe('/api/posts');
        });

        test('response method returns the response when created from response', function (): void {
            // Arrange
            $request = createRateLimitTestRequest_unique('/api/data');
            $psrResponse = new Psr7Response(429, [], '{"error": "Too many requests"}');
            $response = new Response($psrResponse, $request);

            // Act
            $exception = RateLimitException::fromResponse($request, $response);

            // Assert
            expect($exception->response())->toBe($response)
                ->and($exception->response()->status())->toBe(429);
        });

        test('isServerSide returns true when exception has response', function (): void {
            // Arrange
            $request = createRateLimitTestRequest_unique('/api/users');
            $psrResponse = new Psr7Response(429, [], '{}');
            $response = new Response($psrResponse, $request);

            // Act
            $exception = RateLimitException::fromResponse($request, $response);

            // Assert
            expect($exception->isServerSide())->toBeTrue()
                ->and($exception->isClientSide())->toBeFalse();
        });

        test('isClientSide returns true when exception has no response', function (): void {
            // Arrange & Act
            $exception = RateLimitException::exceeded(60, 100, 0);

            // Assert
            expect($exception->isClientSide())->toBeTrue()
                ->and($exception->isServerSide())->toBeFalse();
        });
    });

    describe('Edge Cases', function (): void {
        test('creates exception from response without rate limit headers', function (): void {
            // Arrange
            $request = createRateLimitTestRequest_unique('/api/users');
            $psrResponse = new Psr7Response(429, [], '{"error": "Rate limit"}');
            $response = new Response($psrResponse, $request);

            // Act
            $exception = RateLimitException::fromResponse($request, $response);

            // Assert
            expect($exception->retryAfter())->toBeNull()
                ->and($exception->limit())->toBeNull()
                ->and($exception->remaining())->toBeNull();
        });

        test('creates exception from response with partial rate limit headers', function (): void {
            // Arrange
            $request = createRateLimitTestRequest_unique('/api/users');
            $psrResponse = new Psr7Response(429, [
                'X-RateLimit-Limit' => '500',
            ], '{"error": "Rate limit"}');
            $response = new Response($psrResponse, $request);

            // Act
            $exception = RateLimitException::fromResponse($request, $response);

            // Assert
            expect($exception->retryAfter())->toBeNull()
                ->and($exception->limit())->toBe(500)
                ->and($exception->remaining())->toBeNull();
        });

        test('request method returns placeholder request when created via exceeded', function (): void {
            // Arrange & Act
            $exception = RateLimitException::exceeded(30, 100, 5);

            // Assert - exceeded() uses a placeholder request since ClientException requires one
            expect($exception->request())->toBeInstanceOf(Request::class)
                ->and($exception->request()->endpoint())->toBe('');
        });

        test('response method returns null when created via exceeded', function (): void {
            // Arrange & Act
            $exception = RateLimitException::exceeded(30, 100, 5);

            // Assert
            expect($exception->response())->toBeNull();
        });

        test('handles zero retry after seconds', function (): void {
            // Arrange & Act
            $exception = RateLimitException::exceeded(0, 100, 0);

            // Assert
            expect($exception->retryAfter())->toBe(0);
        });

        test('handles zero remaining requests', function (): void {
            // Arrange & Act
            $exception = RateLimitException::exceeded(60, 100, 0);

            // Assert
            expect($exception->remaining())->toBe(0);
        });

        test('handles non-zero remaining requests', function (): void {
            // Arrange & Act
            $exception = RateLimitException::exceeded(60, 100, 25);

            // Assert
            expect($exception->remaining())->toBe(25);
        });

        test('converts string rate limit headers to integers', function (): void {
            // Arrange
            $request = createRateLimitTestRequest_unique('/api/users');
            $psrResponse = new Psr7Response(429, [
                'Retry-After' => '300',
                'X-RateLimit-Limit' => '5000',
                'X-RateLimit-Remaining' => '42',
            ], '{}');
            $response = new Response($psrResponse, $request);

            // Act
            $exception = RateLimitException::fromResponse($request, $response);

            // Assert
            expect($exception->retryAfter())->toBe(300)->toBeInt()
                ->and($exception->limit())->toBe(5_000)->toBeInt()
                ->and($exception->remaining())->toBe(42)->toBeInt();
        });
    });
});

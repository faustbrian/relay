<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Testing;

use Cline\Relay\Core\Response;
use Cline\Relay\Support\Exceptions\JsonEncodingException;
use GuzzleHttp\Psr7\Response as Psr7Response;

use function ceil;
use function json_encode;
use function mb_strlen;
use function sprintf;

/**
 * Factory for creating mock responses in tests.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class MockResponse
{
    /**
     * Create a successful JSON response.
     *
     * @param array<string, mixed>  $data
     * @param array<string, string> $headers
     */
    public static function json(
        array $data,
        int $status = 200,
        array $headers = [],
    ): Response {
        $headers['Content-Type'] ??= 'application/json';

        $encoded = json_encode($data);

        if ($encoded === false) {
            throw JsonEncodingException::encodingFailed();
        }

        return new Response(
            new Psr7Response($status, $headers, $encoded),
        );
    }

    /**
     * Create a plain text response.
     *
     * @param array<string, string> $headers
     */
    public static function text(
        string $body,
        int $status = 200,
        array $headers = [],
    ): Response {
        $headers['Content-Type'] ??= 'text/plain';

        return new Response(
            new Psr7Response($status, $headers, $body),
        );
    }

    /**
     * Create an empty response.
     */
    public static function empty(int $status = 204): Response
    {
        return new Response(
            new Psr7Response($status),
        );
    }

    /**
     * Create a 404 Not Found response.
     *
     * @param array<string, mixed> $data
     */
    public static function notFound(array $data = ['error' => 'Not found']): Response
    {
        return self::json($data, 404);
    }

    /**
     * Create a 401 Unauthorized response.
     *
     * @param array<string, mixed> $data
     */
    public static function unauthorized(array $data = ['error' => 'Unauthorized']): Response
    {
        return self::json($data, 401);
    }

    /**
     * Create a 403 Forbidden response.
     *
     * @param array<string, mixed> $data
     */
    public static function forbidden(array $data = ['error' => 'Forbidden']): Response
    {
        return self::json($data, 403);
    }

    /**
     * Create a 422 Validation Error response.
     *
     * @param array<string, array<string>> $errors
     */
    public static function validationError(array $errors): Response
    {
        return self::json(['errors' => $errors], 422);
    }

    /**
     * Create a 429 Rate Limited response.
     */
    public static function rateLimited(int $retryAfter = 60): Response
    {
        $encoded = json_encode(['error' => 'Rate limit exceeded']);

        if ($encoded === false) {
            throw JsonEncodingException::encodingFailed();
        }

        return new Response(
            new Psr7Response(429, [
                'Content-Type' => 'application/json',
                'Retry-After' => (string) $retryAfter,
                'X-RateLimit-Remaining' => '0',
            ], $encoded),
        );
    }

    /**
     * Create a 500 Server Error response.
     *
     * @param array<string, mixed> $data
     */
    public static function serverError(array $data = ['error' => 'Internal server error']): Response
    {
        return self::json($data, 500);
    }

    /**
     * Create a 503 Service Unavailable response.
     *
     * @param array<string, mixed> $data
     */
    public static function serviceUnavailable(array $data = ['error' => 'Service temporarily unavailable']): Response
    {
        return self::json($data, 503);
    }

    /**
     * Create a file download response.
     */
    public static function file(
        string $content,
        string $filename,
        ?string $mimeType = null,
    ): Response {
        return new Response(
            new Psr7Response(200, [
                'Content-Type' => $mimeType ?? 'application/octet-stream',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
                'Content-Length' => (string) mb_strlen($content),
            ], $content),
        );
    }

    /**
     * Create a paginated response.
     *
     * @param array<int, mixed> $items
     */
    public static function paginated(
        array $items,
        int $page = 1,
        int $perPage = 15,
        int $total = 100,
    ): Response {
        return self::json([
            'data' => $items,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / $perPage),
            ],
        ]);
    }

    /**
     * Create a response with specific headers.
     *
     * @param array<string, string> $headers
     */
    public static function withHeaders(array $headers): Response
    {
        return new Response(
            new Psr7Response(200, $headers, '{}'),
        );
    }

    /**
     * Create a response with ETag for caching.
     */
    public static function cached(string $etag, ?string $lastModified = null): Response
    {
        $headers = ['ETag' => $etag];

        if ($lastModified !== null) {
            $headers['Last-Modified'] = $lastModified;
        }

        return new Response(
            new Psr7Response(200, $headers, '{}'),
        );
    }

    /**
     * Create a 304 Not Modified response.
     */
    public static function notModified(): Response
    {
        return new Response(
            new Psr7Response(304),
        );
    }
}

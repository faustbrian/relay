<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Observability\Debugging;

use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;

use const JSON_PRETTY_PRINT;

use function array_any;
use function explode;
use function http_build_query;
use function implode;
use function is_array;
use function json_encode;
use function sprintf;
use function strcasecmp;

/**
 * Debug formatter for requests and responses.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Debugger
{
    private const string REDACTED = '***REDACTED***';

    /** @var array<string> Headers to redact */
    private array $sensitiveHeaders = [
        'Authorization',
        'X-API-Key',
        'X-Api-Key',
        'Cookie',
        'Set-Cookie',
    ];

    /** @var array<string> Body keys to redact */
    private array $sensitiveBodyKeys = [
        'password',
        'secret',
        'token',
        'api_key',
        'apiKey',
        'access_token',
        'refresh_token',
        'credit_card',
        'card_number',
        'cvv',
        'ssn',
    ];

    /**
     * Set sensitive headers to redact.
     *
     * @param array<string> $headers
     */
    public function setSensitiveHeaders(array $headers): self
    {
        $this->sensitiveHeaders = $headers;

        return $this;
    }

    /**
     * Set sensitive body keys to redact.
     *
     * @param array<string> $keys
     */
    public function setSensitiveBodyKeys(array $keys): self
    {
        $this->sensitiveBodyKeys = $keys;

        return $this;
    }

    /**
     * Format a request for debugging.
     */
    public function formatRequest(Request $request, string $baseUrl): string
    {
        $output = [];
        $output[] = '┌─ Request ─────────────────────────────────────';
        $output[] = sprintf('│ %s %s%s', $request->method(), $baseUrl, $request->endpoint());

        // Query params
        $query = $request->allQuery();

        if ($query !== []) {
            $output[] = '│ Query: '.http_build_query($query);
        }

        $output[] = '│';

        // Headers
        $headers = $request->allHeaders();

        if ($headers !== []) {
            $output[] = '│ Headers:';

            foreach ($this->redactHeaders($headers) as $name => $value) {
                $output[] = sprintf('│   %s: %s', $name, $value);
            }

            $output[] = '│';
        }

        // Body
        $body = $request->body();

        if ($body !== null) {
            $output[] = '│ Body:';
            $redactedBody = $this->redactBody($body);
            $formatted = json_encode($redactedBody, JSON_PRETTY_PRINT);

            if ($formatted !== false) {
                foreach (explode("\n", $formatted) as $line) {
                    $output[] = '│   '.$line;
                }
            }
        }

        $output[] = '└───────────────────────────────────────────────';

        return implode("\n", $output);
    }

    /**
     * Format a response for debugging.
     */
    public function formatResponse(Response $response): string
    {
        $output = [];
        $output[] = '┌─ Response ────────────────────────────────────';

        $duration = $response->duration();
        $durationStr = $duration !== null ? sprintf(' (%sms)', $duration) : '';
        $output[] = sprintf('│ %d %s%s', $response->status(), $this->getStatusText($response->status()), $durationStr);
        $output[] = '│';

        // Headers
        $headers = $response->headers();

        if ($headers !== []) {
            $output[] = '│ Headers:';

            foreach ($this->redactHeaders($headers) as $name => $value) {
                $output[] = sprintf('│   %s: %s', $name, $value);
            }

            $output[] = '│';
        }

        // Body
        $json = $response->json();

        if (is_array($json) && $json !== []) {
            $output[] = '│ Body:';
            $redactedBody = $this->redactBody($json);
            $formatted = json_encode($redactedBody, JSON_PRETTY_PRINT);

            if ($formatted !== false) {
                foreach (explode("\n", $formatted) as $line) {
                    $output[] = '│   '.$line;
                }
            }
        }

        $output[] = '└───────────────────────────────────────────────';

        return implode("\n", $output);
    }

    /**
     * Redact sensitive headers.
     *
     * @param  array<string, array<string>|string> $headers
     * @return array<string, string>
     */
    private function redactHeaders(array $headers): array
    {
        $redacted = [];

        foreach ($headers as $name => $value) {
            // PSR-7 headers can be arrays, flatten to string
            $valueStr = is_array($value) ? implode(', ', $value) : $value;
            $redacted[$name] = $this->isSensitiveHeader($name) ? self::REDACTED : $valueStr;
        }

        return $redacted;
    }

    /**
     * Redact sensitive body keys.
     *
     * @param  array<mixed, mixed> $body
     * @return array<mixed, mixed>
     */
    private function redactBody(array $body): array
    {
        $redacted = [];

        foreach ($body as $key => $value) {
            if ($this->isSensitiveBodyKey((string) $key)) {
                $redacted[$key] = self::REDACTED;
            } elseif (is_array($value)) {
                $redacted[$key] = $this->redactBody($value);
            } else {
                $redacted[$key] = $value;
            }
        }

        return $redacted;
    }

    /**
     * Check if a header is sensitive.
     */
    private function isSensitiveHeader(string $name): bool
    {
        return array_any($this->sensitiveHeaders, fn ($sensitive): bool => strcasecmp($name, $sensitive) === 0);
    }

    /**
     * Check if a body key is sensitive.
     */
    private function isSensitiveBodyKey(string $key): bool
    {
        return array_any($this->sensitiveBodyKeys, fn ($sensitive): bool => strcasecmp($key, $sensitive) === 0);
    }

    /**
     * Get the status text for a status code.
     */
    private function getStatusText(int $status): string
    {
        return match ($status) {
            200 => 'OK',
            201 => 'Created',
            204 => 'No Content',
            301 => 'Moved Permanently',
            302 => 'Found',
            304 => 'Not Modified',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            default => '',
        };
    }
}

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Observability\Debugging;

use Cline\Relay\Core\Request;
use Cline\Relay\Support\Exceptions\JsonEncodingException;
use Cline\Relay\Support\Exceptions\ValueFormatException;

use const JSON_PRETTY_PRINT;

use function array_keys;
use function get_debug_type;
use function implode;
use function in_array;
use function is_array;
use function is_bool;
use function is_object;
use function is_scalar;
use function json_encode;
use function mb_strtoupper;
use function method_exists;
use function throw_if;

/**
 * Converts requests to Hurl file format for debugging and testing.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://hurl.dev/
 */
final class HurlDumper
{
    private ?string $basicAuthUser = null;

    private ?string $basicAuthPassword = null;

    private bool $prettyPrintJson = true;

    /**
     * Set basic auth credentials to include in output.
     */
    public function withBasicAuth(string $username, string $password): self
    {
        $this->basicAuthUser = $username;
        $this->basicAuthPassword = $password;

        return $this;
    }

    /**
     * Enable/disable pretty printing of JSON bodies.
     */
    public function prettyPrintJson(bool $pretty = true): self
    {
        $this->prettyPrintJson = $pretty;

        return $this;
    }

    /**
     * Dump a request to Hurl format.
     */
    public function dump(Request $request, string $baseUrl): string
    {
        $lines = [];

        // Method and URL
        $method = mb_strtoupper($request->method());
        $url = $baseUrl.$request->endpoint();
        $lines[] = $method.' '.$url;

        // Headers (directly after URL, no section marker)
        $headers = $request->allHeaders();
        $contentType = $request->contentType();
        $hasContentTypeHeader = in_array('Content-Type', array_keys($headers), true)
            || in_array('content-type', array_keys($headers), true);

        foreach ($headers as $name => $value) {
            $lines[] = $name.': '.$value;
        }

        // Add Content-Type if not already present
        if ($contentType !== null && !$hasContentTypeHeader) {
            $lines[] = 'Content-Type: '.$contentType;
        }

        // Query parameters section
        $query = $request->allQuery();

        if ($query !== []) {
            $lines[] = '[QueryStringParams]';

            foreach ($query as $key => $value) {
                $lines[] = $key.': '.$this->formatValue($value);
            }
        }

        // Basic auth section
        if ($this->basicAuthUser !== null && $this->basicAuthPassword !== null) {
            $lines[] = '[BasicAuth]';
            $lines[] = $this->basicAuthUser.': '.$this->basicAuthPassword;
        }

        // Body (must be last)
        $body = $request->body();

        if ($body !== null && $body !== []) {
            $jsonFlags = $this->prettyPrintJson ? JSON_PRETTY_PRINT : 0;
            $encoded = json_encode($body, $jsonFlags);
            throw_if($encoded === false, JsonEncodingException::requestBodyFailed());

            $lines[] = $encoded;
        }

        return implode("\n", $lines);
    }

    /**
     * Dump multiple requests to a Hurl file.
     *
     * @param array<array{request: Request, baseUrl: string}> $requests
     */
    public function dumpMultiple(array $requests): string
    {
        $parts = [];

        foreach ($requests as $item) {
            $parts[] = $this->dump($item['request'], $item['baseUrl']);
        }

        return implode("\n\n", $parts);
    }

    /**
     * Format a value for Hurl output.
     */
    private function formatValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            $encoded = json_encode($value);
            throw_if($encoded === false, JsonEncodingException::arrayValueFailed());

            return $encoded;
        }

        if (is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))) {
            return (string) $value;
        }

        throw ValueFormatException::cannotFormat(get_debug_type($value));
    }
}

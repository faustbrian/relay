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

use function array_keys;
use function escapeshellarg;
use function http_build_query;
use function implode;
use function in_array;
use function json_encode;
use function mb_strtoupper;
use function sprintf;
use function throw_if;

/**
 * Converts requests to curl commands for debugging.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class CurlDumper
{
    private bool $compressed = false;

    private bool $insecure = false;

    private bool $verbose = false;

    private bool $silent = false;

    private bool $followRedirects = true;

    private ?int $maxRedirects = null;

    private ?int $timeout = null;

    private ?int $connectTimeout = null;

    /**
     * Enable gzip/deflate compression.
     */
    public function compressed(bool $compressed = true): self
    {
        $this->compressed = $compressed;

        return $this;
    }

    /**
     * Disable SSL verification.
     */
    public function insecure(bool $insecure = true): self
    {
        $this->insecure = $insecure;

        return $this;
    }

    /**
     * Enable verbose output.
     */
    public function verbose(bool $verbose = true): self
    {
        $this->verbose = $verbose;

        return $this;
    }

    /**
     * Enable silent mode.
     */
    public function silent(bool $silent = true): self
    {
        $this->silent = $silent;

        return $this;
    }

    /**
     * Enable/disable following redirects.
     */
    public function followRedirects(bool $follow = true): self
    {
        $this->followRedirects = $follow;

        return $this;
    }

    /**
     * Set max redirects.
     */
    public function maxRedirects(?int $max): self
    {
        $this->maxRedirects = $max;

        return $this;
    }

    /**
     * Set request timeout.
     */
    public function timeout(?int $seconds): self
    {
        $this->timeout = $seconds;

        return $this;
    }

    /**
     * Set connection timeout.
     */
    public function connectTimeout(?int $seconds): self
    {
        $this->connectTimeout = $seconds;

        return $this;
    }

    /**
     * Dump a request to a curl command.
     */
    public function dump(Request $request, string $baseUrl): string
    {
        $parts = ['curl'];

        // Method (only include if not GET)
        $method = mb_strtoupper($request->method());

        if ($method !== 'GET') {
            $parts[] = '-X '.$method;
        }

        // URL with query string
        $url = $baseUrl.$request->endpoint();
        $query = $request->allQuery();

        if ($query !== []) {
            $url .= '?'.http_build_query($query);
        }

        // Headers
        $headers = $request->allHeaders();

        foreach ($headers as $name => $value) {
            $parts[] = sprintf('-H %s', escapeshellarg($name.': '.$value));
        }

        // Content-Type header if not already set
        $contentType = $request->contentType();
        $hasContentTypeHeader = in_array('Content-Type', array_keys($headers), true)
            || in_array('content-type', array_keys($headers), true);

        if ($contentType !== null && !$hasContentTypeHeader) {
            $parts[] = sprintf('-H %s', escapeshellarg('Content-Type: '.$contentType));
        }

        // Body
        $body = $request->body();

        if ($body !== null && $body !== []) {
            $encoded = json_encode($body);

            throw_if($encoded === false, JsonEncodingException::requestBodyFailed());

            $parts[] = sprintf('-d %s', escapeshellarg($encoded));
        }

        // Flags
        if ($this->compressed) {
            $parts[] = '--compressed';
        }

        if ($this->insecure) {
            $parts[] = '-k';
        }

        if ($this->verbose) {
            $parts[] = '-v';
        }

        if ($this->silent) {
            $parts[] = '-s';
        }

        if ($this->followRedirects) {
            $parts[] = '-L';

            if ($this->maxRedirects !== null) {
                $parts[] = '--max-redirs '.$this->maxRedirects;
            }
        }

        if ($this->timeout !== null) {
            $parts[] = '-m '.$this->timeout;
        }

        if ($this->connectTimeout !== null) {
            $parts[] = '--connect-timeout '.$this->connectTimeout;
        }

        // URL at the end
        $parts[] = escapeshellarg($url);

        return implode(' ', $parts);
    }

    /**
     * Dump to a multi-line format for readability.
     */
    public function dumpMultiline(Request $request, string $baseUrl): string
    {
        $parts = ['curl'];

        // Method
        $method = mb_strtoupper($request->method());

        if ($method !== 'GET') {
            $parts[] = '  -X '.$method;
        }

        // Headers
        $headers = $request->allHeaders();

        foreach ($headers as $name => $value) {
            $parts[] = sprintf('  -H %s', escapeshellarg($name.': '.$value));
        }

        // Content-Type header if not already set
        $contentType = $request->contentType();
        $hasContentTypeHeader = in_array('Content-Type', array_keys($headers), true)
            || in_array('content-type', array_keys($headers), true);

        if ($contentType !== null && !$hasContentTypeHeader) {
            $parts[] = sprintf('  -H %s', escapeshellarg('Content-Type: '.$contentType));
        }

        // Body
        $body = $request->body();

        if ($body !== null && $body !== []) {
            $encoded = json_encode($body);

            throw_if($encoded === false, JsonEncodingException::requestBodyFailed());

            $parts[] = sprintf('  -d %s', escapeshellarg($encoded));
        }

        // Flags
        if ($this->compressed) {
            $parts[] = '  --compressed';
        }

        if ($this->insecure) {
            $parts[] = '  -k';
        }

        if ($this->verbose) {
            $parts[] = '  -v';
        }

        if ($this->silent) {
            $parts[] = '  -s';
        }

        if ($this->followRedirects) {
            $parts[] = '  -L';

            if ($this->maxRedirects !== null) {
                $parts[] = '  --max-redirs '.$this->maxRedirects;
            }
        }

        if ($this->timeout !== null) {
            $parts[] = '  -m '.$this->timeout;
        }

        if ($this->connectTimeout !== null) {
            $parts[] = '  --connect-timeout '.$this->connectTimeout;
        }

        // URL with query string
        $url = $baseUrl.$request->endpoint();
        $query = $request->allQuery();

        if ($query !== []) {
            $url .= '?'.http_build_query($query);
        }

        $parts[] = '  '.escapeshellarg($url);

        return implode(" \\\n", $parts);
    }
}

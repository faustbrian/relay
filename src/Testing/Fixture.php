<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Testing;

use Cline\Relay\Core\Response;
use Cline\Relay\Support\Exceptions\FixtureException;
use GuzzleHttp\Psr7\Response as Psr7Response;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;

use function array_key_exists;
use function array_keys;
use function dirname;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function is_array;
use function is_callable;
use function is_dir;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function mkdir;
use function preg_replace;
use function strcasecmp;
use function unlink;

/**
 * Fixture for recording and replaying API responses with redaction support.
 *
 * @author Brian Faust <brian@cline.sh>
 */
class Fixture
{
    private static string $fixturePath = 'tests/Fixtures/Saloon';

    /**
     * Create a new Fixture instance.
     */
    public function __construct(
        private readonly string $name,
    ) {}

    /**
     * Create a fixture from a name.
     */
    public static function make(string $name): self
    {
        return new self($name);
    }

    /**
     * Set the global fixture path.
     */
    public static function setFixturePath(string $path): void
    {
        self::$fixturePath = $path;
    }

    /**
     * Get the global fixture path.
     */
    public static function getFixturePath(): string
    {
        return self::$fixturePath;
    }

    /**
     * Get the fixture name.
     */
    public function defineName(): string
    {
        return $this->name;
    }

    /**
     * Resolve the fixture to a response.
     */
    public function resolve(): Response
    {
        $path = $this->getFilePath();

        if (file_exists($path)) {
            return $this->loadFromFile($path);
        }

        if (MockConfig::shouldThrowOnMissingFixtures()) {
            throw FixtureException::missingFixture($this->name, $path);
        }

        // Record the fixture
        return $this->record();
    }

    /**
     * Check if the fixture exists.
     */
    public function exists(): bool
    {
        return file_exists($this->getFilePath());
    }

    /**
     * Get the file path for this fixture.
     */
    public function getFilePath(): string
    {
        return self::$fixturePath.'/'.$this->name.'.json';
    }

    /**
     * Store a response to the fixture file.
     */
    public function store(Response $response): void
    {
        $path = $this->getFilePath();
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        $data = [
            'status' => $response->status(),
            'headers' => $response->headers(),
            'body' => $response->json() ?: [],
        ];

        // Apply redactions before storing
        $data = $this->applyRedactions($data);

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        file_put_contents($path, $json);
    }

    /**
     * Delete the fixture file.
     */
    public function delete(): bool
    {
        $path = $this->getFilePath();

        if (file_exists($path)) {
            return unlink($path);
        }

        return false;
    }

    /**
     * Define sensitive headers to redact.
     *
     * @return array<string, callable|string>
     */
    protected function defineSensitiveHeaders(): array
    {
        return [];
    }

    /**
     * Define sensitive JSON parameters to redact.
     *
     * @return array<string, callable|string>
     */
    protected function defineSensitiveJsonParameters(): array
    {
        return [];
    }

    /**
     * Define sensitive regex patterns to redact.
     *
     * @return array<string, string>
     */
    protected function defineSensitiveRegexPatterns(): array
    {
        return [];
    }

    /**
     * Load a response from a fixture file.
     */
    private function loadFromFile(string $path): Response
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw FixtureException::unableToRead($path); // @codeCoverageIgnore
        }

        $decoded = json_decode($contents, true);

        if ($decoded === null || !is_array($decoded)) {
            throw FixtureException::invalidJson($path);
        }

        /** @var array<string, mixed> $decoded */
        // Apply redactions when loading
        $data = $this->applyRedactions($decoded);

        $status = $data['status'] ?? 200;
        $headers = $data['headers'] ?? [];
        $body = $data['body'] ?? [];

        if (!is_int($status)) {
            $status = 200;
        }

        if (!is_array($headers)) {
            $headers = [];
        }

        /** @var array<array<string>|string> $headers */
        $encodedBody = json_encode($body);

        if ($encodedBody === false) {
            $encodedBody = '{}';
        }

        return new Response(
            new Psr7Response($status, $headers, $encodedBody),
        );
    }

    /**
     * Record a real response to a fixture file.
     */
    private function record(): Response
    {
        // This would make a real request - for now, throw
        throw FixtureException::recordingDisabled($this->name);
    }

    /**
     * Apply all redactions to fixture data.
     *
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function applyRedactions(array $data): array
    {
        // Redact headers
        if (array_key_exists('headers', $data) && is_array($data['headers'])) {
            /** @var array<string, mixed> $headers */
            $headers = $data['headers'];
            $data['headers'] = $this->redactHeaders($headers);
        }

        // Redact body JSON parameters
        if (array_key_exists('body', $data) && is_array($data['body'])) {
            /** @var array<string, mixed> $body */
            $body = $data['body'];
            $data['body'] = $this->redactJsonParameters($body);
        }

        // Redact body using regex patterns
        if (array_key_exists('body', $data)) {
            $body = $data['body'];

            if (is_array($body)) {
                /** @var array<string, mixed> $body */
                $data['body'] = $this->redactRegexPatterns($body);
            } elseif (is_string($body)) {
                $data['body'] = $this->redactRegexPatterns($body);
            }
        }

        return $data;
    }

    /**
     * Redact sensitive headers.
     *
     * @param  array<string, mixed> $headers
     * @return array<string, mixed>
     */
    private function redactHeaders(array $headers): array
    {
        $sensitive = $this->defineSensitiveHeaders();

        foreach (array_keys($headers) as $name) {
            foreach ($sensitive as $sensitiveHeader => $replacement) {
                if (strcasecmp($name, $sensitiveHeader) === 0) {
                    $headers[$name] = is_callable($replacement) ? $replacement() : $replacement;

                    break;
                }
            }
        }

        return $headers;
    }

    /**
     * Redact sensitive JSON parameters.
     *
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function redactJsonParameters(array $data): array
    {
        $sensitive = $this->defineSensitiveJsonParameters();

        return $this->redactNestedParameters($data, $sensitive);
    }

    /**
     * Recursively redact nested parameters.
     *
     * @param  array<string, mixed>           $data
     * @param  array<string, callable|string> $sensitive
     * @return array<string, mixed>
     */
    private function redactNestedParameters(array $data, array $sensitive): array
    {
        foreach ($data as $key => $value) {
            if (array_key_exists($key, $sensitive)) {
                $replacement = $sensitive[$key];
                $data[$key] = is_callable($replacement) ? $replacement() : $replacement;
            } elseif (is_array($value)) {
                /** @var array<string, mixed> $value */
                $data[$key] = $this->redactNestedParameters($value, $sensitive);
            }
        }

        return $data;
    }

    /**
     * Redact using regex patterns.
     *
     * @param  array<string, mixed>|string $data
     * @return array<string, mixed>|string
     */
    private function redactRegexPatterns(array|string $data): array|string
    {
        $patterns = $this->defineSensitiveRegexPatterns();

        if ($patterns === []) {
            return $data;
        }

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_string($value)) {
                    $redacted = $value;

                    foreach ($patterns as $pattern => $replacement) {
                        $result = preg_replace($pattern, $replacement, (string) $redacted);
                        $redacted = $result ?? $redacted;
                    }

                    $data[$key] = $redacted;
                } elseif (is_array($value)) {
                    /** @var array<string, mixed> $value */
                    $data[$key] = $this->redactRegexPatterns($value);
                }
            }

            return $data;
        }

        foreach ($patterns as $pattern => $replacement) {
            $result = preg_replace($pattern, $replacement, (string) $data);
            $data = $result ?? $data;
        }

        return $data;
    }
}

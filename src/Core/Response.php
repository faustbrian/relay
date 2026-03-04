<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Core;

use Cline\Relay\Features\RateLimiting\RateLimitInfo;
use Cline\Relay\Support\Exceptions\FileWriteException;
use Cline\Relay\Support\Exceptions\ResponseException;
use DateTimeImmutable;
use Generator;
use GuzzleHttp\Psr7\Response as Psr7Response;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Macroable;
use Psr\Http\Message\ResponseInterface;
use stdClass;

use function array_map;
use function base64_encode;
use function collect;
use function data_get;
use function data_set;
use function dd;
use function dirname;
use function dump;
use function fclose;
use function file_put_contents;
use function fopen;
use function fwrite;
use function is_array;
use function is_dir;
use function json_decode;
use function json_encode;
use function mb_trim;
use function preg_match;
use function rewind;
use function str_contains;
use function throw_if;
use function throw_unless;
use function urldecode;

/**
 * Response wrapper with typed accessors and convenience methods.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Response
{
    use Macroable;

    private ?float $duration = null;

    private ?string $traceId = null;

    private ?string $spanId = null;

    private ?string $idempotencyKey = null;

    private bool $wasIdempotentReplay = false;

    private bool $fromCache = false;

    public function __construct(
        private readonly ResponseInterface $psrResponse,
        private readonly ?Request $request = null,
    ) {}

    // ===== Factory Methods =====
    /**
     * Create a response from array data.
     *
     * @param array<string, mixed>  $data
     * @param array<string, string> $headers
     */
    public static function make(array $data, int $status = 200, array $headers = []): self
    {
        $body = json_encode($data);

        throw_if($body === false, ResponseException::jsonEncodingFailed());

        $headers['Content-Type'] ??= 'application/json';

        $psrResponse = new Psr7Response($status, $headers, $body);

        return new self($psrResponse);
    }

    /**
     * Create a new response instance with the given request attached.
     */
    public function withRequest(Request $request): self
    {
        $response = new self($this->psrResponse, $request);
        $response->duration = $this->duration;
        $response->traceId = $this->traceId;
        $response->spanId = $this->spanId;
        $response->idempotencyKey = $this->idempotencyKey;
        $response->wasIdempotentReplay = $this->wasIdempotentReplay;
        $response->fromCache = $this->fromCache;

        return $response;
    }

    /**
     * Get the HTTP status code.
     */
    public function status(): int
    {
        return $this->psrResponse->getStatusCode();
    }

    /**
     * Get all headers as an associative array.
     *
     * @return array<string, list<string>>
     */
    public function headers(): array
    {
        $headers = $this->psrResponse->getHeaders();

        // Ensure all header values are lists (not just arrays with string keys)
        /** @var array<string, list<string>> */
        return array_map(array_values(...), $headers);
    }

    /**
     * Get a specific header value.
     */
    public function header(string $name): ?string
    {
        $values = $this->psrResponse->getHeader($name);

        return $values !== [] ? $values[0] : null;
    }

    /**
     * Get the raw response body.
     */
    public function body(): string
    {
        return (string) $this->psrResponse->getBody();
    }

    /**
     * Parse the response body as JSON.
     *
     * When $key is null, returns the full decoded response as an array.
     * When $key is provided, returns the value at that key path (which can be any type).
     */
    public function json(?string $key = null): mixed
    {
        $data = json_decode($this->body(), true);

        if ($key === null) {
            return $data;
        }

        return data_get($data, $key);
    }

    /**
     * Parse the response body as JSON object.
     */
    public function object(): stdClass
    {
        $result = json_decode($this->body());

        throw_unless($result instanceof stdClass, ResponseException::invalidJsonObject());

        return $result;
    }

    /**
     * Parse the response body as a Laravel Collection.
     *
     * @return Collection<string, mixed>
     */
    public function collect(?string $key = null): Collection
    {
        $data = $this->json($key);

        return collect(is_array($data) ? $data : [$data]);
    }

    /**
     * Map the response to a DTO.
     *
     * @template T of object
     *
     * @param  class-string<T> $class
     * @return T
     */
    public function dto(string $class): object
    {
        return new $class($this->json());
    }

    /**
     * Create a DTO from the response using the request's createDtoFromResponse method.
     *
     * Returns the DTO even if the response failed.
     */
    public function toDto(): mixed
    {
        if (!$this->request instanceof Request) {
            return null;
        }

        return $this->request->createDtoFromResponse($this);
    }

    /**
     * Create a DTO from the response or throw an exception if the response failed.
     *
     * @throws ResponseException If the response failed
     */
    public function dtoOrFail(): mixed
    {
        if ($this->failed()) {
            throw ResponseException::cannotCreateDtoFromFailedResponse($this->status(), $this->body());
        }

        throw_unless($this->request instanceof Request, ResponseException::noRequestAssociatedWithResponse());

        $dto = $this->request->createDtoFromResponse($this);

        if ($dto === null) {
            throw ResponseException::requestDoesNotImplementDtoCreation($this->request::class);
        }

        return $dto;
    }

    /**
     * Map the response to a collection of DTOs.
     *
     * @template T of object
     *
     * @param  class-string<T>    $class
     * @param  null|string        $key   The key to extract from the response
     * @return Collection<int, T>
     */
    public function dtoCollection(string $class, ?string $key = null): Collection
    {
        $data = $key !== null ? $this->json($key) : $this->json();

        if (!is_array($data)) {
            return collect();
        }

        return collect($data)->map(
            /**
             * @return T
             */
            function (mixed $item) use ($class): object {
                throw_unless(is_array($item), ResponseException::collectionItemMustBeArray());

                return new $class($item);
            },
        );
    }

    /**
     * Check if the response was successful (2xx).
     */
    public function ok(): bool
    {
        return $this->status() >= 200 && $this->status() < 300;
    }

    /**
     * Alias for ok().
     */
    public function successful(): bool
    {
        return $this->ok();
    }

    /**
     * Check if the response failed (4xx or 5xx).
     */
    public function failed(): bool
    {
        if ($this->clientError()) {
            return true;
        }

        return $this->serverError();
    }

    /**
     * Check if the response was a client error (4xx).
     */
    public function clientError(): bool
    {
        return $this->status() >= 400 && $this->status() < 500;
    }

    /**
     * Check if the response was a server error (5xx).
     */
    public function serverError(): bool
    {
        return $this->status() >= 500;
    }

    /**
     * Throw an exception if the response failed.
     *
     * @throws ResponseException
     */
    public function throw(): self
    {
        if ($this->failed()) {
            throw ResponseException::httpRequestFailed($this->status(), $this->body());
        }

        return $this;
    }

    /**
     * Check if the response is a redirect (3xx).
     */
    public function redirect(): bool
    {
        return $this->status() >= 300 && $this->status() < 400;
    }

    /**
     * Get the original request.
     */
    public function request(): ?Request
    {
        return $this->request;
    }

    /**
     * Get the underlying PSR-7 response.
     */
    public function toPsrResponse(): ResponseInterface
    {
        return $this->psrResponse;
    }

    // ===== Timing =====

    /**
     * Get the request duration in milliseconds.
     */
    public function duration(): ?float
    {
        return $this->duration;
    }

    /**
     * Set the request duration.
     *
     * @internal
     */
    public function setDuration(float $duration): self
    {
        $this->duration = $duration;

        return $this;
    }

    // ===== Conditional Request Support =====

    /**
     * Get the ETag header value.
     */
    public function etag(): ?string
    {
        return $this->header('ETag');
    }

    /**
     * Get the Last-Modified header as a DateTimeImmutable.
     */
    public function lastModified(): ?DateTimeImmutable
    {
        $value = $this->header('Last-Modified');

        if ($value === null) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('D, d M Y H:i:s \G\M\T', $value);

        return $date !== false ? $date : null;
    }

    /**
     * Check if the response was 304 Not Modified.
     */
    public function wasNotModified(): bool
    {
        return $this->status() === 304;
    }

    /**
     * Check if the response was served from cache.
     */
    public function fromCache(): bool
    {
        return $this->fromCache;
    }

    /**
     * Mark as served from cache.
     *
     * @internal
     */
    public function setFromCache(bool $fromCache): self
    {
        $this->fromCache = $fromCache;

        return $this;
    }

    // ===== Tracing =====

    /**
     * Get the trace ID.
     */
    public function traceId(): ?string
    {
        return $this->traceId;
    }

    /**
     * Set the trace ID.
     *
     * @internal
     */
    public function setTraceId(string $traceId): self
    {
        $this->traceId = $traceId;

        return $this;
    }

    /**
     * Get the span ID.
     */
    public function spanId(): ?string
    {
        return $this->spanId;
    }

    /**
     * Set the span ID.
     *
     * @internal
     */
    public function setSpanId(string $spanId): self
    {
        $this->spanId = $spanId;

        return $this;
    }

    // ===== Idempotency =====

    /**
     * Get the idempotency key used for this request.
     */
    public function idempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    /**
     * Set the idempotency key.
     *
     * @internal
     */
    public function setIdempotencyKey(string $key): self
    {
        $this->idempotencyKey = $key;

        return $this;
    }

    /**
     * Check if this response was an idempotent replay.
     */
    public function wasIdempotentReplay(): bool
    {
        return $this->wasIdempotentReplay;
    }

    /**
     * Mark as idempotent replay.
     *
     * @internal
     */
    public function setWasIdempotentReplay(bool $wasReplay): self
    {
        $this->wasIdempotentReplay = $wasReplay;

        return $this;
    }

    // ===== Rate Limiting =====

    /**
     * Get the rate limit from response headers.
     */
    public function rateLimit(): ?RateLimitInfo
    {
        $limit = $this->header('X-RateLimit-Limit');
        $remaining = $this->header('X-RateLimit-Remaining');
        $reset = $this->header('X-RateLimit-Reset');

        if ($limit === null && $remaining === null) {
            return null;
        }

        return new RateLimitInfo(
            limit: $limit !== null ? (int) $limit : null,
            remaining: $remaining !== null ? (int) $remaining : null,
            reset: $reset !== null ? (int) $reset : null,
        );
    }

    // ===== Mutation (Immutable) =====

    /**
     * Create a new response with a different JSON body.
     *
     * @param array<string, mixed> $data
     */
    public function withJson(array $data): self
    {
        $body = json_encode($data);

        throw_if($body === false, ResponseException::jsonEncodingFailed());

        return $this->withBody($body);
    }

    /**
     * Create a new response with a modified JSON key.
     */
    public function withJsonKey(string $key, mixed $value): self
    {
        $data = $this->json();

        throw_unless(is_array($data), ResponseException::cannotModifyJsonKey());

        data_set($data, $key, $value);

        /** @var array<string, mixed> $data */
        return $this->withJson($data);
    }

    /**
     * Create a new response with a different body.
     */
    public function withBody(string $body): self
    {
        $stream = Utils::streamFor($body);
        $newPsrResponse = $this->psrResponse->withBody($stream);

        $new = new self($newPsrResponse, $this->request);
        $this->copyMetadataTo($new);

        return $new;
    }

    /**
     * Create a new response with different headers.
     *
     * @param array<string, string> $headers
     */
    public function withHeaders(array $headers): self
    {
        $newPsrResponse = $this->psrResponse;

        foreach ($headers as $name => $value) {
            $newPsrResponse = $newPsrResponse->withHeader($name, $value);
        }

        $new = new self($newPsrResponse, $this->request);
        $this->copyMetadataTo($new);

        return $new;
    }

    /**
     * Create a new response with a different header.
     */
    public function withHeader(string $name, string $value): self
    {
        return $this->withHeaders([$name => $value]);
    }

    /**
     * Create a new response with a different status code.
     */
    public function withStatus(int $code): self
    {
        $newPsrResponse = $this->psrResponse->withStatus($code);

        $new = new self($newPsrResponse, $this->request);
        $this->copyMetadataTo($new);

        return $new;
    }

    // ===== File Downloads =====

    /**
     * Save the response body to a file.
     *
     * @throws FileWriteException If the file cannot be written
     */
    public function saveTo(string $path): self
    {
        $directory = dirname($path);

        throw_unless(is_dir($directory), FileWriteException::directoryNotFound($directory));

        $result = file_put_contents($path, $this->body());

        throw_if($result === false, FileWriteException::writeFailed($path));

        return $this;
    }

    /**
     * Stream the response body to a file with optional progress callback.
     *
     * @param null|callable(int, int): void $progress Callback with (downloaded, total) bytes
     *
     * @throws FileWriteException If the file cannot be written
     */
    public function streamTo(string $path, ?callable $progress = null): self
    {
        $directory = dirname($path);

        throw_unless(is_dir($directory), FileWriteException::directoryNotFound($directory));

        $handle = fopen($path, 'wb');

        throw_if($handle === false, FileWriteException::writeFailed($path));

        $body = $this->psrResponse->getBody();
        $total = (int) ($this->header('Content-Length') ?? 0);
        $downloaded = 0;
        $chunkSize = 8_192;

        while (!$body->eof()) {
            $chunk = $body->read($chunkSize);
            $written = fwrite($handle, $chunk);

            if ($written === false) { // @codeCoverageIgnore
                fclose($handle); // @codeCoverageIgnore

                throw FileWriteException::writeFailed($path); // @codeCoverageIgnore
            }

            /** @codeCoverageIgnore */
            $downloaded += $written;

            if ($progress === null) {
                continue;
            }

            $progress($downloaded, $total);
        }

        fclose($handle);

        return $this;
    }

    /**
     * Iterate over response body chunks.
     *
     * @return Generator<string>
     */
    public function chunks(int $chunkSize = 8_192): Generator
    {
        $body = $this->psrResponse->getBody();

        while (!$body->eof()) {
            yield $body->read($chunkSize);
        }
    }

    /**
     * Get the response body as a stream resource.
     *
     * Note: Returns a PHP resource, not the Cline\Relay\Resource class.
     * PHPStan cannot distinguish between the resource pseudo-type and the Resource class,
     * so we use mixed return type to avoid the name collision.
     */
    public function stream(): mixed
    {
        $stream = fopen('php://temp', 'r+b');

        throw_if($stream === false, ResponseException::failedToCreateTemporaryStream());

        $result = fwrite($stream, $this->body());

        if ($result === false) {
            fclose($stream);

            throw ResponseException::failedToWriteToTemporaryStream();
        }

        rewind($stream);

        return $stream;
    }

    /**
     * Get the filename from Content-Disposition header.
     */
    public function filename(): ?string
    {
        $disposition = $this->header('Content-Disposition');

        if ($disposition === null) {
            return null;
        }

        // Try filename*= (RFC 5987)
        if (preg_match('/filename\*=(?:utf-8\'\')?([^;]+)/i', $disposition, $matches)) {
            return urldecode(mb_trim($matches[1], '"'));
        }

        // Try filename=
        if (preg_match('/filename=([^;]+)/i', $disposition, $matches)) {
            return mb_trim($matches[1], '"');
        }

        return null;
    }

    /**
     * Check if response is a file download.
     */
    public function isDownload(): bool
    {
        $disposition = $this->header('Content-Disposition');

        return $disposition !== null && str_contains($disposition, 'attachment');
    }

    /**
     * Get the response body as base64.
     */
    public function base64(): string
    {
        return base64_encode($this->body());
    }

    // ===== Debugging =====

    /**
     * Dump the response for debugging.
     */
    public function dump(): self
    {
        dump($this->toDebugArray());

        return $this;
    }

    /**
     * Dump the response and die.
     *
     * @codeCoverageIgnore
     */
    public function dd(): never
    {
        dd($this->toDebugArray());
    }

    /**
     * Copy metadata to another response.
     */
    private function copyMetadataTo(self $other): void
    {
        $other->duration = $this->duration;
        $other->traceId = $this->traceId;
        $other->spanId = $this->spanId;
        $other->idempotencyKey = $this->idempotencyKey;
        $other->wasIdempotentReplay = $this->wasIdempotentReplay;
        $other->fromCache = $this->fromCache;
    }

    /**
     * Convert to array for debugging.
     *
     * @return array<string, mixed>
     */
    private function toDebugArray(): array
    {
        $body = $this->body();
        $jsonBody = json_decode($body, true);

        return [
            'status' => $this->status(),
            'headers' => $this->headers(),
            'body' => $jsonBody ?? $body,
            'duration' => $this->duration,
        ];
    }
}

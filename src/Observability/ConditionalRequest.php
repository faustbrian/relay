<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Observability;

use DateTimeImmutable;
use DateTimeInterface;

use function is_array;

/**
 * Handles conditional request headers (ETag, Last-Modified).
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ConditionalRequest
{
    private ?string $etag = null;

    private ?DateTimeImmutable $lastModified = null;

    /**
     * Extract conditional values from response headers.
     *
     * @param array<string, array<string>|string> $headers
     */
    public static function fromResponseHeaders(array $headers): self
    {
        $instance = new self();

        // Extract ETag
        $etag = $headers['ETag'] ?? $headers['etag'] ?? null;

        if ($etag !== null) {
            $instance->etag = is_array($etag) ? $etag[0] : $etag;
        }

        // Extract Last-Modified
        $lastModified = $headers['Last-Modified'] ?? $headers['last-modified'] ?? null;

        if ($lastModified !== null) {
            $dateString = is_array($lastModified) ? $lastModified[0] : $lastModified;
            $instance->lastModified = DateTimeImmutable::createFromFormat(
                DateTimeInterface::RFC7231,
                $dateString,
            ) ?: null;
        }

        return $instance;
    }

    /**
     * Set the ETag for conditional request.
     */
    public function withEtag(string $etag): self
    {
        $clone = clone $this;
        $clone->etag = $etag;

        return $clone;
    }

    /**
     * Set the Last-Modified date for conditional request.
     */
    public function withLastModified(DateTimeInterface $date): self
    {
        $clone = clone $this;
        $clone->lastModified = DateTimeImmutable::createFromInterface($date);

        return $clone;
    }

    /**
     * Get headers for conditional GET request.
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        $headers = [];

        if ($this->etag !== null) {
            $headers['If-None-Match'] = $this->etag;
        }

        if ($this->lastModified instanceof DateTimeImmutable) {
            $headers['If-Modified-Since'] = $this->lastModified->format(DateTimeInterface::RFC7231);
        }

        return $headers;
    }

    /**
     * Get headers for conditional PUT/PATCH request (preconditions).
     *
     * @return array<string, string>
     */
    public function getPreconditionHeaders(): array
    {
        $headers = [];

        if ($this->etag !== null) {
            $headers['If-Match'] = $this->etag;
        }

        if ($this->lastModified instanceof DateTimeImmutable) {
            $headers['If-Unmodified-Since'] = $this->lastModified->format(DateTimeInterface::RFC7231);
        }

        return $headers;
    }

    /**
     * Check if a 304 response means cache can be used.
     */
    public function isNotModified(int $statusCode): bool
    {
        return $statusCode === 304;
    }

    /**
     * Check if a 412 response means precondition failed.
     */
    public function isPreconditionFailed(int $statusCode): bool
    {
        return $statusCode === 412;
    }

    /**
     * Get the current ETag.
     */
    public function etag(): ?string
    {
        return $this->etag;
    }

    /**
     * Get the current Last-Modified date.
     */
    public function lastModified(): ?DateTimeImmutable
    {
        return $this->lastModified;
    }

    /**
     * Check if any conditional headers are set.
     */
    public function hasConditions(): bool
    {
        return $this->etag !== null || $this->lastModified instanceof DateTimeImmutable;
    }
}

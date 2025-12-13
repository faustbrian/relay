<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Files;

use Cline\Relay\Support\Exceptions\FileException;
use SplFileInfo;

use const PATHINFO_EXTENSION;

use function base64_decode;
use function file_exists;
use function file_get_contents;
use function is_readable;
use function mb_strlen;
use function mb_strtolower;
use function pathinfo;
use function stream_get_contents;
use function throw_if;
use function throw_unless;

/**
 * Represents a file for upload in multipart requests.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class File
{
    private function __construct(
        private string $name,
        private string $contents,
        private ?string $filename = null,
        private ?string $mimeType = null,
        /** @var array<string, string> */
        private array $headers = [],
    ) {}

    /**
     * Create a file from a path.
     */
    public static function fromPath(
        string $name,
        string $path,
        ?string $filename = null,
        ?string $mimeType = null,
    ): self {
        throw_unless(file_exists($path), FileException::notFound($path));

        throw_unless(is_readable($path), FileException::notReadable($path));

        $file = new SplFileInfo($path);
        $contents = file_get_contents($path);

        throw_if($contents === false, FileException::unableToRead($path));

        return new self(
            name: $name,
            contents: $contents,
            filename: $filename ?? $file->getFilename(),
            mimeType: $mimeType ?? self::guessMimeType($path),
        );
    }

    /**
     * Create a file from string contents.
     */
    public static function fromContents(
        string $name,
        string $contents,
        string $filename,
        ?string $mimeType = null,
    ): self {
        return new self(
            name: $name,
            contents: $contents,
            filename: $filename,
            mimeType: $mimeType ?? 'application/octet-stream',
        );
    }

    /**
     * Create a file from a resource/stream.
     *
     * @param resource $resource
     */
    public static function fromResource(
        string $name,
        $resource,
        string $filename,
        ?string $mimeType = null,
    ): self {
        $contents = stream_get_contents($resource);

        return new self(
            name: $name,
            contents: $contents,
            filename: $filename,
            mimeType: $mimeType ?? 'application/octet-stream',
        );
    }

    /**
     * Create a file from base64-encoded string.
     */
    public static function fromBase64(
        string $name,
        string $base64,
        string $filename,
        ?string $mimeType = null,
    ): self {
        $contents = base64_decode($base64, true);

        throw_if($contents === false, FileException::invalidBase64());

        return new self(
            name: $name,
            contents: $contents,
            filename: $filename,
            mimeType: $mimeType ?? 'application/octet-stream',
        );
    }

    /**
     * Get the form field name.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Get the file contents.
     */
    public function contents(): string
    {
        return $this->contents;
    }

    /**
     * Get the filename.
     */
    public function filename(): ?string
    {
        return $this->filename;
    }

    /**
     * Get the MIME type.
     */
    public function mimeType(): ?string
    {
        return $this->mimeType;
    }

    /**
     * Get additional headers.
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Create a copy with a different name.
     */
    public function withName(string $name): self
    {
        return new self(
            name: $name,
            contents: $this->contents,
            filename: $this->filename,
            mimeType: $this->mimeType,
            headers: $this->headers,
        );
    }

    /**
     * Create a copy with a different filename.
     */
    public function withFilename(string $filename): self
    {
        return new self(
            name: $this->name,
            contents: $this->contents,
            filename: $filename,
            mimeType: $this->mimeType,
            headers: $this->headers,
        );
    }

    /**
     * Create a copy with a different MIME type.
     */
    public function withMimeType(string $mimeType): self
    {
        return new self(
            name: $this->name,
            contents: $this->contents,
            filename: $this->filename,
            mimeType: $mimeType,
            headers: $this->headers,
        );
    }

    /**
     * Create a copy with additional headers.
     *
     * @param array<string, string> $headers
     */
    public function withHeaders(array $headers): self
    {
        return new self(
            name: $this->name,
            contents: $this->contents,
            filename: $this->filename,
            mimeType: $this->mimeType,
            headers: [...$this->headers, ...$headers],
        );
    }

    /**
     * Get the file size in bytes.
     */
    public function size(): int
    {
        return mb_strlen($this->contents);
    }

    /**
     * Convert to Guzzle multipart array format.
     *
     * @return array<string, mixed>
     */
    public function toMultipart(): array
    {
        $part = [
            'name' => $this->name,
            'contents' => $this->contents,
        ];

        if ($this->filename !== null) {
            $part['filename'] = $this->filename;
        }

        if ($this->headers !== []) {
            $part['headers'] = $this->headers;
        }

        if ($this->mimeType !== null) {
            $part['headers']['Content-Type'] = $this->mimeType;
        }

        return $part;
    }

    /**
     * Guess the MIME type from file extension.
     */
    private static function guessMimeType(string $path): string
    {
        $extension = mb_strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'zip' => 'application/zip',
            'gz', 'gzip' => 'application/gzip',
            'tar' => 'application/x-tar',
            'txt' => 'text/plain',
            'html', 'htm' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'csv' => 'text/csv',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'mp3' => 'audio/mpeg',
            'mp4' => 'video/mp4',
            'mov' => 'video/quicktime',
            'avi' => 'video/x-msvideo',
            default => 'application/octet-stream',
        };
    }
}

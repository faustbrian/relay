<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Files;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

use function array_map;
use function count;

/**
 * Collection of files for multipart uploads.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @implements IteratorAggregate<int, File>
 */
final class FileCollection implements Countable, IteratorAggregate
{
    /** @var array<int, File> */
    private array $files = [];

    /**
     * @param array<int, File> $files
     */
    public function __construct(array $files = [])
    {
        foreach ($files as $file) {
            $this->add($file);
        }
    }

    /**
     * Add a file to the collection.
     */
    public function add(File $file): self
    {
        $this->files[] = $file;

        return $this;
    }

    /**
     * Add a file from path.
     */
    public function addFromPath(
        string $name,
        string $path,
        ?string $filename = null,
        ?string $mimeType = null,
    ): self {
        $this->files[] = File::fromPath($name, $path, $filename, $mimeType);

        return $this;
    }

    /**
     * Add a file from contents.
     */
    public function addFromContents(
        string $name,
        string $contents,
        string $filename,
        ?string $mimeType = null,
    ): self {
        $this->files[] = File::fromContents($name, $contents, $filename, $mimeType);

        return $this;
    }

    /**
     * Get all files.
     *
     * @return array<int, File>
     */
    public function all(): array
    {
        return $this->files;
    }

    /**
     * Check if collection is empty.
     */
    public function isEmpty(): bool
    {
        return $this->files === [];
    }

    /**
     * Check if collection is not empty.
     */
    public function isNotEmpty(): bool
    {
        return $this->files !== [];
    }

    /**
     * Get the count of files.
     */
    public function count(): int
    {
        return count($this->files);
    }

    /**
     * Get the total size of all files in bytes.
     */
    public function totalSize(): int
    {
        $total = 0;

        foreach ($this->files as $file) {
            $total += $file->size();
        }

        return $total;
    }

    /**
     * Convert to Guzzle multipart array format.
     *
     * @return array<int, array<string, mixed>>
     */
    public function toMultipart(): array
    {
        return array_map(
            fn (File $file): array => $file->toMultipart(),
            $this->files,
        );
    }

    /**
     * Get iterator for files.
     *
     * @return Traversable<int, File>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->files);
    }
}

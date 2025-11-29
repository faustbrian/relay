<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Exceptions;

use InvalidArgumentException;

/**
 * Exception thrown for file-related errors.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class FileException extends InvalidArgumentException
{
    public static function notFound(string $path): self
    {
        return new self('File not found: '.$path);
    }

    public static function notReadable(string $path): self
    {
        return new self('File not readable: '.$path);
    }

    public static function unableToRead(string $path): self
    {
        return new self('Unable to read file: '.$path);
    }

    public static function invalidBase64(): self
    {
        return new self('Invalid base64 content');
    }
}

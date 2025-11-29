<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Exceptions;

use RuntimeException;

/**
 * Exception thrown when file write operations fail.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class FileWriteException extends RuntimeException
{
    public static function directoryNotFound(string $directory): self
    {
        return new self('Directory does not exist: '.$directory);
    }

    public static function writeFailed(string $path): self
    {
        return new self('Failed to write file: '.$path);
    }
}

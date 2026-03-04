<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Exceptions;

use RuntimeException;

use function sprintf;

/**
 * Exception thrown when a value cannot be formatted.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ValueFormatException extends RuntimeException
{
    public static function cannotFormat(string $type): self
    {
        return new self(sprintf('Cannot format value of type %s', $type));
    }
}

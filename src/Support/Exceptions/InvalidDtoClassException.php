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
 * Exception thrown when an invalid DTO class is provided.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidDtoClassException extends InvalidArgumentException
{
    public static function mustImplementInterface(string $class): self
    {
        return new self($class.' must implement DataTransferObject interface');
    }
}

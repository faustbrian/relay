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
 * Exception thrown for cache key generation errors.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class CacheKeyException extends RuntimeException
{
    public static function methodMustReturnString(): self
    {
        return new self('Cache key method must return a string');
    }

    public static function propertyMustBeScalar(string $property): self
    {
        return new self(
            sprintf("Property '%s' must be scalar or null for cache key interpolation", $property),
        );
    }
}

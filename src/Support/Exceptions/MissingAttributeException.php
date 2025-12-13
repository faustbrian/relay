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
 * Thrown when a required attribute is missing from a request.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class MissingAttributeException extends RuntimeException
{
    public static function httpMethod(string $requestClass): self
    {
        return new self(sprintf(
            'Request [%s] is missing an HTTP method attribute. Add #[Get], #[Post], etc.',
            $requestClass,
        ));
    }
}

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Exceptions;

use RuntimeException;

use function implode;
use function sprintf;

/**
 * Thrown when conflicting attributes are applied to a request.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class AttributeConflictException extends RuntimeException
{
    /**
     * @param array<string> $methods
     */
    public static function multipleHttpMethods(string $requestClass, array $methods): self
    {
        return new self(sprintf(
            'Request [%s] has multiple HTTP method attributes: %s. Only one is allowed.',
            $requestClass,
            implode(', ', $methods),
        ));
    }

    /**
     * @param array<string> $contentTypes
     */
    public static function multipleContentTypes(string $requestClass, array $contentTypes): self
    {
        return new self(sprintf(
            'Request [%s] has multiple content type attributes: %s. Only one is allowed.',
            $requestClass,
            implode(', ', $contentTypes),
        ));
    }

    /**
     * @param array<string> $protocols
     */
    public static function multipleProtocols(string $requestClass, array $protocols): self
    {
        return new self(sprintf(
            'Request [%s] has multiple protocol attributes: %s. Only one is allowed.',
            $requestClass,
            implode(', ', $protocols),
        ));
    }
}

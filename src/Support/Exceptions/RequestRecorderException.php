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
 * Exception thrown when request recorder assertions fail.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class RequestRecorderException extends RuntimeException
{
    public static function noMatchingRequest(): self
    {
        return new self('No matching request was recorded');
    }

    public static function noRequestToEndpoint(string $endpoint): self
    {
        return new self(
            sprintf('No request to %s was recorded', $endpoint),
        );
    }
}

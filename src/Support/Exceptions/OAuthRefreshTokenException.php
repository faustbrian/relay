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
 * Exception thrown when OAuth refresh token is missing.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class OAuthRefreshTokenException extends InvalidArgumentException
{
    public static function missingRefreshToken(): self
    {
        return new self('The provided OAuthAuthenticator does not contain a refresh token.');
    }
}

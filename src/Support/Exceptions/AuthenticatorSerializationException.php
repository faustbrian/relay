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
 * Exception thrown for authenticator serialization errors.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class AuthenticatorSerializationException extends RuntimeException
{
    public static function invalidSerializedData(): self
    {
        return new self('Invalid serialized data');
    }

    public static function missingOrInvalidAccessToken(): self
    {
        return new self('Missing or invalid accessToken');
    }

    public static function invalidRefreshToken(): self
    {
        return new self('Invalid refreshToken');
    }

    public static function invalidExpiresAt(): self
    {
        return new self('Invalid expiresAt');
    }
}

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Exceptions;

use Exception;

/**
 * Exception for OAuth configuration errors.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class OAuthConfigException extends Exception
{
    public static function missingClientId(): self
    {
        return new self('The Client ID is empty or has not been provided.');
    }

    public static function missingClientSecret(): self
    {
        return new self('The Client Secret is empty or has not been provided.');
    }

    public static function missingRedirectUri(): self
    {
        return new self('The Redirect URI is empty or has not been provided.');
    }
}

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Exceptions;

use RuntimeException;

use function json_last_error_msg;

/**
 * Exception thrown when JSON encoding fails.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class JsonEncodingException extends RuntimeException
{
    public static function requestBodyFailed(): self
    {
        return new self('Failed to encode request body to JSON');
    }

    public static function arrayValueFailed(): self
    {
        return new self('Failed to encode array value to JSON');
    }

    public static function encodingFailed(): self
    {
        return new self('Failed to encode JSON: '.json_last_error_msg());
    }
}

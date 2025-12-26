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
 * Exception thrown for response-related errors.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ResponseException extends RuntimeException
{
    public static function jsonEncodingFailed(): self
    {
        return new self('Failed to encode data as JSON');
    }

    public static function invalidJsonObject(): self
    {
        return new self('Response body is not a valid JSON object');
    }

    public static function cannotCreateDtoFromFailedResponse(int $status, string $body): self
    {
        return new self(
            sprintf('Cannot create DTO from failed response (HTTP %d): %s', $status, $body),
            $status,
        );
    }

    public static function noRequestAssociatedWithResponse(): self
    {
        return new self('Cannot create DTO: no request associated with response');
    }

    public static function requestDoesNotImplementDtoCreation(string $requestClass): self
    {
        return new self(
            sprintf('Request %s does not implement createDtoFromResponse()', $requestClass),
        );
    }

    public static function collectionItemMustBeArray(): self
    {
        return new self('DTO collection items must be arrays');
    }

    public static function cannotModifyJsonKey(): self
    {
        return new self('Cannot modify JSON key: response body is not an array');
    }

    public static function failedToCreateTemporaryStream(): self
    {
        return new self('Failed to create temporary stream');
    }

    public static function failedToWriteToTemporaryStream(): self
    {
        return new self('Failed to write to temporary stream');
    }

    public static function httpRequestFailed(int $status, string $body): self
    {
        return new self(
            sprintf('HTTP request failed with status %d: %s', $status, $body),
            $status,
        );
    }
}

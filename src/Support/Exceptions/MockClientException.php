<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Exceptions;

use Exception;

use function sprintf;

/**
 * Exception thrown by the MockClient.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class MockClientException extends Exception
{
    /**
     * No matching response found for request.
     */
    public static function noMatchingResponse(string $requestClass, string $url): self
    {
        return new self(sprintf("No mock response configured for request '%s' to URL '%s'.", $requestClass, $url));
    }

    /**
     * Stray request attempted when prevented.
     */
    public static function strayRequest(string $requestClass, string $url): self
    {
        return new self(sprintf("Stray request attempted for '%s' to URL '%s'. Real API calls are prevented.", $requestClass, $url));
    }

    /**
     * Assertion failed.
     */
    public static function assertionFailed(string $message): self
    {
        return new self($message);
    }

    /**
     * Invalid response type returned.
     */
    public static function invalidResponse(string $requestClass): self
    {
        return new self(sprintf("Mock response for '%s' must return a Response instance.", $requestClass));
    }
}

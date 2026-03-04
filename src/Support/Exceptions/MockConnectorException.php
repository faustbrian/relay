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
 * Exception thrown when MockConnector assertions fail.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class MockConnectorException extends RuntimeException
{
    public static function noResponsesConfigured(): self
    {
        return new self('No mock responses configured. Add responses with addResponse() before sending requests.');
    }

    public static function requestNotSent(string $endpoint, ?string $method = null): self
    {
        $message = 'No request was sent to '.$endpoint;

        if ($method !== null) {
            $message .= ' with method '.$method;
        }

        return new self($message);
    }

    public static function unexpectedRequest(string $endpoint): self
    {
        return new self('Request was unexpectedly sent to '.$endpoint);
    }

    public static function requestCountMismatch(int $expected, int $actual): self
    {
        return new self(
            sprintf('Expected %d requests, but %d were sent', $expected, $actual),
        );
    }
}

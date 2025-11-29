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
 * Exception thrown when circuit breaker is open.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class CircuitOpenException extends Exception
{
    public function __construct(
        string $message,
        private readonly int $retryAfter,
    ) {
        parent::__construct($message, 503);
    }

    public static function open(int $retryAfter): self
    {
        return new self('Circuit breaker is open', $retryAfter);
    }

    public static function halfOpenAtCapacity(): self
    {
        return new self('Circuit breaker is half-open and at capacity', 1);
    }

    /**
     * Get seconds until retry is allowed.
     */
    public function retryAfter(): int
    {
        return $this->retryAfter;
    }
}

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Contracts;

use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;
use Throwable;

/**
 * Interface for retry policy implementations.
 *
 * Implement this interface to create reusable retry policies that encapsulate
 * all retry configuration and logic. Policies can be shared across requests
 * and have access to both the request and response for decision making.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface RetryPolicy
{
    /**
     * Maximum number of retry attempts.
     */
    public function times(): int;

    /**
     * Initial delay between retries in milliseconds.
     */
    public function delay(): int;

    /**
     * Multiplier for exponential backoff.
     */
    public function multiplier(): float;

    /**
     * Maximum delay in milliseconds.
     */
    public function maxDelay(): int;

    /**
     * Determine if the request should be retried based on the response.
     */
    public function shouldRetry(Request $request, Response $response, int $attempt): bool;

    /**
     * Determine if the request should be retried based on an exception.
     */
    public function shouldRetryException(Request $request, Throwable $exception, int $attempt): bool;
}

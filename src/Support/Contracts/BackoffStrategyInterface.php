<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Contracts;

use Cline\Relay\Core\AbstractRequest;

/**
 * Interface for rate limit backoff strategies.
 *
 * Implement this interface to create custom backoff strategies
 * for rate limiting retry behavior.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface BackoffStrategyInterface
{
    /**
     * Calculate the delay in milliseconds for a given attempt.
     *
     * @param AbstractRequest $request    The request being rate limited
     * @param int             $attempt    The current retry attempt (1-based)
     * @param int             $retryAfter The Retry-After header value in seconds (0 if not present)
     */
    public function calculateDelay(AbstractRequest $request, int $attempt, int $retryAfter = 0): int;
}

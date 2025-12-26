<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Attributes\Resilience;

use Attribute;
use Cline\Relay\Support\Contracts\CircuitBreakerPolicy;

/**
 * Configure circuit breaker for a request.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class CircuitBreaker
{
    /**
     * @param int                                     $failureThreshold  Failures to open circuit
     * @param int                                     $resetTimeout      Seconds before retry (half-open)
     * @param int                                     $halfOpenRequests  Allowed requests in half-open state
     * @param int                                     $failureWindow     Time window for counting failures (seconds)
     * @param int                                     $successThreshold  Successes needed to close circuit
     * @param null|float                              $failurePercentage Open when this % of requests fail
     * @param null|int                                $minimumRequests   Min requests before evaluating percentage
     * @param null|string                             $key               Custom circuit key
     * @param null|class-string<CircuitBreakerPolicy> $policy            Policy class that encapsulates all circuit breaker configuration
     */
    public function __construct(
        public int $failureThreshold = 5,
        public int $resetTimeout = 30,
        public int $halfOpenRequests = 3,
        public int $failureWindow = 60,
        public int $successThreshold = 1,
        public ?float $failurePercentage = null,
        public ?int $minimumRequests = null,
        public ?string $key = null,
        public ?string $policy = null,
    ) {}
}

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Features\Resilience;

use Cline\Relay\Support\Contracts\CircuitBreakerPolicy;
use Closure;

/**
 * Configuration for circuit breaker behavior.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class CircuitBreakerConfig
{
    /**
     * @param int                       $failureThreshold  Failures to open circuit
     * @param int                       $resetTimeout      Seconds before retry (half-open)
     * @param int                       $halfOpenRequests  Allowed requests in half-open state
     * @param int                       $failureWindow     Time window for counting failures
     * @param int                       $successThreshold  Successes needed to close circuit
     * @param null|float                $failurePercentage Open when this % of requests fail
     * @param null|int                  $minimumRequests   Min requests before evaluating percentage
     * @param null|string               $key               Circuit key
     * @param null|Closure              $failureCondition  What counts as failure
     * @param null|Closure              $onOpen            Callback when circuit opens
     * @param null|Closure              $onClose           Callback when circuit closes
     * @param null|Closure              $onHalfOpen        Callback when circuit half-opens
     * @param null|CircuitBreakerPolicy $policy            Policy instance that encapsulates all configuration
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
        public ?Closure $failureCondition = null,
        public ?Closure $onOpen = null,
        public ?Closure $onClose = null,
        public ?Closure $onHalfOpen = null,
        public ?CircuitBreakerPolicy $policy = null,
    ) {}

    /**
     * Create a config from a policy class.
     */
    public static function fromPolicy(CircuitBreakerPolicy $policy): self
    {
        return new self(
            failureThreshold: $policy->failureThreshold(),
            resetTimeout: $policy->resetTimeout(),
            halfOpenRequests: $policy->halfOpenRequests(),
            failureWindow: $policy->failureWindow(),
            successThreshold: $policy->successThreshold(),
            onOpen: $policy->onOpen(...),
            onClose: $policy->onClose(...),
            onHalfOpen: $policy->onHalfOpen(...),
            policy: $policy,
        );
    }
}

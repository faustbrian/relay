<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Features\Resilience;

use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;
use Cline\Relay\Support\Attributes\Resilience\Retry;
use Cline\Relay\Support\Contracts\RetryDecider;
use Cline\Relay\Support\Contracts\RetryPolicy;
use Closure;
use Illuminate\Support\Sleep;
use ReflectionClass;
use Throwable;

use function array_any;
use function class_exists;
use function in_array;
use function is_a;
use function method_exists;
use function min;

/**
 * Handles retry logic for failed requests.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class RetryHandler
{
    public function __construct(
        private ?RetryConfig $defaultConfig = null,
    ) {}

    /**
     * Get retry config for a request.
     */
    public function getConfig(Request $request): ?RetryConfig
    {
        $attribute = $this->getRetryAttribute($request);

        // If a policy class is specified, use it for configuration
        if ($attribute?->policy !== null) {
            $policy = $this->resolvePolicy($attribute->policy);

            if ($policy instanceof RetryPolicy) {
                return new RetryConfig(
                    times: $policy->times(),
                    delay: $policy->delay(),
                    multiplier: $policy->multiplier(),
                    maxDelay: $policy->maxDelay(),
                );
            }
        }

        if ($attribute instanceof Retry) {
            return new RetryConfig(
                times: $attribute->times,
                delay: $attribute->delay,
                multiplier: $attribute->multiplier,
                maxDelay: $attribute->maxDelay,
                statusCodes: $attribute->when,
                exceptions: $attribute->exceptions,
            );
        }

        return $this->defaultConfig;
    }

    /**
     * Check if we should retry based on a response.
     */
    public function shouldRetryResponse(Request $request, Response $response, int $attempt): bool
    {
        $attribute = $this->getRetryAttribute($request);

        // If a policy class is specified, delegate to it
        if ($attribute?->policy !== null) {
            $policy = $this->resolvePolicy($attribute->policy);

            if ($policy instanceof RetryPolicy) {
                if ($attempt >= $policy->times()) {
                    return false;
                }

                return $policy->shouldRetry($request, $response, $attempt);
            }
        }

        $config = $this->getConfig($request);

        if (!$config instanceof RetryConfig) {
            return false;
        }

        if ($attempt >= $config->times) {
            return false;
        }

        // Custom callback - either method name or RetryDecider class
        if ($attribute?->callback !== null && $this->canResolveCallback($request, $attribute->callback)) {
            return $this->resolveCallback($request, $attribute->callback, $response, $attempt);
        }

        // Custom closure in config
        if ($config->when instanceof Closure) {
            return (bool) ($config->when)($response);
        }

        // Check status codes
        if ($config->statusCodes !== null) {
            return in_array($response->status(), $config->statusCodes, true);
        }

        // Default: retry on server errors
        return $response->serverError();
    }

    /**
     * Check if we should retry based on an exception.
     */
    public function shouldRetryException(Request $request, Throwable $exception, int $attempt): bool
    {
        $attribute = $this->getRetryAttribute($request);

        // If a policy class is specified, delegate to it
        if ($attribute?->policy !== null) {
            $policy = $this->resolvePolicy($attribute->policy);

            if ($policy instanceof RetryPolicy) {
                if ($attempt >= $policy->times()) {
                    return false;
                }

                return $policy->shouldRetryException($request, $exception, $attempt);
            }
        }

        $config = $this->getConfig($request);

        if (!$config instanceof RetryConfig) {
            return false;
        }

        if ($attempt >= $config->times) {
            return false;
        }

        // Check exception types
        if ($config->exceptions !== null) {
            return array_any($config->exceptions, fn ($exceptionClass): bool => $exception instanceof $exceptionClass);
        }

        // Default: don't retry exceptions unless explicitly configured
        return false;
    }

    /**
     * Calculate delay for a retry attempt in milliseconds.
     */
    public function calculateDelay(Request $request, int $attempt): int
    {
        $attribute = $this->getRetryAttribute($request);

        // If a policy class is specified, use its delay configuration
        if ($attribute?->policy !== null) {
            $policy = $this->resolvePolicy($attribute->policy);

            if ($policy instanceof RetryPolicy) {
                $delay = (int) ($policy->delay() * $policy->multiplier() ** ($attempt - 1));

                return min($delay, $policy->maxDelay());
            }
        }

        $config = $this->getConfig($request);

        if (!$config instanceof RetryConfig) {
            return 0;
        }

        $delay = (int) ($config->delay * $config->multiplier ** ($attempt - 1));

        return min($delay, $config->maxDelay);
    }

    /**
     * Sleep for the calculated delay.
     */
    public function sleep(Request $request, int $attempt): void
    {
        $delay = $this->calculateDelay($request, $attempt);

        if ($delay <= 0) {
            return;
        }

        Sleep::usleep($delay * 1_000); // Convert to microseconds
    }

    /**
     * Get the Retry attribute from a request.
     */
    private function getRetryAttribute(Request $request): ?Retry
    {
        $reflection = new ReflectionClass($request);
        $attributes = $reflection->getAttributes(Retry::class);

        if ($attributes === []) {
            return null;
        }

        return $attributes[0]->newInstance();
    }

    /**
     * Resolve a RetryPolicy class to an instance.
     *
     * @param class-string<RetryPolicy> $policyClass
     */
    private function resolvePolicy(string $policyClass): ?RetryPolicy
    {
        if (!class_exists($policyClass)) {
            return null;
        }

        return new $policyClass();
    }

    /**
     * Check if a callback can be resolved.
     */
    private function canResolveCallback(Request $request, string $callback): bool
    {
        return (class_exists($callback) && is_a($callback, RetryDecider::class, true))
            || method_exists($request, $callback);
    }

    /**
     * Resolve and execute a callback for retry decision.
     *
     * The callback can be:
     * - A method name on the request class
     * - A class-string of a RetryDecider implementation
     */
    private function resolveCallback(Request $request, string $callback, Response $response, int $attempt): bool
    {
        // Check if it's a RetryDecider class
        if (class_exists($callback) && is_a($callback, RetryDecider::class, true)) {
            $decider = new $callback();

            return $decider($request, $response, $attempt);
        }

        // Method name on the request
        return (bool) $request->{$callback}($response, $attempt);
    }
}

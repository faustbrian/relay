<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Features\RateLimiting;

use Cline\Relay\Core\Connector;
use Cline\Relay\Core\Request;
use Cline\Relay\Features\RateLimiting\RateLimitInfo;
use Cline\Relay\Support\Attributes\RateLimiting\RateLimit;
use Cline\Relay\Support\Contracts\BackoffStrategy;
use Cline\Relay\Support\Contracts\RateLimitStore;
use Cline\Relay\Support\Exceptions\Client\RateLimitException;
use Illuminate\Support\Facades\Date;
use ReflectionClass;

use function class_exists;
use function is_a;
use function is_object;
use function is_scalar;
use function method_exists;
use function preg_replace_callback;

/**
 * Handles rate limiting for requests.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class RateLimiter
{
    public function __construct(
        private RateLimitStore $store,
        private ?RateLimitConfig $defaultConfig = null,
    ) {}

    /**
     * Check if a request should be rate limited.
     *
     * @throws RateLimitException If rate limited and retry is disabled
     */
    public function check(Connector $connector, Request $request): void
    {
        $config = $this->getConfigForRequest($request);

        if (!$config instanceof RateLimitConfig) {
            return;
        }

        $key = $this->resolveKey($connector, $request);

        if (!$this->store->attempt($key, $config->requests, $config->perSeconds)) {
            $retryAfter = $this->store->getResetTime($key);
            $remaining = $this->store->getRemaining($key, $config->requests);

            throw RateLimitException::exceeded(
                retryAfterSeconds: $retryAfter !== null ? $retryAfter - Date::now()->getTimestamp() : null,
                limit: $config->requests,
                remaining: $remaining,
            );
        }
    }

    /**
     * Get the rate limit state for a request.
     */
    public function getState(Connector $connector, Request $request): ?RateLimitInfo
    {
        $config = $this->getConfigForRequest($request);

        if (!$config instanceof RateLimitConfig) {
            return null;
        }

        $key = $this->resolveKey($connector, $request);

        return new RateLimitInfo(
            limit: $config->requests,
            remaining: $this->store->getRemaining($key, $config->requests),
            reset: $this->store->getResetTime($key),
        );
    }

    /**
     * Get the retry configuration for a request.
     */
    public function getRetryConfig(Request $request): ?RateLimitConfig
    {
        $config = $this->getConfigForRequest($request);

        if (!$config instanceof RateLimitConfig || !$config->retry) {
            return null;
        }

        return $config;
    }

    /**
     * Calculate backoff time for a retry attempt.
     */
    public function calculateBackoff(Request $request, RateLimitConfig $config, int $attempt, int $retryAfter = 0): int
    {
        // Check if backoff is a BackoffStrategy class
        if (class_exists($config->backoff) && is_a($config->backoff, BackoffStrategy::class, true)) {
            $strategy = new ($config->backoff)();

            return $strategy->calculateDelay($request, $attempt, $retryAfter);
        }

        return match ($config->backoff) {
            'linear' => $attempt * 1_000, // 1s, 2s, 3s...
            'exponential' => (int) (1_000 * 2 ** ($attempt - 1)), // 1s, 2s, 4s...
            default => 1_000,
        };
    }

    /**
     * Get the rate limit configuration for a request.
     */
    private function getConfigForRequest(Request $request): ?RateLimitConfig
    {
        $attribute = $this->getRateLimitAttribute($request);

        if ($attribute instanceof RateLimit) {
            return new RateLimitConfig(
                requests: $attribute->requests,
                perSeconds: $attribute->perSeconds,
                retry: $attribute->retry,
                maxRetries: $attribute->maxRetries,
                backoff: $attribute->backoff,
            );
        }

        return $this->defaultConfig;
    }

    /**
     * Resolve the rate limit key for a request.
     */
    private function resolveKey(Connector $connector, Request $request): string
    {
        $attribute = $this->getRateLimitAttribute($request);

        if ($attribute?->key !== null) {
            return $this->resolveCustomKey($request, $attribute->key);
        }

        // Default key based on connector class
        return $connector::class;
    }

    /**
     * Resolve a custom key with placeholder substitution.
     */
    private function resolveCustomKey(Request $request, string $keyTemplate): string
    {
        return preg_replace_callback('/\{(\w+)\}/', function (array $matches) use ($request): string {
            $property = $matches[1];

            $reflection = new ReflectionClass($request);

            if ($reflection->hasProperty($property)) {
                $prop = $reflection->getProperty($property);
                $value = $prop->getValue($request);

                return is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))
                    ? (string) $value
                    : $matches[0];
            }

            return $matches[0];
        }, $keyTemplate) ?? $keyTemplate;
    }

    /**
     * Get the RateLimit attribute from a request.
     */
    private function getRateLimitAttribute(Request $request): ?RateLimit
    {
        $reflection = new ReflectionClass($request);
        $attributes = $reflection->getAttributes(RateLimit::class);

        if ($attributes === []) {
            return null;
        }

        return $attributes[0]->newInstance();
    }
}

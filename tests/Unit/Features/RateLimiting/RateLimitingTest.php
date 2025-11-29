<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Relay\Core\Connector;
use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;
use Cline\Relay\Features\RateLimiting\CacheStore;
use Cline\Relay\Features\RateLimiting\MemoryStore;
use Cline\Relay\Features\RateLimiting\RateLimitConfig;
use Cline\Relay\Features\RateLimiting\RateLimiter;
use Cline\Relay\Features\RateLimiting\RateLimitInfo;
use Cline\Relay\Support\Attributes\RateLimiting\ConcurrencyLimit;
use Cline\Relay\Support\Attributes\RateLimiting\RateLimit;
use Cline\Relay\Support\Exceptions\Client\RateLimitException;
use Illuminate\Support\Facades\Date;
use Psr\SimpleCache\CacheInterface;
use Tests\Fixtures\RateLimiting\TestBackoffStrategy;

function createTestConnectorForRateLimiting(): Connector
{
    return new class() extends Connector
    {
        public function baseUrl(): string
        {
            return 'https://api.example.com';
        }
    };
}

describe('RateLimit Attribute', function (): void {
    it('configures rate limit', function (): void {
        $rateLimit = new RateLimit(requests: 100, perSeconds: 60);

        expect($rateLimit->requests)->toBe(100);
        expect($rateLimit->perSeconds)->toBe(60);
    });

    it('supports custom key', function (): void {
        $rateLimit = new RateLimit(requests: 100, perSeconds: 60, key: 'api');

        expect($rateLimit->key)->toBe('api');
    });

    it('supports retry configuration', function (): void {
        $rateLimit = new RateLimit(
            requests: 100,
            perSeconds: 60,
            retry: true,
            maxRetries: 5,
            backoff: 'linear',
        );

        expect($rateLimit->retry)->toBeTrue();
        expect($rateLimit->maxRetries)->toBe(5);
        expect($rateLimit->backoff)->toBe('linear');
    });
});

describe('ConcurrencyLimit Attribute', function (): void {
    it('configures concurrency limit', function (): void {
        $limit = new ConcurrencyLimit(limit: 5);

        expect($limit->limit)->toBe(5);
    });
});

describe('RateLimitConfig', function (): void {
    it('creates with required parameters', function (): void {
        $config = new RateLimitConfig(requests: 100, perSeconds: 60);

        expect($config->requests)->toBe(100);
        expect($config->perSeconds)->toBe(60);
        expect($config->retry)->toBeFalse();
    });

    it('accepts all parameters', function (): void {
        $config = new RateLimitConfig(
            requests: 100,
            perSeconds: 60,
            retry: true,
            maxRetries: 5,
            backoff: 'linear',
        );

        expect($config->retry)->toBeTrue();
        expect($config->maxRetries)->toBe(5);
        expect($config->backoff)->toBe('linear');
    });
});

describe('MemoryStore', function (): void {
    it('allows requests within limit', function (): void {
        $store = new MemoryStore();

        expect($store->attempt('test', 3, 60))->toBeTrue();
        expect($store->attempt('test', 3, 60))->toBeTrue();
        expect($store->attempt('test', 3, 60))->toBeTrue();
    });

    it('blocks requests over limit', function (): void {
        $store = new MemoryStore();

        expect($store->attempt('test', 2, 60))->toBeTrue();
        expect($store->attempt('test', 2, 60))->toBeTrue();
        expect($store->attempt('test', 2, 60))->toBeFalse();
    });

    it('tracks remaining requests', function (): void {
        $store = new MemoryStore();

        $store->attempt('test', 5, 60);
        $store->attempt('test', 5, 60);

        expect($store->getRemaining('test', 5))->toBe(3);
    });

    it('resets rate limit', function (): void {
        $store = new MemoryStore();

        $store->attempt('test', 2, 60);
        $store->attempt('test', 2, 60);

        expect($store->attempt('test', 2, 60))->toBeFalse();

        $store->reset('test');
        expect($store->attempt('test', 2, 60))->toBeTrue();
    });

    it('clears all rate limits', function (): void {
        $store = new MemoryStore();

        $store->attempt('test1', 1, 60);
        $store->attempt('test2', 1, 60);

        $store->clear();

        expect($store->attempt('test1', 1, 60))->toBeTrue();
        expect($store->attempt('test2', 1, 60))->toBeTrue();
    });

    test('returns zero count when bucket has expired in getCount', function (): void {
        // Arrange
        $store = new MemoryStore();

        // Travel back in time to create an expired bucket
        Date::setTestNow(Date::now()->subSeconds(120));
        $store->attempt('expired-key', 5, 60);
        $store->attempt('expired-key', 5, 60);

        // Travel back to current time
        Date::setTestNow();

        // Act
        $count = $store->getCount('expired-key');

        // Assert - bucket has expired (120s > 60s window), should return 0
        expect($count)->toBe(0);
    });
});

describe('RateLimiter', function (): void {
    it('allows requests within limit', function (): void {
        $store = new MemoryStore();
        $config = new RateLimitConfig(requests: 3, perSeconds: 60);
        $limiter = new RateLimiter($store, $config);
        $connector = createTestConnectorForRateLimiting();

        $request = new class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        // Should not throw for first 3 requests
        $limiter->check($connector, $request);
        $limiter->check($connector, $request);
        $limiter->check($connector, $request);

        expect(true)->toBeTrue(); // Made it here without exception
    });

    it('throws when limit exceeded', function (): void {
        $store = new MemoryStore();
        $config = new RateLimitConfig(requests: 2, perSeconds: 60);
        $limiter = new RateLimiter($store, $config);
        $connector = createTestConnectorForRateLimiting();

        $request = new class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        $limiter->check($connector, $request);
        $limiter->check($connector, $request);

        $limiter->check($connector, $request);
    })->throws(RateLimitException::class);

    it('calculates exponential backoff', function (): void {
        $store = new MemoryStore();
        $config = new RateLimitConfig(requests: 100, perSeconds: 60, backoff: 'exponential');
        $limiter = new RateLimiter($store, $config);

        $request = new class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        expect($limiter->calculateBackoff($request, $config, 1))->toBe(1_000);
        expect($limiter->calculateBackoff($request, $config, 2))->toBe(2_000);
        expect($limiter->calculateBackoff($request, $config, 3))->toBe(4_000);
    });

    it('calculates linear backoff', function (): void {
        $store = new MemoryStore();
        $config = new RateLimitConfig(requests: 100, perSeconds: 60, backoff: 'linear');
        $limiter = new RateLimiter($store, $config);

        $request = new class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        expect($limiter->calculateBackoff($request, $config, 1))->toBe(1_000);
        expect($limiter->calculateBackoff($request, $config, 2))->toBe(2_000);
        expect($limiter->calculateBackoff($request, $config, 3))->toBe(3_000);
    });

    it('calculates default backoff for unknown strategy', function (): void {
        $store = new MemoryStore();
        $config = new RateLimitConfig(requests: 100, perSeconds: 60, backoff: 'unknown');
        $limiter = new RateLimiter($store, $config);

        $request = new class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        expect($limiter->calculateBackoff($request, $config, 1))->toBe(1_000);
        expect($limiter->calculateBackoff($request, $config, 2))->toBe(1_000);
        expect($limiter->calculateBackoff($request, $config, 5))->toBe(1_000);
    });

    it('uses BackoffStrategy class when specified', function (): void {
        $store = new MemoryStore();
        $config = new RateLimitConfig(
            requests: 100,
            perSeconds: 60,
            backoff: TestBackoffStrategy::class,
        );
        $limiter = new RateLimiter($store, $config);

        $request = new class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        // TestBackoffStrategy uses fibonacci: 1s, 1s, 2s, 3s, 5s...
        expect($limiter->calculateBackoff($request, $config, 1))->toBe(1_000);
        expect($limiter->calculateBackoff($request, $config, 2))->toBe(1_000);
        expect($limiter->calculateBackoff($request, $config, 3))->toBe(2_000);
        expect($limiter->calculateBackoff($request, $config, 4))->toBe(3_000);
        expect($limiter->calculateBackoff($request, $config, 5))->toBe(5_000);
    });

    it('BackoffStrategy respects retry-after header', function (): void {
        $store = new MemoryStore();
        $config = new RateLimitConfig(
            requests: 100,
            perSeconds: 60,
            backoff: TestBackoffStrategy::class,
        );
        $limiter = new RateLimiter($store, $config);

        $request = new class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        // When retry-after is set, TestBackoffStrategy respects it
        expect($limiter->calculateBackoff($request, $config, 1, 30))->toBe(30_000);
    });

    it('allows requests when no config is set', function (): void {
        $store = new MemoryStore();
        $limiter = new RateLimiter($store);
        $connector = createTestConnectorForRateLimiting();

        $request = new class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        // Should not throw - no rate limiting applied
        $limiter->check($connector, $request);
        $limiter->check($connector, $request);
        $limiter->check($connector, $request);
        $limiter->check($connector, $request);
        $limiter->check($connector, $request);

        expect(true)->toBeTrue(); // Made it here without exception
    });
});

describe('RateLimiter::getState()', function (): void {
    it('returns rate limit state for request with default config', function (): void {
        $store = new MemoryStore();
        $config = new RateLimitConfig(requests: 5, perSeconds: 60);
        $limiter = new RateLimiter($store, $config);
        $connector = createTestConnectorForRateLimiting();

        $request = new class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        // Make 2 requests
        $limiter->check($connector, $request);
        $limiter->check($connector, $request);

        $state = $limiter->getState($connector, $request);

        expect($state)->not->toBeNull();
        expect($state->limit())->toBe(5);
        expect($state->remaining())->toBe(3);
        expect($state->reset())->not->toBeNull();
    });

    it('returns null when no config is set', function (): void {
        $store = new MemoryStore();
        $limiter = new RateLimiter($store);
        $connector = createTestConnectorForRateLimiting();

        $request = new class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        $state = $limiter->getState($connector, $request);

        expect($state)->toBeNull();
    });

    it('returns state with attribute-based config', function (): void {
        $store = new MemoryStore();
        $limiter = new RateLimiter($store);
        $connector = createTestConnectorForRateLimiting();

        $request = new #[RateLimit(requests: 10, perSeconds: 60)] class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        $state = $limiter->getState($connector, $request);

        expect($state)->not->toBeNull();
        expect($state->limit())->toBe(10);
        expect($state->remaining())->toBe(10);
    });
});

describe('RateLimiter::getRetryConfig()', function (): void {
    it('returns config when retry is enabled', function (): void {
        $store = new MemoryStore();
        $limiter = new RateLimiter($store);

        $request = new #[RateLimit(requests: 10, perSeconds: 60, retry: true, maxRetries: 3)] class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        $retryConfig = $limiter->getRetryConfig($request);

        expect($retryConfig)->not->toBeNull();
        expect($retryConfig->retry)->toBeTrue();
        expect($retryConfig->maxRetries)->toBe(3);
        expect($retryConfig->requests)->toBe(10);
        expect($retryConfig->perSeconds)->toBe(60);
    });

    it('returns null when retry is disabled', function (): void {
        $store = new MemoryStore();
        $limiter = new RateLimiter($store);

        $request = new #[RateLimit(requests: 10, perSeconds: 60, retry: false)] class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        $retryConfig = $limiter->getRetryConfig($request);

        expect($retryConfig)->toBeNull();
    });

    it('returns null when no config is set', function (): void {
        $store = new MemoryStore();
        $limiter = new RateLimiter($store);

        $request = new class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        $retryConfig = $limiter->getRetryConfig($request);

        expect($retryConfig)->toBeNull();
    });

    it('returns null when default config has retry disabled', function (): void {
        $store = new MemoryStore();
        $config = new RateLimitConfig(requests: 10, perSeconds: 60, retry: false);
        $limiter = new RateLimiter($store, $config);

        $request = new class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        $retryConfig = $limiter->getRetryConfig($request);

        expect($retryConfig)->toBeNull();
    });
});

describe('RateLimiter with RateLimit attribute', function (): void {
    it('uses attribute config over default config', function (): void {
        $store = new MemoryStore();
        $defaultConfig = new RateLimitConfig(requests: 100, perSeconds: 60);
        $limiter = new RateLimiter($store, $defaultConfig);
        $connector = createTestConnectorForRateLimiting();

        $request = new #[RateLimit(requests: 2, perSeconds: 60)] class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        // Should allow 2 requests (attribute config) not 100 (default config)
        $limiter->check($connector, $request);
        $limiter->check($connector, $request);

        expect(fn () => $limiter->check($connector, $request))->toThrow(RateLimitException::class);
    });

    it('creates config from attribute with all parameters', function (): void {
        $store = new MemoryStore();
        $limiter = new RateLimiter($store);

        $request = new #[RateLimit(requests: 50, perSeconds: 120, retry: true, maxRetries: 5, backoff: 'exponential', )] class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        $retryConfig = $limiter->getRetryConfig($request);

        expect($retryConfig)->not->toBeNull();
        expect($retryConfig->requests)->toBe(50);
        expect($retryConfig->perSeconds)->toBe(120);
        expect($retryConfig->retry)->toBeTrue();
        expect($retryConfig->maxRetries)->toBe(5);
        expect($retryConfig->backoff)->toBe('exponential');
    });

    it('uses custom key from attribute', function (): void {
        $store = new MemoryStore();
        $limiter = new RateLimiter($store);
        $connector = createTestConnectorForRateLimiting();

        $request = new #[RateLimit(requests: 2, perSeconds: 60, key: 'custom-api-key')] class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        // Make 2 requests
        $limiter->check($connector, $request);
        $limiter->check($connector, $request);

        // Third should fail due to custom key being rate limited
        expect(fn () => $limiter->check($connector, $request))->toThrow(RateLimitException::class);
    });
});

describe('RateLimiter custom key resolution', function (): void {
    it('resolves placeholder in custom key template', function (): void {
        $store = new MemoryStore();
        $limiter = new RateLimiter($store);
        $connector = createTestConnectorForRateLimiting();

        $request = new #[RateLimit(requests: 2, perSeconds: 60, key: 'user-{userId}')] class() extends Request
        {
            public function __construct(
                public readonly string $userId = 'user123',
            ) {}

            public function endpoint(): string
            {
                return '/test';
            }
        };

        // Make 2 requests with same userId
        $limiter->check($connector, $request);
        $limiter->check($connector, $request);

        // Third should fail - rate limited by user-user123 key
        expect(fn () => $limiter->check($connector, $request))->toThrow(RateLimitException::class);
    });

    it('resolves multiple placeholders in custom key template', function (): void {
        $store = new MemoryStore();
        $limiter = new RateLimiter($store);
        $connector = createTestConnectorForRateLimiting();

        $request = new #[RateLimit(requests: 2, perSeconds: 60, key: 'tenant-{tenantId}-user-{userId}')] class() extends Request
        {
            public function __construct(
                public readonly string $tenantId = 'tenant456',
                public readonly string $userId = 'user789',
            ) {}

            public function endpoint(): string
            {
                return '/test';
            }
        };

        // Make 2 requests
        $limiter->check($connector, $request);
        $limiter->check($connector, $request);

        // Third should fail
        expect(fn () => $limiter->check($connector, $request))->toThrow(RateLimitException::class);
    });

    it('keeps placeholder when property does not exist', function (): void {
        $store = new MemoryStore();
        $limiter = new RateLimiter($store);
        $connector = createTestConnectorForRateLimiting();

        $request = new #[RateLimit(requests: 2, perSeconds: 60, key: 'user-{nonExistentProperty}')] class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        // Make 2 requests - should use key 'user-{nonExistentProperty}' as-is
        $limiter->check($connector, $request);
        $limiter->check($connector, $request);

        // Third should fail
        expect(fn () => $limiter->check($connector, $request))->toThrow(RateLimitException::class);
    });

    it('isolates rate limits by different user IDs', function (): void {
        $store = new MemoryStore();
        $limiter = new RateLimiter($store);
        $connector = createTestConnectorForRateLimiting();

        // Create two separate request instances with different user IDs
        $request1 = new #[RateLimit(requests: 2, perSeconds: 60, key: 'user-{userId}')] class('user1') extends Request
        {
            public function __construct(
                public readonly string $userId,
            ) {}

            public function endpoint(): string
            {
                return '/test';
            }
        };

        $request2 = new #[RateLimit(requests: 2, perSeconds: 60, key: 'user-{userId}')] class('user2') extends Request
        {
            public function __construct(
                public readonly string $userId,
            ) {}

            public function endpoint(): string
            {
                return '/test';
            }
        };

        // User1 makes 2 requests
        $limiter->check($connector, $request1);
        $limiter->check($connector, $request1);

        // User2 should still be able to make requests (different key)
        $limiter->check($connector, $request2);
        $limiter->check($connector, $request2);

        // Both users should be rate limited now
        expect(fn () => $limiter->check($connector, $request1))->toThrow(RateLimitException::class);
        expect(fn () => $limiter->check($connector, $request2))->toThrow(RateLimitException::class);
    });
});

describe('RateLimitInfo', function (): void {
    describe('Happy Paths', function (): void {
        test('parses rate limit headers from response successfully', function (): void {
            // Arrange
            $response = Response::make([], 200, [
                'X-RateLimit-Limit' => '100',
                'X-RateLimit-Remaining' => '42',
                'X-RateLimit-Reset' => (string) (Date::now()->getTimestamp() + 60),
            ]);

            // Act
            $info = $response->rateLimit();

            // Assert
            expect($info)->not->toBeNull()
                ->and($info->limit())->toBe(100)
                ->and($info->remaining())->toBe(42);
        });

        test('calculates seconds until reset from timestamp', function (): void {
            // Arrange
            $resetTime = Date::now()->getTimestamp() + 30;
            $response = Response::make([], 200, [
                'X-RateLimit-Limit' => '100',
                'X-RateLimit-Remaining' => '0',
                'X-RateLimit-Reset' => (string) $resetTime,
            ]);

            // Act
            $info = $response->rateLimit();

            // Assert
            expect($info->secondsUntilReset())->toBeLessThanOrEqual(30)
                ->and($info->secondsUntilReset())->toBeGreaterThan(0);
        });

        test('returns DateTimeImmutable for reset() when timestamp is provided', function (): void {
            // Arrange
            $resetTime = Date::now()->getTimestamp() + 60;
            $info = new RateLimitInfo(
                limit: 100,
                remaining: 42,
                reset: $resetTime,
            );

            // Act
            $result = $info->reset();

            // Assert
            expect($result)->toBeInstanceOf(DateTimeImmutable::class)
                ->and($result->getTimestamp())->toBe($resetTime);
        });
    });

    describe('Edge Cases', function (): void {
        test('returns null for reset() when reset timestamp is null', function (): void {
            // Arrange
            $info = new RateLimitInfo(
                limit: 100,
                remaining: 42,
                reset: null,
            );

            // Act & Assert
            expect($info->reset())->toBeNull();
        });

        test('returns null for secondsUntilReset() when reset timestamp is null', function (): void {
            // Arrange
            $info = new RateLimitInfo(
                limit: 100,
                remaining: 42,
                reset: null,
            );

            // Act & Assert
            expect($info->secondsUntilReset())->toBeNull();
        });

        test('returns null when rate limit headers are missing from response', function (): void {
            // Arrange
            $response = Response::make([], 200);

            // Act
            $info = $response->rateLimit();

            // Assert
            expect($info)->toBeNull();
        });
    });
});

describe('RateLimitException', function (): void {
    it('can be created without request/response via exceeded()', function (): void {
        $exception = RateLimitException::exceeded(
            retryAfterSeconds: 30,
            limit: 100,
            remaining: 0,
        );

        expect($exception->getMessage())->toBe('Rate limit exceeded');
        expect($exception->retryAfter())->toBe(30);
        expect($exception->limit())->toBe(100);
        expect($exception->remaining())->toBe(0);
        expect($exception->isClientSide())->toBeTrue();
        expect($exception->isServerSide())->toBeFalse();
    });

    it('can be created from response', function (): void {
        $request = new class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        $response = Response::make([], 429, [
            'Retry-After' => '60',
            'X-RateLimit-Limit' => '100',
            'X-RateLimit-Remaining' => '0',
        ]);

        $exception = RateLimitException::fromResponse($request, $response);

        expect($exception->retryAfter())->toBe(60);
        expect($exception->limit())->toBe(100);
        expect($exception->remaining())->toBe(0);
        expect($exception->isServerSide())->toBeTrue();
        expect($exception->isClientSide())->toBeFalse();
    });
});

describe('CacheStore', function (): void {
    beforeEach(function (): void {
        $this->mockCache = new class() implements CacheInterface
        {
            private array $data = [];

            public function get(string $key, mixed $default = null): mixed
            {
                return $this->data[$key] ?? $default;
            }

            public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
            {
                $this->data[$key] = $value;

                return true;
            }

            public function delete(string $key): bool
            {
                unset($this->data[$key]);

                return true;
            }

            public function clear(): bool
            {
                $this->data = [];

                return true;
            }

            public function getMultiple(iterable $keys, mixed $default = null): iterable
            {
                $result = [];

                foreach ($keys as $key) {
                    $result[$key] = $this->get($key, $default);
                }

                return $result;
            }

            public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
            {
                foreach ($values as $key => $value) {
                    $this->set($key, $value, $ttl);
                }

                return true;
            }

            public function deleteMultiple(iterable $keys): bool
            {
                foreach ($keys as $key) {
                    $this->delete($key);
                }

                return true;
            }

            public function has(string $key): bool
            {
                return array_key_exists($key, $this->data);
            }
        };
    });

    describe('Happy Paths', function (): void {
        test('allows requests within limit', function (): void {
            $store = new CacheStore($this->mockCache);

            expect($store->attempt('test', 3, 60))->toBeTrue()
                ->and($store->attempt('test', 3, 60))->toBeTrue()
                ->and($store->attempt('test', 3, 60))->toBeTrue();
        });

        test('tracks remaining requests correctly', function (): void {
            $store = new CacheStore($this->mockCache);

            $store->attempt('test', 5, 60);
            $store->attempt('test', 5, 60);

            expect($store->getRemaining('test', 5))->toBe(3);
        });

        test('tracks count accurately', function (): void {
            $store = new CacheStore($this->mockCache);

            $store->attempt('test', 5, 60);
            $store->attempt('test', 5, 60);

            expect($store->getCount('test'))->toBe(2);
        });

        test('returns reset time as future timestamp', function (): void {
            $store = new CacheStore($this->mockCache);

            $store->attempt('test', 5, 60);

            $resetTime = $store->getResetTime('test');

            expect($resetTime)->toBeInt()
                ->and($resetTime)->toBeGreaterThan(Date::now()->getTimestamp());
        });

        test('resets rate limit successfully', function (): void {
            $store = new CacheStore($this->mockCache);

            $store->attempt('test', 2, 60);
            $store->attempt('test', 2, 60);

            $store->reset('test');

            expect($store->attempt('test', 2, 60))->toBeTrue();
        });

        test('clears all rate limits when calling clear', function (): void {
            $store = new CacheStore($this->mockCache);

            $store->attempt('test1', 1, 60);
            $store->attempt('test2', 1, 60);

            $store->clear();

            expect($store->getCount('test1'))->toBe(0)
                ->and($store->getCount('test2'))->toBe(0);
        });

        test('creates new bucket after window expires', function (): void {
            $store = new CacheStore($this->mockCache);

            // Manually set an expired bucket in the cache
            $expiredBucket = [
                'count' => 5,
                'window_start' => Date::now()->getTimestamp() - 120,
                'window_size' => 60,
            ];
            $this->mockCache->set('relay:ratelimit:expired', $expiredBucket);

            // Should allow request as window has expired
            expect($store->attempt('expired', 5, 60))->toBeTrue()
                ->and($store->getCount('expired'))->toBe(1);
        });

        test('isolates rate limits by different keys', function (): void {
            $store = new CacheStore($this->mockCache);

            $store->attempt('user1', 2, 60);
            $store->attempt('user1', 2, 60);
            $store->attempt('user2', 2, 60);

            expect($store->getCount('user1'))->toBe(2)
                ->and($store->getCount('user2'))->toBe(1)
                ->and($store->attempt('user2', 2, 60))->toBeTrue();
        });
    });

    describe('Sad Paths', function (): void {
        test('blocks requests over limit', function (): void {
            $store = new CacheStore($this->mockCache);

            expect($store->attempt('test', 2, 60))->toBeTrue()
                ->and($store->attempt('test', 2, 60))->toBeTrue()
                ->and($store->attempt('test', 2, 60))->toBeFalse();
        });

        test('returns false when limit is reached exactly', function (): void {
            $store = new CacheStore($this->mockCache);

            $store->attempt('test', 3, 60);
            $store->attempt('test', 3, 60);
            $store->attempt('test', 3, 60);

            expect($store->attempt('test', 3, 60))->toBeFalse();
        });
    });

    describe('Edge Cases', function (): void {
        test('returns zero count for non-existent key', function (): void {
            $store = new CacheStore($this->mockCache);

            expect($store->getCount('nonexistent'))->toBe(0);
        });

        test('returns null reset time for non-existent key', function (): void {
            $store = new CacheStore($this->mockCache);

            expect($store->getResetTime('nonexistent'))->toBeNull();
        });

        test('returns zero count when bucket has expired', function (): void {
            $store = new CacheStore($this->mockCache);

            // Manually set an expired bucket
            $expiredBucket = [
                'count' => 3,
                'window_start' => Date::now()->getTimestamp() - 120,
                'window_size' => 60,
            ];
            $this->mockCache->set('relay:ratelimit:expired', $expiredBucket);

            expect($store->getCount('expired'))->toBe(0);
        });

        test('returns full limit as remaining for non-existent key', function (): void {
            $store = new CacheStore($this->mockCache);

            expect($store->getRemaining('nonexistent', 10))->toBe(10);
        });

        test('handles zero limit gracefully', function (): void {
            $store = new CacheStore($this->mockCache);

            expect($store->attempt('test', 0, 60))->toBeFalse();
        });

        test('handles single request limit', function (): void {
            $store = new CacheStore($this->mockCache);

            expect($store->attempt('test', 1, 60))->toBeTrue()
                ->and($store->attempt('test', 1, 60))->toBeFalse();
        });

        test('calculates correct TTL for cache entry', function (): void {
            $store = new CacheStore($this->mockCache);

            $store->attempt('test', 5, 60);

            // Verify bucket exists with correct window size
            $bucket = $this->mockCache->get('relay:ratelimit:test');
            expect($bucket)->not->toBeNull()
                ->and($bucket['window_size'])->toBe(60);
        });

        test('maintains count consistency across multiple attempts', function (): void {
            $store = new CacheStore($this->mockCache);

            for ($i = 0; $i < 10; ++$i) {
                $store->attempt('test', 10, 60);
            }

            expect($store->getCount('test'))->toBe(10)
                ->and($store->getRemaining('test', 10))->toBe(0);
        });
    });
});

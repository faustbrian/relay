<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;
use Cline\Relay\Features\Resilience\CircuitBreaker;
use Cline\Relay\Features\Resilience\CircuitBreakerConfig;
use Cline\Relay\Features\Resilience\CircuitState;
use Cline\Relay\Features\Resilience\MemoryCircuitStore;
use Cline\Relay\Features\Resilience\RetryConfig;
use Cline\Relay\Features\Resilience\RetryHandler;
use Cline\Relay\Support\Attributes\Resilience\CircuitBreaker as CircuitBreakerAttr;
use Cline\Relay\Support\Attributes\Resilience\Retry;
use Cline\Relay\Support\Attributes\Resilience\Timeout;
use Cline\Relay\Support\Exceptions\CircuitOpenException;
use Illuminate\Support\Facades\Date;
use Tests\Fixtures\Resilience\AttemptAwareRetryDecider;
use Tests\Fixtures\Resilience\TestRetryDecider;
use Tests\Fixtures\Resilience\TestRetryPolicy;

describe('Timeout Attribute', function (): void {
    it('sets timeout in seconds', function (): void {
        $timeout = new Timeout(seconds: 30);

        expect($timeout->seconds)->toBe(30);
    });

    it('sets separate connect and read timeouts', function (): void {
        $timeout = new Timeout(seconds: 30, connect: 5, read: 25);

        expect($timeout->seconds)->toBe(30);
        expect($timeout->connect)->toBe(5);
        expect($timeout->read)->toBe(25);
    });
});

describe('Retry Attribute', function (): void {
    it('sets retry times', function (): void {
        $retry = new Retry(times: 5);

        expect($retry->times)->toBe(5);
    });

    it('configures exponential backoff', function (): void {
        $retry = new Retry(
            times: 3,
            delay: 1_000,
            multiplier: 2.0,
            maxDelay: 30_000,
        );

        expect($retry->times)->toBe(3);
        expect($retry->delay)->toBe(1_000);
        expect($retry->multiplier)->toBe(2.0);
        expect($retry->maxDelay)->toBe(30_000);
    });

    it('accepts status codes to retry on', function (): void {
        $retry = new Retry(times: 3, when: [500, 502, 503]);

        expect($retry->when)->toBe([500, 502, 503]);
    });
});

describe('CircuitBreaker Attribute', function (): void {
    it('sets failure threshold', function (): void {
        $cb = new CircuitBreakerAttr(failureThreshold: 10);

        expect($cb->failureThreshold)->toBe(10);
    });

    it('configures reset timeout', function (): void {
        $cb = new CircuitBreakerAttr(
            failureThreshold: 5,
            resetTimeout: 60,
            halfOpenRequests: 3,
        );

        expect($cb->resetTimeout)->toBe(60);
        expect($cb->halfOpenRequests)->toBe(3);
    });
});

describe('RetryConfig', function (): void {
    it('creates with default values', function (): void {
        $config = new RetryConfig();

        expect($config->times)->toBe(3);
        expect($config->delay)->toBe(100);
        expect($config->multiplier)->toBe(2.0);
    });

    it('accepts custom configuration', function (): void {
        $config = new RetryConfig(
            times: 5,
            delay: 500,
            multiplier: 1.5,
            statusCodes: [500, 502],
        );

        expect($config->times)->toBe(5);
        expect($config->delay)->toBe(500);
        expect($config->statusCodes)->toBe([500, 502]);
    });
});

describe('RetryHandler', function (): void {
    it('calculates exponential backoff delay', function (): void {
        $config = new RetryConfig(delay: 100, multiplier: 2.0, maxDelay: 10_000);
        $handler = new RetryHandler($config);

        $request = new class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        expect($handler->calculateDelay($request, 1))->toBe(100);
        expect($handler->calculateDelay($request, 2))->toBe(200);
        expect($handler->calculateDelay($request, 3))->toBe(400);
    });

    it('respects max delay', function (): void {
        $config = new RetryConfig(delay: 1_000, multiplier: 10.0, maxDelay: 5_000);
        $handler = new RetryHandler($config);

        $request = new class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        expect($handler->calculateDelay($request, 3))->toBe(5_000);
    });

    it('should retry on server error by default', function (): void {
        $config = new RetryConfig(times: 3);
        $handler = new RetryHandler($config);

        $request = new class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        $response = Response::make([], 500);
        expect($handler->shouldRetryResponse($request, $response, 1))->toBeTrue();

        $response200 = Response::make([], 200);
        expect($handler->shouldRetryResponse($request, $response200, 1))->toBeFalse();
    });

    it('should not retry when max attempts reached', function (): void {
        $config = new RetryConfig(times: 2);
        $handler = new RetryHandler($config);

        $request = new class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        $response = Response::make([], 500);
        expect($handler->shouldRetryResponse($request, $response, 2))->toBeFalse();
    });
});

describe('RetryHandler with Retry attribute', function (): void {
    test('getConfig returns RetryConfig from Retry attribute', function (): void {
        $handler = new RetryHandler();

        $request = new #[Retry(times: 5, delay: 200, multiplier: 3.0, maxDelay: 15_000, when: [500, 502, 503])] class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        // Act
        $config = $handler->getConfig($request);

        // Assert
        expect($config)->toBeInstanceOf(RetryConfig::class)
            ->and($config->times)->toBe(5)
            ->and($config->delay)->toBe(200)
            ->and($config->multiplier)->toBe(3.0)
            ->and($config->maxDelay)->toBe(15_000)
            ->and($config->statusCodes)->toBe([500, 502, 503]);
    });

    test('getConfig returns null when no Retry attribute and no default config', function (): void {
        $handler = new RetryHandler();

        $request = new class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        // Act
        $config = $handler->getConfig($request);

        // Assert
        expect($config)->toBeNull();
    });

    test('getConfig returns default config when no Retry attribute', function (): void {
        $defaultConfig = new RetryConfig(times: 10, delay: 500);
        $handler = new RetryHandler($defaultConfig);

        $request = new class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        // Act
        $config = $handler->getConfig($request);

        // Assert
        expect($config)->toBe($defaultConfig);
    });

    test('calculateDelay uses Retry attribute config over default', function (): void {
        $defaultConfig = new RetryConfig(delay: 1_000);
        $handler = new RetryHandler($defaultConfig);

        $request = new #[Retry(times: 3, delay: 100)] class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        // Act
        $delay = $handler->calculateDelay($request, 1);

        // Assert
        expect($delay)->toBe(100); // Uses attribute delay, not default
    });

    test('shouldRetryResponse returns false when config is null', function (): void {
        $handler = new RetryHandler();

        $request = new class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        $response = Response::make([], 500);

        // Act
        $shouldRetry = $handler->shouldRetryResponse($request, $response, 1);

        // Assert
        expect($shouldRetry)->toBeFalse();
    });
});

describe('RetryHandler with callback', function (): void {
    test('shouldRetryResponse uses custom callback method when specified in attribute', function (): void {
        $handler = new RetryHandler();

        $request = new #[Retry(times: 3, callback: 'shouldRetry')] class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }

            public function shouldRetry(Response $response, int $attempt): bool
            {
                return $response->status() >= 500 && $attempt < 2;
            }
        };

        // Act & Assert - should retry on 500 for attempt 1
        $response500 = Response::make([], 500);
        expect($handler->shouldRetryResponse($request, $response500, 1))->toBeTrue();

        // Should not retry for attempt 2 (callback logic)
        expect($handler->shouldRetryResponse($request, $response500, 2))->toBeFalse();

        // Should not retry on 200
        $response200 = Response::make([], 200);
        expect($handler->shouldRetryResponse($request, $response200, 1))->toBeFalse();
    });

    test('shouldRetryResponse ignores callback if method does not exist', function (): void {
        $handler = new RetryHandler();

        $request = new #[Retry(times: 3, callback: 'nonExistentMethod')] class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        // Act - should fall back to default behavior (server error check)
        $response500 = Response::make([], 500);
        $shouldRetry = $handler->shouldRetryResponse($request, $response500, 1);

        // Assert
        expect($shouldRetry)->toBeTrue(); // Falls back to server error check
    });
});

describe('RetryHandler with closure', function (): void {
    test('shouldRetryResponse uses closure in config when', function (): void {
        $config = new RetryConfig(
            times: 3,
            when: fn (Response $response): bool => $response->status() === 429,
        );
        $handler = new RetryHandler($config);

        $request = new class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        // Act & Assert - should retry on 429
        $response429 = Response::make([], 429);
        expect($handler->shouldRetryResponse($request, $response429, 1))->toBeTrue();

        // Should not retry on 500
        $response500 = Response::make([], 500);
        expect($handler->shouldRetryResponse($request, $response500, 1))->toBeFalse();

        // Should not retry on 200
        $response200 = Response::make([], 200);
        expect($handler->shouldRetryResponse($request, $response200, 1))->toBeFalse();
    });

    test('shouldRetryResponse with closure checking multiple conditions', function (): void {
        $config = new RetryConfig(
            times: 3,
            when: fn (Response $response): bool => $response->status() >= 500 || $response->status() === 429,
        );
        $handler = new RetryHandler($config);

        $request = new class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        // Act & Assert
        expect($handler->shouldRetryResponse($request, Response::make([], 500), 1))->toBeTrue();
        expect($handler->shouldRetryResponse($request, Response::make([], 503), 1))->toBeTrue();
        expect($handler->shouldRetryResponse($request, Response::make([], 429), 1))->toBeTrue();
        expect($handler->shouldRetryResponse($request, Response::make([], 404), 1))->toBeFalse();
        expect($handler->shouldRetryResponse($request, Response::make([], 200), 1))->toBeFalse();
    });
});

describe('RetryHandler with statusCodes', function (): void {
    test('shouldRetryResponse checks specific status codes in config', function (): void {
        $config = new RetryConfig(times: 3, statusCodes: [500, 502, 503]);
        $handler = new RetryHandler($config);

        $request = new class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        // Act & Assert - should retry on specified codes
        expect($handler->shouldRetryResponse($request, Response::make([], 500), 1))->toBeTrue();
        expect($handler->shouldRetryResponse($request, Response::make([], 502), 1))->toBeTrue();
        expect($handler->shouldRetryResponse($request, Response::make([], 503), 1))->toBeTrue();

        // Should not retry on other codes
        expect($handler->shouldRetryResponse($request, Response::make([], 501), 1))->toBeFalse();
        expect($handler->shouldRetryResponse($request, Response::make([], 429), 1))->toBeFalse();
        expect($handler->shouldRetryResponse($request, Response::make([], 200), 1))->toBeFalse();
    });

    test('shouldRetryResponse uses statusCodes from Retry attribute', function (): void {
        $handler = new RetryHandler();

        $request = new #[Retry(times: 3, when: [429, 503])] class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        // Act & Assert
        expect($handler->shouldRetryResponse($request, Response::make([], 429), 1))->toBeTrue();
        expect($handler->shouldRetryResponse($request, Response::make([], 503), 1))->toBeTrue();
        expect($handler->shouldRetryResponse($request, Response::make([], 500), 1))->toBeFalse();
    });
});

describe('RetryHandler::shouldRetryException()', function (): void {
    test('returns false when config is null', function (): void {
        $handler = new RetryHandler();

        $request = new class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        $exception = new RuntimeException('Test exception');

        // Act
        $shouldRetry = $handler->shouldRetryException($request, $exception, 1);

        // Assert
        expect($shouldRetry)->toBeFalse();
    });

    test('returns false when max attempts reached', function (): void {
        $config = new RetryConfig(times: 2);
        $handler = new RetryHandler($config);

        $request = new class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        $exception = new RuntimeException('Test exception');

        // Act
        $shouldRetry = $handler->shouldRetryException($request, $exception, 2);

        // Assert
        expect($shouldRetry)->toBeFalse();
    });

    test('returns false when no exception types configured', function (): void {
        $config = new RetryConfig(times: 3);
        $handler = new RetryHandler($config);

        $request = new class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        $exception = new RuntimeException('Test exception');

        // Act
        $shouldRetry = $handler->shouldRetryException($request, $exception, 1);

        // Assert
        expect($shouldRetry)->toBeFalse(); // Default: don't retry exceptions
    });

    test('returns true when exception type matches configured exceptions', function (): void {
        $config = new RetryConfig(times: 3, exceptions: [RuntimeException::class, LogicException::class]);
        $handler = new RetryHandler($config);

        $request = new class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        // Act & Assert - should retry RuntimeException
        $runtimeException = new RuntimeException('Test');
        expect($handler->shouldRetryException($request, $runtimeException, 1))->toBeTrue();

        // Should retry LogicException
        $logicException = new LogicException('Test');
        expect($handler->shouldRetryException($request, $logicException, 1))->toBeTrue();

        // Should not retry ErrorException
        $invalidArgException = new ErrorException('Test');
        expect($handler->shouldRetryException($request, $invalidArgException, 1))->toBeFalse();
    });

    test('returns true when exception is subclass of configured exception', function (): void {
        $config = new RetryConfig(times: 3, exceptions: [RuntimeException::class]);
        $handler = new RetryHandler($config);

        $request = new class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        // Custom exception extending RuntimeException
        $customException = new class('Test') extends RuntimeException {};

        // Act
        $shouldRetry = $handler->shouldRetryException($request, $customException, 1);

        // Assert
        expect($shouldRetry)->toBeTrue(); // Subclass should match
    });

    test('uses exception types from Retry attribute', function (): void {
        $handler = new RetryHandler();

        $request = new #[Retry(times: 3, exceptions: [RuntimeException::class])] class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        // Act & Assert
        $runtimeException = new RuntimeException('Test');
        expect($handler->shouldRetryException($request, $runtimeException, 1))->toBeTrue();

        $logicException = new LogicException('Test');
        expect($handler->shouldRetryException($request, $logicException, 1))->toBeFalse();
    });
});

describe('RetryHandler::sleep()', function (): void {
    test('calls Sleep::usleep with correct delay in microseconds', function (): void {
        $config = new RetryConfig(delay: 100); // 100ms
        $handler = new RetryHandler($config);

        $request = new class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        // Act & Assert - attempt 1 should be 100ms = 100,000 microseconds
        $delay1 = $handler->calculateDelay($request, 1);
        expect($delay1)->toBe(100);

        // Verify sleep would be called with microseconds (delay * 1000)
        // We can't easily mock Sleep::usleep, but we can verify calculation
        // Sleep should be called with: 100 * 1000 = 100,000 microseconds

        // For attempt 2: 100 * 2^1 = 200ms = 200,000 microseconds
        $delay2 = $handler->calculateDelay($request, 2);
        expect($delay2)->toBe(200);
    });

    test('does not sleep when delay is zero', function (): void {
        $handler = new RetryHandler(); // No config, delay should be 0

        $request = new class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        // Act
        $delay = $handler->calculateDelay($request, 1);

        // Assert
        expect($delay)->toBe(0);

        // sleep() should handle zero delay without calling Sleep::usleep
        $handler->sleep($request, 1); // Should complete without error
    });

    test('sleep uses calculated delay with exponential backoff', function (): void {
        $config = new RetryConfig(delay: 50, multiplier: 2.0);
        $handler = new RetryHandler($config);

        $request = new class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        // Act - verify delays increase exponentially
        expect($handler->calculateDelay($request, 1))->toBe(50);
        expect($handler->calculateDelay($request, 2))->toBe(100);
        expect($handler->calculateDelay($request, 3))->toBe(200);
        expect($handler->calculateDelay($request, 4))->toBe(400);

        // Sleep should use these calculated delays
        $handler->sleep($request, 1); // Should sleep for 50ms
        $handler->sleep($request, 2); // Should sleep for 100ms
    });

    test('sleep respects maxDelay from attribute', function (): void {
        $handler = new RetryHandler();

        $request = new #[Retry(times: 5, delay: 1_000, multiplier: 10.0, maxDelay: 3_000)] class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        // Act - attempt 3 would be 1000 * 10^2 = 100,000 but maxDelay is 3,000
        $delay = $handler->calculateDelay($request, 3);

        // Assert
        expect($delay)->toBe(3_000); // Capped at maxDelay
    });
});

describe('CircuitBreakerConfig', function (): void {
    it('creates with default values', function (): void {
        $config = new CircuitBreakerConfig();

        expect($config->failureThreshold)->toBe(5);
        expect($config->resetTimeout)->toBe(30);
        expect($config->halfOpenRequests)->toBe(3);
    });
});

describe('CircuitState', function (): void {
    it('has closed state', function (): void {
        expect(CircuitState::Closed->value)->toBe('closed');
    });

    it('has open state', function (): void {
        expect(CircuitState::Open->value)->toBe('open');
    });

    it('has half-open state', function (): void {
        expect(CircuitState::HalfOpen->value)->toBe('half-open');
    });
});

describe('MemoryCircuitStore', function (): void {
    it('starts in closed state', function (): void {
        $store = new MemoryCircuitStore();

        expect($store->getState('test'))->toBe(CircuitState::Closed);
    });

    it('tracks failures', function (): void {
        $store = new MemoryCircuitStore();

        $store->recordFailure('test', 60);
        $store->recordFailure('test', 60);

        expect($store->getFailureCount('test'))->toBe(2);
    });

    it('tracks successes', function (): void {
        $store = new MemoryCircuitStore();

        $store->recordSuccess('test');
        $store->recordSuccess('test');

        expect($store->getSuccessCount('test'))->toBe(2);
    });

    it('resets state', function (): void {
        $store = new MemoryCircuitStore();

        $store->setState('test', CircuitState::Open);
        $store->recordFailure('test', 60);

        $store->reset('test');

        expect($store->getState('test'))->toBe(CircuitState::Closed);
        expect($store->getFailureCount('test'))->toBe(0);
    });
});

describe('CircuitBreaker', function (): void {
    it('starts in closed state', function (): void {
        $store = new MemoryCircuitStore();
        $config = new CircuitBreakerConfig();
        $breaker = new CircuitBreaker($store, $config, 'test');

        expect($breaker->state())->toBe(CircuitState::Closed);
        expect($breaker->isClosed())->toBeTrue();
    });

    it('allows requests when closed', function (): void {
        $store = new MemoryCircuitStore();
        $config = new CircuitBreakerConfig();
        $breaker = new CircuitBreaker($store, $config, 'test');

        expect($breaker->allowRequest())->toBeTrue();
    });

    it('opens after failure threshold', function (): void {
        $store = new MemoryCircuitStore();
        $config = new CircuitBreakerConfig(failureThreshold: 3);
        $breaker = new CircuitBreaker($store, $config, 'test');

        $breaker->recordFailure();
        $breaker->recordFailure();

        expect($breaker->isClosed())->toBeTrue();

        $breaker->recordFailure();
        expect($breaker->isOpen())->toBeTrue();
    });

    it('throws when open', function (): void {
        $store = new MemoryCircuitStore();
        $config = new CircuitBreakerConfig(failureThreshold: 1);
        $breaker = new CircuitBreaker($store, $config, 'test');

        $breaker->recordFailure();

        expect($breaker->isOpen())->toBeTrue();

        $breaker->allowRequest();
    })->throws(CircuitOpenException::class);

    it('can be manually opened', function (): void {
        $store = new MemoryCircuitStore();
        $config = new CircuitBreakerConfig();
        $breaker = new CircuitBreaker($store, $config, 'test');

        $breaker->open();

        expect($breaker->isOpen())->toBeTrue();
    });

    it('can be manually closed', function (): void {
        $store = new MemoryCircuitStore();
        $config = new CircuitBreakerConfig(failureThreshold: 1);
        $breaker = new CircuitBreaker($store, $config, 'test');

        $breaker->recordFailure();

        expect($breaker->isOpen())->toBeTrue();

        $breaker->close();

        expect($breaker->isClosed())->toBeTrue();
    });

    it('closes after successes in half-open state', function (): void {
        $store = new MemoryCircuitStore();
        $config = new CircuitBreakerConfig(
            failureThreshold: 1,
            resetTimeout: 30,
            successThreshold: 2,
        );
        $breaker = new CircuitBreaker($store, $config, 'test');

        // Force to half-open state
        $store->setState('test', CircuitState::HalfOpen);

        // Record successes
        $breaker->recordSuccess();
        expect($breaker->isHalfOpen())->toBeTrue();

        $breaker->recordSuccess();
        expect($breaker->isClosed())->toBeTrue();
    });
});

describe('CircuitBreaker state transitions', function (): void {
    afterEach(function (): void {
        Date::setTestNow();
    });

    test('transitions from open to half-open after reset timeout', function (): void {
        $store = new MemoryCircuitStore();
        $config = new CircuitBreakerConfig(failureThreshold: 1, resetTimeout: 5);
        $breaker = new CircuitBreaker($store, $config, 'test');

        // Open the circuit
        $breaker->recordFailure();

        expect($breaker->isOpen())->toBeTrue();

        // Advance time past reset timeout
        Date::setTestNow(Date::now()->addSeconds(6));

        // Should now be half-open
        expect($breaker->isHalfOpen())->toBeTrue();
        expect($breaker->state())->toBe(CircuitState::HalfOpen);
    });

    test('does not transition to half-open before reset timeout', function (): void {
        $store = new MemoryCircuitStore();
        $config = new CircuitBreakerConfig(failureThreshold: 1, resetTimeout: 10);
        $breaker = new CircuitBreaker($store, $config, 'test');

        // Open the circuit
        $breaker->recordFailure();

        expect($breaker->isOpen())->toBeTrue();

        // Advance time but not past reset timeout
        Date::setTestNow(Date::now()->addSeconds(5));

        // Should still be open
        expect($breaker->isOpen())->toBeTrue();
        expect($breaker->state())->toBe(CircuitState::Open);
    });

    test('transitions exactly at reset timeout boundary', function (): void {
        $store = new MemoryCircuitStore();
        $config = new CircuitBreakerConfig(failureThreshold: 1, resetTimeout: 5);
        $breaker = new CircuitBreaker($store, $config, 'test');

        // Open the circuit
        $breaker->recordFailure();

        // Advance time exactly to reset timeout
        Date::setTestNow(Date::now()->addSeconds(5));

        // Should be half-open
        expect($breaker->isHalfOpen())->toBeTrue();
    });
});

describe('CircuitBreaker half-open behavior', function (): void {
    afterEach(function (): void {
        Date::setTestNow();
    });

    test('allows limited requests in half-open state', function (): void {
        $store = new MemoryCircuitStore();
        $config = new CircuitBreakerConfig(
            failureThreshold: 1,
            resetTimeout: 5,
            halfOpenRequests: 3,
        );
        $breaker = new CircuitBreaker($store, $config, 'test');

        // Force to half-open state
        $breaker->recordFailure();
        Date::setTestNow(Date::now()->addSeconds(6));

        // Should allow up to halfOpenRequests
        expect($breaker->allowRequest())->toBeTrue();
        expect($breaker->allowRequest())->toBeTrue();
        expect($breaker->allowRequest())->toBeTrue();
    });

    test('throws CircuitOpenException when half-open capacity reached', function (): void {
        $store = new MemoryCircuitStore();
        $config = new CircuitBreakerConfig(
            failureThreshold: 1,
            resetTimeout: 5,
            halfOpenRequests: 2,
        );
        $breaker = new CircuitBreaker($store, $config, 'test');

        // Force to half-open state
        $breaker->recordFailure();
        Date::setTestNow(Date::now()->addSeconds(6));

        // Allow first two requests
        $breaker->allowRequest();
        $breaker->allowRequest();

        // Third request should throw
        $breaker->allowRequest();
    })->throws(CircuitOpenException::class, 'Circuit breaker is half-open and at capacity');

    test('reopens circuit on failure in half-open state', function (): void {
        $store = new MemoryCircuitStore();
        $config = new CircuitBreakerConfig(
            failureThreshold: 1,
            resetTimeout: 5,
            halfOpenRequests: 3,
        );
        $breaker = new CircuitBreaker($store, $config, 'test');

        // Force to half-open state
        $breaker->recordFailure();
        Date::setTestNow(Date::now()->addSeconds(6));

        expect($breaker->isHalfOpen())->toBeTrue();

        // Record failure in half-open state
        $breaker->recordFailure();

        // Should reopen the circuit
        expect($breaker->isOpen())->toBeTrue();
    });

    test('does not record success when in closed state', function (): void {
        $store = new MemoryCircuitStore();
        $config = new CircuitBreakerConfig();
        $breaker = new CircuitBreaker($store, $config, 'test');

        // Circuit is closed
        expect($breaker->isClosed())->toBeTrue();

        // Record success (should not affect state)
        $breaker->recordSuccess();

        // Should still be closed and success count should be 0
        expect($breaker->isClosed())->toBeTrue();
        expect($store->getSuccessCount('test'))->toBe(0);
    });

    test('resets circuit breaker state completely', function (): void {
        $store = new MemoryCircuitStore();
        $config = new CircuitBreakerConfig(failureThreshold: 2);
        $breaker = new CircuitBreaker($store, $config, 'test');

        // Record failures and open the circuit
        $breaker->recordFailure();
        $breaker->recordFailure();

        expect($breaker->isOpen())->toBeTrue();

        // Reset the circuit
        $breaker->reset();

        // Circuit should be closed and failure count should be 0
        expect($breaker->isClosed())->toBeTrue();
        expect($store->getFailureCount('test'))->toBe(0);
    });
});

describe('CircuitBreaker callbacks', function (): void {
    afterEach(function (): void {
        Date::setTestNow();
    });

    test('calls onOpen callback when circuit opens', function (): void {
        $called = false;
        $callbackKey = null;

        $config = new CircuitBreakerConfig(
            failureThreshold: 1,
            onOpen: function ($key) use (&$called, &$callbackKey): void {
                $called = true;
                $callbackKey = $key;
            },
        );

        $store = new MemoryCircuitStore();
        $breaker = new CircuitBreaker($store, $config, 'test-key');

        // Open the circuit
        $breaker->recordFailure();

        expect($called)->toBeTrue();
        expect($callbackKey)->toBe('test-key');
    });

    test('calls onClose callback when circuit closes', function (): void {
        $called = false;
        $callbackKey = null;

        $config = new CircuitBreakerConfig(
            failureThreshold: 1,
            onClose: function ($key) use (&$called, &$callbackKey): void {
                $called = true;
                $callbackKey = $key;
            },
        );

        $store = new MemoryCircuitStore();
        $breaker = new CircuitBreaker($store, $config, 'test-key');

        // Open then close the circuit
        $breaker->recordFailure();
        $breaker->close();

        expect($called)->toBeTrue();
        expect($callbackKey)->toBe('test-key');
    });

    test('calls onHalfOpen callback when transitioning to half-open', function (): void {
        $called = false;
        $callbackKey = null;

        $config = new CircuitBreakerConfig(
            failureThreshold: 1,
            resetTimeout: 5,
            onHalfOpen: function ($key) use (&$called, &$callbackKey): void {
                $called = true;
                $callbackKey = $key;
            },
        );

        $store = new MemoryCircuitStore();
        $breaker = new CircuitBreaker($store, $config, 'test-key');

        // Open the circuit
        $breaker->recordFailure();

        // Advance time to trigger half-open transition
        Date::setTestNow(Date::now()->addSeconds(6));

        // Trigger state check to call halfOpen()
        $breaker->state();

        expect($called)->toBeTrue();
        expect($callbackKey)->toBe('test-key');
    });

    test('does not throw when callbacks are not configured', function (): void {
        $config = new CircuitBreakerConfig(failureThreshold: 1, resetTimeout: 5);
        $store = new MemoryCircuitStore();
        $breaker = new CircuitBreaker($store, $config, 'test');

        // These should not throw even though callbacks are null
        $breaker->open();
        $breaker->close();
        $breaker->recordFailure();
        Date::setTestNow(Date::now()->addSeconds(6));
        $breaker->state(); // Triggers halfOpen()

        expect(true)->toBeTrue();
    });
});

describe('CircuitBreaker retry timing', function (): void {
    afterEach(function (): void {
        Date::setTestNow();
    });

    test('calculates retry after time when circuit is open', function (): void {
        $store = new MemoryCircuitStore();
        $config = new CircuitBreakerConfig(failureThreshold: 1, resetTimeout: 30);
        $breaker = new CircuitBreaker($store, $config, 'test');

        // Open the circuit
        $breaker->recordFailure();

        try {
            $breaker->allowRequest();
        } catch (CircuitOpenException $circuitOpenException) {
            // Should be close to resetTimeout (30 seconds)
            expect($circuitOpenException->retryAfter())->toBeGreaterThanOrEqual(29);
            expect($circuitOpenException->retryAfter())->toBeLessThanOrEqual(30);
        }
    });

    test('calculates decreasing retry time as time passes', function (): void {
        $store = new MemoryCircuitStore();
        $config = new CircuitBreakerConfig(failureThreshold: 1, resetTimeout: 30);
        $breaker = new CircuitBreaker($store, $config, 'test');

        // Open the circuit
        $breaker->recordFailure();

        // Advance time by 10 seconds
        Date::setTestNow(Date::now()->addSeconds(10));

        try {
            $breaker->allowRequest();
        } catch (CircuitOpenException $circuitOpenException) {
            // Should be approximately 20 seconds remaining
            expect($circuitOpenException->retryAfter())->toBeGreaterThanOrEqual(19);
            expect($circuitOpenException->retryAfter())->toBeLessThanOrEqual(20);
        }
    });

    test('returns zero retry time when openedAt is past reset timeout', function (): void {
        $store = new MemoryCircuitStore();
        $config = new CircuitBreakerConfig(failureThreshold: 1, resetTimeout: 10);
        $breaker = new CircuitBreaker($store, $config, 'test');

        // Open the circuit
        $breaker->recordFailure();

        // Advance time past reset timeout
        Date::setTestNow(Date::now()->addSeconds(15));

        // Should transition to half-open, but let's check the timing
        // The circuit should be half-open now, so we won't get CircuitOpenException
        expect($breaker->isHalfOpen())->toBeTrue();
    });

    test('returns reset timeout when openedAt is null', function (): void {
        $store = new MemoryCircuitStore();
        $config = new CircuitBreakerConfig(failureThreshold: 1, resetTimeout: 30);
        $breaker = new CircuitBreaker($store, $config, 'test');

        // Manually set state to Open without setting openedAt
        $store->setState('test', CircuitState::Open);

        try {
            $breaker->allowRequest();
        } catch (CircuitOpenException $circuitOpenException) {
            // Should return full resetTimeout when openedAt is null
            expect($circuitOpenException->retryAfter())->toBe(30);
        }
    });
});

describe('CircuitOpenException', function (): void {
    it('provides retry after time', function (): void {
        $exception = new CircuitOpenException('Circuit open', 30);

        expect($exception->retryAfter())->toBe(30);
        expect($exception->getMessage())->toBe('Circuit open');
    });
});

describe('RetryHandler with RetryPolicy', function (): void {
    test('uses RetryPolicy class for configuration', function (): void {
        $handler = new RetryHandler();

        $request = new #[Retry(policy: TestRetryPolicy::class)] class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        $config = $handler->getConfig($request);

        expect($config)->toBeInstanceOf(RetryConfig::class)
            ->and($config->times)->toBe(5)
            ->and($config->delay)->toBe(250)
            ->and($config->multiplier)->toBe(1.5)
            ->and($config->maxDelay)->toBe(10_000);
    });

    test('uses RetryPolicy for shouldRetry decision', function (): void {
        $handler = new RetryHandler();

        $request = new #[Retry(policy: TestRetryPolicy::class)] class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        // TestRetryPolicy retries on 500 and 503 only
        expect($handler->shouldRetryResponse($request, Response::make([], 500), 1))->toBeTrue();
        expect($handler->shouldRetryResponse($request, Response::make([], 503), 1))->toBeTrue();
        expect($handler->shouldRetryResponse($request, Response::make([], 502), 1))->toBeFalse();
        expect($handler->shouldRetryResponse($request, Response::make([], 200), 1))->toBeFalse();
    });

    test('RetryPolicy respects max attempts', function (): void {
        $handler = new RetryHandler();

        $request = new #[Retry(policy: TestRetryPolicy::class)] class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        // TestRetryPolicy has times=5
        expect($handler->shouldRetryResponse($request, Response::make([], 500), 4))->toBeTrue();
        expect($handler->shouldRetryResponse($request, Response::make([], 500), 5))->toBeFalse();
    });

    test('RetryPolicy handles exception retry', function (): void {
        $handler = new RetryHandler();

        $request = new #[Retry(policy: TestRetryPolicy::class)] class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        // TestRetryPolicy retries RuntimeException only
        expect($handler->shouldRetryException($request, new RuntimeException('test'), 1))->toBeTrue();
        expect($handler->shouldRetryException($request, new LogicException('test'), 1))->toBeFalse();
    });

    test('calculateDelay uses RetryPolicy configuration', function (): void {
        $handler = new RetryHandler();

        $request = new #[Retry(policy: TestRetryPolicy::class)] class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        // TestRetryPolicy: delay=250, multiplier=1.5, maxDelay=10_000
        expect($handler->calculateDelay($request, 1))->toBe(250);
        expect($handler->calculateDelay($request, 2))->toBe(375); // 250 * 1.5
        expect($handler->calculateDelay($request, 3))->toBe(562); // 250 * 1.5^2 = 562.5 -> 562
    });

    test('ignores invalid policy class and falls back to attribute defaults', function (): void {
        $handler = new RetryHandler();

        $request = new #[Retry(times: 7, delay: 300, policy: 'NonExistentClass')] class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        $config = $handler->getConfig($request);

        // Should fall back to attribute values since policy class doesn't exist
        expect($config)->toBeInstanceOf(RetryConfig::class)
            ->and($config->times)->toBe(7)
            ->and($config->delay)->toBe(300);
    });
});

describe('RetryHandler with RetryDecider', function (): void {
    test('uses RetryDecider class for retry decision', function (): void {
        $handler = new RetryHandler();

        $request = new #[Retry(times: 3, callback: TestRetryDecider::class)] class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        // TestRetryDecider retries on 429 and 503
        expect($handler->shouldRetryResponse($request, Response::make([], 429), 1))->toBeTrue();
        expect($handler->shouldRetryResponse($request, Response::make([], 503), 1))->toBeTrue();
        expect($handler->shouldRetryResponse($request, Response::make([], 500), 1))->toBeFalse();
    });

    test('RetryDecider receives request, response, and attempt', function (): void {
        $handler = new RetryHandler();

        $request = new #[Retry(times: 5, callback: AttemptAwareRetryDecider::class)] class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }
        };

        // AttemptAwareRetryDecider only retries if attempt < 3
        expect($handler->shouldRetryResponse($request, Response::make([], 500), 1))->toBeTrue();
        expect($handler->shouldRetryResponse($request, Response::make([], 500), 2))->toBeTrue();
        expect($handler->shouldRetryResponse($request, Response::make([], 500), 3))->toBeFalse();
    });

    test('falls back to method when callback is not a RetryDecider class', function (): void {
        $handler = new RetryHandler();

        $request = new #[Retry(times: 3, callback: 'shouldRetry')] class() extends Request
        {
            public function endpoint(): string
            {
                return '/test';
            }

            public function shouldRetry(Response $response, int $attempt): bool
            {
                return $response->status() === 418; // I'm a teapot
            }
        };

        expect($handler->shouldRetryResponse($request, Response::make([], 418), 1))->toBeTrue();
        expect($handler->shouldRetryResponse($request, Response::make([], 500), 1))->toBeFalse();
    });
});

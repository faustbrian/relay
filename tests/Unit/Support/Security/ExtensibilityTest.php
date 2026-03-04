<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Relay\Core\Request;
use Cline\Relay\Support\Attributes\Methods\Post;
use Cline\Relay\Support\Security\IdempotencyManager;
use Cline\Relay\Support\Security\RequestSigner;
use Cline\Relay\Testing\MockResponse;
use Psr\SimpleCache\CacheInterface;

function createExtensibilityRequest(string $endpoint = '/users'): Request
{
    return new #[Post()] class($endpoint) extends Request
    {
        public function __construct(
            private readonly string $ep,
        ) {}

        public function endpoint(): string
        {
            return $this->ep;
        }

        public function body(): array
        {
            return ['name' => 'John Doe'];
        }
    };
}

describe('IdempotencyManager', function (): void {
    it('generates unique keys', function (): void {
        $manager = new IdempotencyManager();

        $key1 = $manager->generateKey();
        $key2 = $manager->generateKey();

        expect($key1)->toBeString();
        expect(mb_strlen($key1))->toBe(32); // 16 bytes = 32 hex chars
        expect($key1)->not->toBe($key2);
    });

    it('adds idempotency key to request', function (): void {
        $manager = new IdempotencyManager();
        $request = createExtensibilityRequest();

        $signed = $manager->addToRequest($request);

        expect($signed->idempotencyKey())->not->toBeNull();
        expect($signed->allHeaders())->toHaveKey('Idempotency-Key');
    });

    it('uses provided key', function (): void {
        $manager = new IdempotencyManager();
        $request = createExtensibilityRequest();

        $signed = $manager->addToRequest($request, 'my-custom-key');

        expect($signed->idempotencyKey())->toBe('my-custom-key');
        expect($signed->allHeaders()['Idempotency-Key'])->toBe('my-custom-key');
    });

    it('preserves existing idempotency key from request', function (): void {
        $manager = new IdempotencyManager();
        $request = createExtensibilityRequest()->withIdempotencyKey('existing-key');

        $signed = $manager->addToRequest($request);

        expect($signed->idempotencyKey())->toBe('existing-key');
    });

    it('uses custom header name', function (): void {
        $manager = new IdempotencyManager(
            cache: null,
            headerName: 'X-Request-Id',
        );
        $request = createExtensibilityRequest();

        $signed = $manager->addToRequest($request, 'test-key');

        expect($signed->allHeaders())->toHaveKey('X-Request-Id');
        expect($signed->allHeaders()['X-Request-Id'])->toBe('test-key');
    });

    it('caches and retrieves responses with cache', function (): void {
        $cache = new class() implements CacheInterface
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
                yield from [];
            }

            public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
            {
                return true;
            }

            public function deleteMultiple(iterable $keys): bool
            {
                return true;
            }

            public function has(string $key): bool
            {
                return array_key_exists($key, $this->data);
            }
        };

        $manager = new IdempotencyManager(cache: $cache);

        // Initially no cached response
        expect($manager->getCachedResponse('test-key'))->toBeNull();

        // Cache a response
        $response = MockResponse::json(['id' => 1, 'name' => 'John']);
        $manager->cacheResponse('test-key', $response);

        // Retrieve cached response
        $cached = $manager->getCachedResponse('test-key');
        expect($cached)->not->toBeNull();
        expect($cached->json('id'))->toBe(1);

        // Invalidate
        $manager->invalidate('test-key');
        expect($manager->getCachedResponse('test-key'))->toBeNull();
    });

    it('marks response as replay', function (): void {
        $manager = new IdempotencyManager();
        $response = MockResponse::json([]);

        expect($manager->isReplay($response))->toBeFalse();

        $replay = $manager->markAsReplay($response);

        expect($manager->isReplay($replay))->toBeTrue();
    });

    it('returns header name', function (): void {
        $manager = new IdempotencyManager();

        expect($manager->headerName())->toBe('Idempotency-Key');
    });

    it('returns custom header name', function (): void {
        $manager = new IdempotencyManager(headerName: 'X-Request-Id');

        expect($manager->headerName())->toBe('X-Request-Id');
    });

    it('returns null from getCachedResponse when no cache is set', function (): void {
        $manager = new IdempotencyManager();

        expect($manager->getCachedResponse('any-key'))->toBeNull();
    });

    it('does nothing in cacheResponse when no cache is set', function (): void {
        $manager = new IdempotencyManager();
        $response = MockResponse::json(['test' => 'data']);

        // Should not throw - just returns early
        $manager->cacheResponse('test-key', $response);

        // Verify nothing was cached (since there's no cache)
        expect($manager->getCachedResponse('test-key'))->toBeNull();
    });
});

describe('RequestSigner', function (): void {
    it('signs request with HMAC', function (): void {
        $signer = new RequestSigner(
            secret: 'my-secret-key',
            includeTimestamp: false,
        );

        $request = createExtensibilityRequest();
        $signed = $signer->sign($request);

        expect($signed->allHeaders())->toHaveKey('X-Signature');
        expect(mb_strlen($signed->allHeaders()['X-Signature']))->toBe(64); // sha256 = 64 hex chars
    });

    it('includes timestamp when enabled', function (): void {
        $signer = new RequestSigner(
            secret: 'my-secret-key',
            includeTimestamp: true,
        );

        $request = createExtensibilityRequest();
        $signed = $signer->sign($request);

        expect($signed->allHeaders())->toHaveKey('X-Signature');
        expect($signed->allHeaders())->toHaveKey('X-Timestamp');
        expect($signed->allHeaders()['X-Timestamp'])->toBeNumeric();
    });

    it('uses custom header names', function (): void {
        $signer = new RequestSigner(
            secret: 'my-secret-key',
            headerName: 'Authorization-Signature',
            timestampHeader: 'Request-Time',
        );

        $request = createExtensibilityRequest();
        $signed = $signer->sign($request);

        expect($signed->allHeaders())->toHaveKey('Authorization-Signature');
        expect($signed->allHeaders())->toHaveKey('Request-Time');
    });

    it('verifies valid signature', function (): void {
        $signer = new RequestSigner(
            secret: 'my-secret-key',
            includeTimestamp: false,
        );

        $request = createExtensibilityRequest();
        $signed = $signer->sign($request);

        $signature = $signed->allHeaders()['X-Signature'];

        expect($signer->verify($request, $signature))->toBeTrue();
    });

    it('rejects invalid signature', function (): void {
        $signer = new RequestSigner(
            secret: 'my-secret-key',
            includeTimestamp: false,
        );

        $request = createExtensibilityRequest();

        expect($signer->verify($request, 'invalid-signature'))->toBeFalse();
    });

    it('uses different algorithms', function (): void {
        $sha512Signer = new RequestSigner(
            secret: 'my-secret-key',
            algorithm: 'sha512',
            includeTimestamp: false,
        );

        $request = createExtensibilityRequest();
        $signed = $sha512Signer->sign($request);

        expect(mb_strlen($signed->allHeaders()['X-Signature']))->toBe(128); // sha512 = 128 hex chars
    });

    it('produces different signatures for different requests', function (): void {
        $signer = new RequestSigner(
            secret: 'my-secret-key',
            includeTimestamp: false,
        );

        $request1 = createExtensibilityRequest('/users');
        $request2 = createExtensibilityRequest('/posts');

        $signed1 = $signer->sign($request1);
        $signed2 = $signer->sign($request2);

        expect($signed1->allHeaders()['X-Signature'])
            ->not->toBe($signed2->allHeaders()['X-Signature']);
    });

    it('produces different signatures with different secrets', function (): void {
        $signer1 = new RequestSigner(secret: 'secret-1', includeTimestamp: false);
        $signer2 = new RequestSigner(secret: 'secret-2', includeTimestamp: false);

        $request = createExtensibilityRequest();

        $signed1 = $signer1->sign($request);
        $signed2 = $signer2->sign($request);

        expect($signed1->allHeaders()['X-Signature'])
            ->not->toBe($signed2->allHeaders()['X-Signature']);
    });
});

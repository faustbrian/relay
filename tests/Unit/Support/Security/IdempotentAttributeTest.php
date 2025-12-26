<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Relay\Core\Request;
use Cline\Relay\Support\Attributes\Idempotent;
use Cline\Relay\Support\Attributes\Methods\Post;

describe('Idempotent Attribute', function (): void {
    describe('isIdempotent()', function (): void {
        it('returns true for requests with #[Idempotent] attribute', function (): void {
            $request = new #[Post(), Idempotent()] class extends Request
            {
                public function endpoint(): string
                {
                    return '/payments';
                }
            };

            expect($request->isIdempotent())->toBeTrue();
        });

        it('returns false for requests without #[Idempotent] attribute', function (): void {
            $request = new #[Post()] class extends Request
            {
                public function endpoint(): string
                {
                    return '/payments';
                }
            };

            expect($request->isIdempotent())->toBeFalse();
        });

        it('returns false when enabled is false', function (): void {
            $request = new #[Post(), Idempotent(enabled: false)] class extends Request
            {
                public function endpoint(): string
                {
                    return '/payments';
                }
            };

            expect($request->isIdempotent())->toBeFalse();
        });
    });

    describe('idempotencyHeader()', function (): void {
        it('returns default header name', function (): void {
            $request = new #[Post(), Idempotent()] class extends Request
            {
                public function endpoint(): string
                {
                    return '/payments';
                }
            };

            expect($request->idempotencyHeader())->toBe('Idempotency-Key');
        });

        it('returns custom header name', function (): void {
            $request = new #[Post(), Idempotent(header: 'X-Request-Id')] class extends Request
            {
                public function endpoint(): string
                {
                    return '/payments';
                }
            };

            expect($request->idempotencyHeader())->toBe('X-Request-Id');
        });
    });

    describe('initialize()', function (): void {
        it('generates idempotency key on initialize', function (): void {
            $request = new #[Post(), Idempotent()] class extends Request
            {
                public function endpoint(): string
                {
                    return '/payments';
                }
            };

            expect($request->idempotencyKey())->toBeNull();

            $request->initialize();

            expect($request->idempotencyKey())->not->toBeNull();
            expect($request->idempotencyKey())->toHaveLength(32);
        });

        it('adds idempotency key to headers', function (): void {
            $request = new #[Post(), Idempotent()] class extends Request
            {
                public function endpoint(): string
                {
                    return '/payments';
                }
            };

            $request->initialize();

            $headers = $request->allHeaders();

            expect($headers)->toHaveKey('Idempotency-Key');
            expect($headers['Idempotency-Key'])->toBe($request->idempotencyKey());
        });

        it('uses custom header name', function (): void {
            $request = new #[Post(), Idempotent(header: 'X-Idempotency-Key')] class extends Request
            {
                public function endpoint(): string
                {
                    return '/payments';
                }
            };

            $request->initialize();

            $headers = $request->allHeaders();

            expect($headers)->toHaveKey('X-Idempotency-Key');
            expect($headers)->not->toHaveKey('Idempotency-Key');
        });

        it('uses custom key method when provided', function (): void {
            $request = new #[Post(), Idempotent(keyMethod: 'generateKey')] class extends Request
            {
                public function endpoint(): string
                {
                    return '/payments';
                }

                public function generateKey(): string
                {
                    return 'custom-key-123';
                }
            };

            $request->initialize();

            expect($request->idempotencyKey())->toBe('custom-key-123');
        });

        it('preserves manually set idempotency key', function (): void {
            $request = new #[Post(), Idempotent()] class extends Request
            {
                public function endpoint(): string
                {
                    return '/payments';
                }
            };

            $request = $request->withIdempotencyKey('my-custom-key');
            $request->initialize();

            expect($request->idempotencyKey())->toBe('my-custom-key');
            expect($request->allHeaders()['Idempotency-Key'])->toBe('my-custom-key');
        });

        it('does not generate key when not idempotent', function (): void {
            $request = new #[Post()] class extends Request
            {
                public function endpoint(): string
                {
                    return '/payments';
                }
            };

            $request->initialize();

            expect($request->idempotencyKey())->toBeNull();
            expect($request->allHeaders())->not->toHaveKey('Idempotency-Key');
        });

        it('does not generate key when disabled', function (): void {
            $request = new #[Post(), Idempotent(enabled: false)] class extends Request
            {
                public function endpoint(): string
                {
                    return '/payments';
                }
            };

            $request->initialize();

            expect($request->idempotencyKey())->toBeNull();
        });
    });
});

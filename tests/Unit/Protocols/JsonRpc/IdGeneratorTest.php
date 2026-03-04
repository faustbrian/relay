<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Relay\Protocols\JsonRpc\IncrementingIdGenerator;
use Cline\Relay\Protocols\JsonRpc\RandomIdGenerator;
use Cline\Relay\Protocols\JsonRpc\UlidIdGenerator;
use Cline\Relay\Protocols\JsonRpc\UuidIdGenerator;

describe('IdGenerator', function (): void {
    describe('UlidIdGenerator', function (): void {
        it('generates valid ULID strings', function (): void {
            $generator = new UlidIdGenerator();

            $id = $generator->generate();

            // ULID is 26 characters, base32 encoded
            expect(mb_strlen($id))->toBe(26);
            expect($id)->toMatch('/^[0-9A-Z]{26}$/');
        });

        it('generates unique IDs', function (): void {
            $generator = new UlidIdGenerator();

            $id1 = $generator->generate();
            $id2 = $generator->generate();

            expect($id1)->not->toBe($id2);
        });
    });

    describe('UuidIdGenerator', function (): void {
        it('generates valid UUID v4 strings', function (): void {
            $generator = new UuidIdGenerator();

            $id = $generator->generate();

            // UUID v4 format: xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
            expect($id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i');
        });

        it('generates unique IDs', function (): void {
            $generator = new UuidIdGenerator();

            $id1 = $generator->generate();
            $id2 = $generator->generate();

            expect($id1)->not->toBe($id2);
        });
    });

    describe('RandomIdGenerator', function (): void {
        it('generates strings of default length 32', function (): void {
            $generator = new RandomIdGenerator();

            $id = $generator->generate();

            expect(mb_strlen($id))->toBe(32);
        });

        it('generates strings of custom length', function (): void {
            $generator = new RandomIdGenerator(16);

            $id = $generator->generate();

            expect(mb_strlen($id))->toBe(16);
        });

        it('generates unique IDs', function (): void {
            $generator = new RandomIdGenerator();

            $id1 = $generator->generate();
            $id2 = $generator->generate();

            expect($id1)->not->toBe($id2);
        });
    });

    describe('IncrementingIdGenerator', function (): void {
        it('starts at 1 by default', function (): void {
            $generator = new IncrementingIdGenerator();

            expect($generator->generate())->toBe('1');
        });

        it('increments with each call', function (): void {
            $generator = new IncrementingIdGenerator();

            expect($generator->generate())->toBe('1');
            expect($generator->generate())->toBe('2');
            expect($generator->generate())->toBe('3');
        });

        it('starts at custom value', function (): void {
            $generator = new IncrementingIdGenerator(100);

            expect($generator->generate())->toBe('100');
            expect($generator->generate())->toBe('101');
        });

        it('can reset counter', function (): void {
            $generator = new IncrementingIdGenerator();

            $generator->generate();
            $generator->generate();
            $generator->reset();

            expect($generator->generate())->toBe('1');
        });

        it('can reset counter to custom value', function (): void {
            $generator = new IncrementingIdGenerator();

            $generator->generate();
            $generator->reset(50);

            expect($generator->generate())->toBe('50');
        });

        it('can get current counter without incrementing', function (): void {
            $generator = new IncrementingIdGenerator();

            expect($generator->current())->toBe(1);
            expect($generator->current())->toBe(1);

            $generator->generate();

            expect($generator->current())->toBe(2);
        });
    });
});

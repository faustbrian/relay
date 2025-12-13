<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Relay\Support\Serialization\ResponseSerializer;
use Cline\Relay\Testing\MockResponse;
use Illuminate\Support\Collection;
use Tests\Fixtures\UserDto;

describe('ResponseSerializer', function (): void {
    it('deserializes response to DTO', function (): void {
        $serializer = new ResponseSerializer();
        $response = MockResponse::json([
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $dto = $serializer->toDto($response, UserDto::class);

        expect($dto)->toBeInstanceOf(UserDto::class);
        expect($dto->id)->toBe(1);
        expect($dto->name)->toBe('John Doe');
        expect($dto->email)->toBe('john@example.com');
    });

    it('deserializes from nested key', function (): void {
        $serializer = new ResponseSerializer();
        $response = MockResponse::json([
            'data' => [
                'user' => [
                    'id' => 1,
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                ],
            ],
        ]);

        $dto = $serializer->toDtoFrom($response, 'data.user', UserDto::class);

        expect($dto)->toBeInstanceOf(UserDto::class);
        expect($dto->id)->toBe(1);
    });

    it('deserializes to DTO collection', function (): void {
        $serializer = new ResponseSerializer();
        $response = MockResponse::json([
            ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'],
            ['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com'],
        ]);

        $dtos = $serializer->toDtoCollection($response, UserDto::class);

        expect($dtos)->toHaveCount(2);
        expect($dtos[0])->toBeInstanceOf(UserDto::class);
        expect($dtos[0]->name)->toBe('John');
        expect($dtos[1]->name)->toBe('Jane');
    });

    it('deserializes nested collection', function (): void {
        $serializer = new ResponseSerializer();
        $response = MockResponse::json([
            'data' => [
                ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'],
                ['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com'],
            ],
        ]);

        $dtos = $serializer->toDtoCollection($response, UserDto::class, 'data');

        expect($dtos)->toHaveCount(2);
    });

    it('returns Laravel collection', function (): void {
        $serializer = new ResponseSerializer();
        $response = MockResponse::json([
            ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'],
            ['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com'],
        ]);

        $collection = $serializer->toCollection($response, UserDto::class);

        expect($collection)->toBeInstanceOf(Collection::class);
        expect($collection)->toHaveCount(2);
        expect($collection->first())->toBeInstanceOf(UserDto::class);
    });

    it('throws for non-DTO class', function (): void {
        $serializer = new ResponseSerializer();
        $response = MockResponse::json(['id' => 1]);

        $serializer->toDto($response, stdClass::class);
    })->throws(InvalidArgumentException::class, 'must implement DataTransferObject');
});

describe('DataTransferObject', function (): void {
    it('can be created from array', function (): void {
        $dto = UserDto::fromArray([
            'id' => 1,
            'name' => 'John',
            'email' => 'john@example.com',
        ]);

        expect($dto->id)->toBe(1);
        expect($dto->name)->toBe('John');
    });

    it('can be converted to array', function (): void {
        $dto = new UserDto(1, 'John', 'john@example.com');

        expect($dto->toArray())->toBe([
            'id' => 1,
            'name' => 'John',
            'email' => 'john@example.com',
        ]);
    });
});

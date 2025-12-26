<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Serialization;

use Cline\Relay\Core\Response;
use Cline\Relay\Support\Contracts\DataTransferObject;
use Cline\Relay\Support\Exceptions\InvalidDtoClassException;
use Illuminate\Support\Collection;

use function array_map;
use function collect;
use function is_subclass_of;
use function throw_unless;

/**
 * Serializes responses to DTOs or other formats.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ResponseSerializer
{
    /**
     * Deserialize response to a DTO.
     *
     * @template T of DataTransferObject
     *
     * @param  class-string<T> $dtoClass
     * @return T
     */
    public function toDto(Response $response, string $dtoClass): DataTransferObject
    {
        // @phpstan-ignore function.alreadyNarrowedType (Runtime validation ensures class string implements interface)
        throw_unless(is_subclass_of($dtoClass, DataTransferObject::class), InvalidDtoClassException::mustImplementInterface($dtoClass));

        /** @var array<string, mixed> */
        $data = $response->json();

        return $dtoClass::fromArray($data);
    }

    /**
     * Deserialize response data at a specific key to a DTO.
     *
     * @template T of DataTransferObject
     *
     * @param  class-string<T> $dtoClass
     * @return T
     */
    public function toDtoFrom(Response $response, string $key, string $dtoClass): DataTransferObject
    {
        // @phpstan-ignore function.alreadyNarrowedType (Runtime validation ensures class string implements interface)
        throw_unless(is_subclass_of($dtoClass, DataTransferObject::class), InvalidDtoClassException::mustImplementInterface($dtoClass));

        /** @var array<string, mixed> */
        $data = $response->json($key);

        return $dtoClass::fromArray($data);
    }

    /**
     * Deserialize response to a collection of DTOs.
     *
     * @template T of DataTransferObject
     *
     * @param  class-string<T> $dtoClass
     * @return array<T>
     */
    public function toDtoCollection(Response $response, string $dtoClass, ?string $key = null): array
    {
        // @phpstan-ignore function.alreadyNarrowedType (Runtime validation ensures class string implements interface)
        throw_unless(is_subclass_of($dtoClass, DataTransferObject::class), InvalidDtoClassException::mustImplementInterface($dtoClass));

        /** @var array<array<string, mixed>> */
        $items = $key !== null
            ? $response->json($key)
            : $response->json();

        return array_map(
            /** @param array<string, mixed> $item */
            fn (array $item): DataTransferObject => $dtoClass::fromArray($item),
            $items,
        );
    }

    /**
     * Deserialize response to a Laravel collection of DTOs.
     *
     * @template T of DataTransferObject
     *
     * @param  class-string<T>    $dtoClass
     * @return Collection<int, T>
     */
    public function toCollection(Response $response, string $dtoClass, ?string $key = null): Collection
    {
        return collect($this->toDtoCollection($response, $dtoClass, $key));
    }
}

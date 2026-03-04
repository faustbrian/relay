<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Core\JsonRpc;

use Cline\Relay\Protocols\JsonRpcRequest;
use Override;
use Spatie\LaravelData\Data;

use function is_array;

/**
 * Base request for internal microservices using JSON-RPC.
 *
 * Automatically handles the `$data` property if present:
 * - Wraps Data objects in `['data' => $data->toArray()]`
 * - Wraps arrays in `['data' => $data]`
 * - Returns empty array if data is empty/null
 *
 * @author Brian Faust <brian@cline.sh>
 */
abstract class JsonRpcMicroserviceRequest extends JsonRpcRequest
{
    protected ?string $methodPrefix = 'app';

    /**
     * @return array<string, mixed>
     */
    #[Override()]
    public function params(): array
    {
        /** @var null|array<string, mixed>|Data $data */
        $data = $this->data ?? null;

        if ($data === null) {
            return [];
        }

        if ($data instanceof Data) {
            $array = $this->filterNulls($data->toArray());

            return $array === [] ? [] : ['data' => $array];
        }

        // Plain arrays are returned directly without wrapping
        return $data;
    }

    /**
     * Recursively filter null values from array.
     *
     * @param  array<mixed>         $array
     * @return array<string, mixed>
     */
    private function filterNulls(array $array): array
    {
        $result = [];

        /** @var int|string $key */
        foreach ($array as $key => $value) {
            if ($value === null) {
                continue;
            }

            /** @var string $key */
            $result[$key] = is_array($value) ? $this->filterNulls($value) : $value;
        }

        return $result;
    }
}

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Core\JsonRpc;

use Cline\Relay\Protocols\JsonRpcRequest;
use Cline\Struct\AbstractData;
use Override;

/**
 * Base request for internal microservices using JSON-RPC.
 *
 * Automatically handles the `$data` property if present:
 * - Wraps data objects in `['data' => $data->toArray()]`
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
        /** @var null|AbstractData|array<string, mixed> $data */
        $data = $this->data ?? null;

        if ($data === null) {
            return [];
        }

        if ($data instanceof AbstractData) {
            return ['data' => $data->toArray()];
        }

        return ['data' => $data];
    }
}

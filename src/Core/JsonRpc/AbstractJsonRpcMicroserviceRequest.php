<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Core\JsonRpc;

use Cline\Relay\Protocols\AbstractJsonRpcRequest;
use Cline\Struct\AbstractData;
use Override;

use function assert;
use function is_array;
use function method_exists;
use function property_exists;

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
abstract class AbstractJsonRpcMicroserviceRequest extends AbstractJsonRpcRequest
{
    protected ?string $methodPrefix = 'app';

    /**
     * @return array<string, mixed>
     */
    #[Override()]
    public function params(): array
    {
        /** @var null|AbstractData|array<string, mixed> $data */
        $data = $this->resolveMicroserviceData();

        if ($data === null) {
            return [];
        }

        if ($data instanceof AbstractData) {
            return ['data' => $data->toArray()];
        }

        return ['data' => $data];
    }

    /**
     * @return null|AbstractData|array<mixed, mixed>
     */
    protected function resolveMicroserviceData(): AbstractData|array|null
    {
        $property = 'data';
        $method = 'resolveData';

        if (method_exists($this, $method)) {
            /** @var null|AbstractData|array<string, mixed> */
            return $this->{$method}();
        }

        if (!property_exists($this, $property)) {
            return null;
        }

        assert($this->{$property} === null || $this->{$property} instanceof AbstractData || is_array($this->{$property}));

        return $this->{$property};
    }
}

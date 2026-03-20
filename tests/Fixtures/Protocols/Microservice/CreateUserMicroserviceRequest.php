<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\Protocols\Microservice;

use Cline\Relay\Core\JsonRpc\AbstractJsonRpcMicroserviceRequest;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class CreateUserMicroserviceRequest extends AbstractJsonRpcMicroserviceRequest
{
    /**
     * @param null|array<string, mixed>|CreateUserData $data
     */
    public function __construct(
        private readonly array|CreateUserData|null $data = null,
    ) {}

    /**
     * @return null|array<string, mixed>|CreateUserData
     */
    protected function resolveData(): array|CreateUserData|null
    {
        return $this->data;
    }
}

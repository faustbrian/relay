<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\Protocols\Microservice;

use Cline\Relay\Core\JsonRpc\JsonRpcMicroserviceRequest;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class CreateUserMicroserviceRequest extends JsonRpcMicroserviceRequest
{
    /**
     * @param array<string, mixed>|CreateUserData|null $data
     */
    public function __construct(
        protected array|CreateUserData|null $data = null,
    ) {}
}

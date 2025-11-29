<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\Protocols;

use Cline\Relay\Protocols\JsonRpcRequest;
use Override;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class CustomEndpointJsonRpcRequest extends JsonRpcRequest
{
    #[Override()]
    public function endpoint(): string
    {
        return '/api/v2/jsonrpc';
    }
}

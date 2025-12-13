<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\Protocols;

use Cline\Relay\Protocols\JsonRpcRequest;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class ListOrdersJsonRpcRequest extends JsonRpcRequest
{
    protected ?string $methodPrefix = 'app';
}

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Protocols\JsonRpc;

use Illuminate\Support\Str;

/**
 * Generates JSON-RPC IDs using ULIDs.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class UlidIdGenerator implements IdGenerator
{
    public function generate(): string
    {
        return (string) Str::ulid();
    }
}

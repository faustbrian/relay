<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Protocols\JsonRpc;

/**
 * Contract for JSON-RPC request ID generators.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface IdGenerator
{
    /**
     * Generate a unique ID for a JSON-RPC request.
     */
    public function generate(): string;
}

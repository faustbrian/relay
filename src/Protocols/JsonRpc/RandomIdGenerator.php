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
 * Generates JSON-RPC IDs using random strings.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class RandomIdGenerator implements IdGenerator
{
    public function __construct(
        private int $length = 32,
    ) {}

    public function generate(): string
    {
        return Str::random($this->length);
    }
}

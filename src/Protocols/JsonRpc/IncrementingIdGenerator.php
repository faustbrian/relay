<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Protocols\JsonRpc;

/**
 * Generates JSON-RPC IDs using incrementing integers.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class IncrementingIdGenerator implements IdGenerator
{
    public function __construct(
        private int $counter = 1,
    ) {}

    public function generate(): string
    {
        return (string) $this->counter++;
    }

    /**
     * Reset the counter to a specific value.
     */
    public function reset(int $start = 1): void
    {
        $this->counter = $start;
    }

    /**
     * Get the current counter value without incrementing.
     */
    public function current(): int
    {
        return $this->counter;
    }
}

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Contracts;

/**
 * Interface for data transfer objects.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface DataTransferObject
{
    /**
     * Create from an array of data.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): static;

    /**
     * Convert to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Attributes\Resilience;

use Attribute;

/**
 * Configure request timeout.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Timeout
{
    /**
     * @param int      $seconds Total timeout in seconds
     * @param null|int $connect Connection timeout in seconds
     * @param null|int $read    Read timeout in seconds
     */
    public function __construct(
        public int $seconds = 30,
        public ?int $connect = null,
        public ?int $read = null,
    ) {}
}

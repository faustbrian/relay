<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Attributes\RateLimiting;

use Attribute;

/**
 * Configure concurrency limiting for a request.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class ConcurrencyLimit
{
    /**
     * @param int $limit Maximum concurrent requests
     */
    public function __construct(
        public int $limit,
    ) {}
}

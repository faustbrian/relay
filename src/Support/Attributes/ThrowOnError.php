<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Attributes;

use Attribute;

/**
 * Enable throwing exceptions on 4xx/5xx responses.
 *
 * Can be applied to individual requests or to connectors (for all requests).
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class ThrowOnError
{
    public function __construct(
        /**
         * Whether to throw on client errors (4xx).
         */
        public bool $clientErrors = true,
        /**
         * Whether to throw on server errors (5xx).
         */
        public bool $serverErrors = true,
    ) {}
}

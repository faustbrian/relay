<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Attributes\Network;

use Attribute;

/**
 * Force IP resolution to IPv4 or IPv6.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class ForceIpResolve
{
    public const string V4 = 'v4';

    public const string V6 = 'v6';

    public function __construct(
        public string $version = self::V4,
    ) {}
}

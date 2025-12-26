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
 * Configure SSL/TLS settings for a request or connector.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Ssl
{
    public function __construct(
        public bool $verify = true,
        public ?string $certPath = null,
        public ?string $keyPath = null,
        public ?string $keyPassword = null,
        public ?string $caBundlePath = null,
    ) {}
}

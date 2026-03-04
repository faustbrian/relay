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
 * Configure HTTP proxy for a request or connector.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Proxy
{
    /**
     * @param null|array<string> $noProxy Domains to bypass proxy
     */
    public function __construct(
        public ?string $http = null,
        public ?string $https = null,
        public ?array $noProxy = null,
    ) {}
}

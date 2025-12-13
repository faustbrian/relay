<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Attributes\Protocols;

use Attribute;
use Cline\Relay\Support\Contracts\Protocol;

/**
 * Mark a request as using SOAP protocol.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Soap implements Protocol
{
    public function __construct(
        public string $version = '1.1',
    ) {}

    public function protocol(): string
    {
        return 'soap';
    }

    public function defaultContentType(): string
    {
        return $this->version === '1.2'
            ? 'application/soap+xml'
            : 'text/xml';
    }
}

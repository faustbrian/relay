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
 * Mark a request as using GraphQL protocol.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class GraphQL implements Protocol
{
    public function protocol(): string
    {
        return 'graphql';
    }

    public function defaultContentType(): string
    {
        return 'application/json';
    }
}

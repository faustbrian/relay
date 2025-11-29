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
 * Mark a request as using JSON-RPC 2.0 protocol.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class JsonRpc implements Protocol
{
    public function __construct(
        public string $version = '2.0',
    ) {}

    public function protocol(): string
    {
        return 'jsonrpc';
    }

    public function defaultContentType(): string
    {
        return 'application/json';
    }
}

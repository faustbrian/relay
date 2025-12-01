<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Attributes;

use Attribute;
use Cline\Relay\Support\Contracts\IdempotencyKeyGenerator;

/**
 * Mark a request as idempotent.
 *
 * When applied to a request, an idempotency key will be automatically
 * generated and added to the request headers. This ensures that
 * retrying the same request will not cause duplicate operations.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Idempotent
{
    /**
     * @param string                                            $header    The header name for the idempotency key
     * @param null|class-string<IdempotencyKeyGenerator>|string $keyMethod Method name or IdempotencyKeyGenerator class for custom key generation
     * @param bool                                              $enabled   Whether idempotency is enabled (useful for disabling on subclass)
     */
    public function __construct(
        public string $header = 'Idempotency-Key',
        public ?string $keyMethod = null,
        public bool $enabled = true,
    ) {}
}

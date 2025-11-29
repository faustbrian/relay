<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Attributes\Caching;

use Attribute;
use Cline\Relay\Support\Contracts\CacheKeyResolver;

/**
 * Enable caching for a request.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Cache
{
    /**
     * @param int                                        $ttl         Time to live in seconds
     * @param null|class-string<CacheKeyResolver>|string $keyResolver Custom cache key template, method name, or CacheKeyResolver class
     * @param array<string>                              $tags        Cache tags for invalidation
     */
    public function __construct(
        public int $ttl = 300,
        public ?string $keyResolver = null,
        public array $tags = [],
    ) {}
}

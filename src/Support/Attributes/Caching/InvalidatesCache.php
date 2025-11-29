<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Attributes\Caching;

use Attribute;

/**
 * Invalidate cache entries after a request completes.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class InvalidatesCache
{
    /**
     * @param array<string>            $tags     Cache tags to invalidate
     * @param null|array<class-string> $requests Request classes to invalidate
     * @param null|array<string>       $keys     Specific cache keys to invalidate
     */
    public function __construct(
        public array $tags = [],
        public ?array $requests = null,
        public ?array $keys = null,
    ) {}
}

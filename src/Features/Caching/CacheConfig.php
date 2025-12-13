<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Features\Caching;

use Psr\SimpleCache\CacheInterface;

/**
 * Configuration for caching behavior.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class CacheConfig
{
    /**
     * @param CacheInterface $store            The cache store to use
     * @param string         $hashAlgorithm    Algorithm for hashing query/body (md5, sha1, xxh3, etc.)
     * @param null|int       $maxKeyLength     Maximum key length (null for unlimited)
     * @param bool           $includeHeaders   Whether to include headers in cache key
     * @param string         $prefix           Prefix for all cache keys
     * @param int            $defaultTtl       Default TTL in seconds
     * @param array<string>  $cacheableMethods HTTP methods that can be cached
     */
    public function __construct(
        public CacheInterface $store,
        public string $hashAlgorithm = 'md5',
        public ?int $maxKeyLength = null,
        public bool $includeHeaders = false,
        public string $prefix = '',
        public int $defaultTtl = 300,
        public array $cacheableMethods = ['GET', 'HEAD'],
    ) {}
}

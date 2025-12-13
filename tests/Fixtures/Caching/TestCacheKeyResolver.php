<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\Caching;

use Cline\Relay\Core\Request;
use Cline\Relay\Support\Contracts\CacheKeyResolver;

/**
 * Test cache key resolver for unit tests.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class TestCacheKeyResolver implements CacheKeyResolver
{
    public function resolve(Request $request): string
    {
        return 'test-resolver:'.$request->endpoint();
    }
}

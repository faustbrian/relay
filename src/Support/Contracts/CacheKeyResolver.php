<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Contracts;

use Cline\Relay\Core\Request;

/**
 * Interface for custom cache key resolution.
 *
 * Implement this interface to create reusable cache key resolvers
 * that can be shared across requests.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface CacheKeyResolver
{
    /**
     * Generate a cache key for the given request.
     */
    public function resolve(Request $request): string;
}

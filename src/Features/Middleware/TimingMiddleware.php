<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Features\Middleware;

use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;
use Cline\Relay\Support\Contracts\Middleware;
use Closure;

use function microtime;

/**
 * Middleware that records request timing.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class TimingMiddleware implements Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        $response = $next($request);

        $duration = (microtime(true) - $startTime) * 1_000;

        return $response->setDuration($duration);
    }
}

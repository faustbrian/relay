<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Features\Middleware;

use Cline\Relay\Core\AbstractRequest;
use Cline\Relay\Core\Response;
use Cline\Relay\Support\Contracts\MiddlewareInterface;
use Closure;

use function microtime;

/**
 * MiddlewareInterface that records request timing.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class TimingMiddleware implements MiddlewareInterface
{
    public function handle(AbstractRequest $request, Closure $next): Response
    {
        $startTime = microtime(true);

        $response = $next($request);

        $duration = (microtime(true) - $startTime) * 1_000;

        return $response->setDuration($duration);
    }
}

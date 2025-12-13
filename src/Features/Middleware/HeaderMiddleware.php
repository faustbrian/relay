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

/**
 * Middleware that adds headers to all requests.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class HeaderMiddleware implements Middleware
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private array $headers,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request->withHeaders($this->headers));
    }
}

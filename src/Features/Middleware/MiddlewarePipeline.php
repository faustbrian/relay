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

use function array_reduce;
use function array_reverse;
use function array_unshift;
use function assert;
use function count;

/**
 * Processes requests through a chain of middleware.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class MiddlewarePipeline
{
    /** @var array<Closure|Middleware> */
    private array $middleware = [];

    /**
     * Add middleware to the pipeline.
     *
     * @param Closure(Request, Closure): Response|Middleware $middleware
     */
    public function push(Middleware|Closure $middleware): self
    {
        $this->middleware[] = $middleware;

        return $this;
    }

    /**
     * Add middleware to the beginning of the pipeline.
     *
     * @param Closure(Request, Closure): Response|Middleware $middleware
     */
    public function prepend(Middleware|Closure $middleware): self
    {
        array_unshift($this->middleware, $middleware);

        return $this;
    }

    /**
     * Process the request through all middleware.
     *
     * @param Closure(Request): Response $core
     */
    public function process(Request $request, Closure $core): Response
    {
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            fn (Closure $next, Middleware|Closure $middleware): Closure => function (Request $request) use ($middleware, $next): Response {
                $response = $middleware instanceof Middleware
                    ? $middleware->handle($request, $next)
                    : $middleware($request, $next);

                assert($response instanceof Response);

                return $response;
            },
            $core,
        );

        return $pipeline($request);
    }

    /**
     * Get the count of middleware.
     */
    public function count(): int
    {
        return count($this->middleware);
    }

    /**
     * Check if pipeline has any middleware.
     */
    public function hasMiddleware(): bool
    {
        return $this->middleware !== [];
    }

    /**
     * Clear all middleware.
     */
    public function clear(): self
    {
        $this->middleware = [];

        return $this;
    }
}

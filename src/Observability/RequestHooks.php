<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Observability;

use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;
use Closure;
use Throwable;

/**
 * Manages request lifecycle hooks.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class RequestHooks
{
    /** @var array<Closure(Request): Request> */
    private array $beforeRequest = [];

    /** @var array<Closure(Response, Request): Response> */
    private array $afterResponse = [];

    /** @var array<Closure(Throwable, Request): void> */
    private array $onError = [];

    /**
     * Register a before-request hook.
     *
     * @param Closure(Request): Request $callback
     */
    public function beforeRequest(Closure $callback): self
    {
        $this->beforeRequest[] = $callback;

        return $this;
    }

    /**
     * Register an after-response hook.
     *
     * @param Closure(Response, Request): Response $callback
     */
    public function afterResponse(Closure $callback): self
    {
        $this->afterResponse[] = $callback;

        return $this;
    }

    /**
     * Register an error hook.
     *
     * @param Closure(Throwable, Request): void $callback
     */
    public function onError(Closure $callback): self
    {
        $this->onError[] = $callback;

        return $this;
    }

    /**
     * Execute before-request hooks.
     */
    public function executeBeforeRequest(Request $request): Request
    {
        foreach ($this->beforeRequest as $hook) {
            $request = $hook($request);
        }

        return $request;
    }

    /**
     * Execute after-response hooks.
     */
    public function executeAfterResponse(Response $response, Request $request): Response
    {
        foreach ($this->afterResponse as $hook) {
            $response = $hook($response, $request);
        }

        return $response;
    }

    /**
     * Execute error hooks.
     */
    public function executeOnError(Throwable $error, Request $request): void
    {
        foreach ($this->onError as $hook) {
            $hook($error, $request);
        }
    }

    /**
     * Check if any hooks are registered.
     */
    public function hasHooks(): bool
    {
        return $this->beforeRequest !== []
            || $this->afterResponse !== []
            || $this->onError !== [];
    }
}

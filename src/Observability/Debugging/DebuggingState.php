<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Observability\Debugging;

use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;
use Closure;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\VarDumper\VarDumper;

use function class_exists;

/**
 * Tracks debugging state and handles output.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class DebuggingState
{
    private bool $debugRequest = false;

    private bool $debugResponse = false;

    private bool $die = false;

    /** @var null|Closure(Request, ?RequestInterface): void */
    private ?Closure $requestHandler = null;

    /** @var null|Closure(Response, ResponseInterface): void */
    private ?Closure $responseHandler = null;

    /**
     * Enable request debugging.
     */
    public function enableRequestDebugging(): self
    {
        $this->debugRequest = true;

        return $this;
    }

    /**
     * Enable response debugging.
     */
    public function enableResponseDebugging(): self
    {
        $this->debugResponse = true;

        return $this;
    }

    /**
     * Set whether to die after debugging.
     */
    public function setDie(bool $die): self
    {
        $this->die = $die;

        return $this;
    }

    /**
     * Set custom request handler.
     *
     * @param null|Closure(Request, ?RequestInterface): void $handler
     */
    public function setRequestHandler(?Closure $handler): self
    {
        $this->requestHandler = $handler;

        return $this;
    }

    /**
     * Set custom response handler.
     *
     * @param null|Closure(Response, ResponseInterface): void $handler
     */
    public function setResponseHandler(?Closure $handler): self
    {
        $this->responseHandler = $handler;

        return $this;
    }

    /**
     * Check if request debugging is enabled.
     */
    public function shouldDebugRequest(): bool
    {
        return $this->debugRequest;
    }

    /**
     * Check if response debugging is enabled.
     */
    public function shouldDebugResponse(): bool
    {
        return $this->debugResponse;
    }

    /**
     * Check if any debugging is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->debugRequest || $this->debugResponse;
    }

    /**
     * Check if should die after debugging.
     */
    public function shouldDie(): bool
    {
        return $this->die;
    }

    /**
     * Output request debug info.
     *
     * @param Request               $request    The Relay request
     * @param string                $baseUrl    The base URL
     * @param null|RequestInterface $psrRequest The PSR-7 request (optional for backwards compatibility)
     */
    public function outputRequest(Request $request, string $baseUrl, ?RequestInterface $psrRequest = null): void
    {
        if (!$this->debugRequest) {
            return;
        }

        if ($this->requestHandler instanceof Closure) {
            ($this->requestHandler)($request, $psrRequest);

            if ($this->die) {
                exit(1); // @codeCoverageIgnore
            }

            return;
        }

        $debugger = new Debugger();
        $output = $debugger->formatRequest($request, $baseUrl);

        $this->output($output);

        if ($this->die && !$this->debugResponse) {
            exit(1); // @codeCoverageIgnore
        }
    }

    /**
     * Output response debug info.
     *
     * @param Response               $response    The Relay response
     * @param null|ResponseInterface $psrResponse The PSR-7 response (optional for backwards compatibility)
     */
    public function outputResponse(Response $response, ?ResponseInterface $psrResponse = null): void
    {
        if (!$this->debugResponse) {
            return;
        }

        if ($this->responseHandler instanceof Closure) {
            ($this->responseHandler)($response, $psrResponse ?? $response->toPsrResponse());

            if ($this->die) {
                exit(1); // @codeCoverageIgnore
            }

            return;
        }

        $debugger = new Debugger();
        $output = $debugger->formatResponse($response);

        $this->output($output);

        if ($this->die) {
            exit(1); // @codeCoverageIgnore
        }
    }

    /**
     * Output debug data.
     */
    private function output(string $output): void
    {
        if (class_exists(VarDumper::class)) {
            echo $output."\n";
        } else {
            // @codeCoverageIgnoreStart
            echo $output."\n";
            // @codeCoverageIgnoreEnd
        }
    }
}

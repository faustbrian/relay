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

/**
 * Adds debugging capabilities to Connector and Request classes.
 *
 * Provides Saloon-style debugging methods:
 * - debug() - Debug both request and response
 * - debugRequest() - Debug only the outgoing request
 * - debugResponse() - Debug only the incoming response
 *
 * @author Brian Faust <brian@cline.sh>
 */
trait HasDebugging
{
    private ?DebuggingState $debuggingState = null;

    /**
     * Enable debugging for both request and response.
     *
     * @param bool $die Terminate application after receiving response
     */
    public function debug(bool $die = false): static
    {
        $this->getDebuggingState()
            ->enableRequestDebugging()
            ->enableResponseDebugging()
            ->setDie($die);

        return $this;
    }

    /**
     * Enable debugging for outgoing requests only.
     *
     * @param bool                                          $die     Terminate after debugging request
     * @param null|Closure(Request, ?RequestInterface):void $handler Custom handler for request debugging
     */
    public function debugRequest(bool $die = false, ?Closure $handler = null): static
    {
        $this->getDebuggingState()
            ->enableRequestDebugging()
            ->setRequestHandler($handler)
            ->setDie($die);

        return $this;
    }

    /**
     * Enable debugging for incoming responses only.
     *
     * @param bool                                           $die     Terminate after debugging response
     * @param null|Closure(Response, ResponseInterface):void $handler Custom handler for response debugging
     */
    public function debugResponse(bool $die = false, ?Closure $handler = null): static
    {
        $this->getDebuggingState()
            ->enableResponseDebugging()
            ->setResponseHandler($handler)
            ->setDie($die);

        return $this;
    }

    /**
     * Get the debugging state.
     */
    public function getDebuggingState(): DebuggingState
    {
        if (!$this->debuggingState instanceof DebuggingState) {
            $this->debuggingState = new DebuggingState();
        }

        return $this->debuggingState;
    }

    /**
     * Check if debugging is enabled.
     */
    public function hasDebugging(): bool
    {
        return $this->debuggingState instanceof DebuggingState && $this->debuggingState->isEnabled();
    }
}

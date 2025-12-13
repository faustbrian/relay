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
 * Intercept and debug all HTTP traffic across all connectors.
 *
 * ```php
 * use Cline\Relay\Observability\Debugging\Wiretap;
 *
 * // Tap into all requests
 * Wiretap::requests(function (Request $request, RequestInterface $psrRequest) {
 *     ray($psrRequest);
 * });
 *
 * // Tap into all responses
 * Wiretap::responses(function (Response $response, ResponseInterface $psrResponse) {
 *     ray($psrResponse);
 * });
 *
 * // Tap both with default formatted output
 * Wiretap::enable();
 *
 * // Stop tapping
 * Wiretap::disable();
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Wiretap
{
    private static bool $tapRequests = false;

    private static bool $tapResponses = false;

    /** @var null|Closure(Request, RequestInterface): void */
    private static ?Closure $requestHandler = null;

    /** @var null|Closure(Response, ResponseInterface): void */
    private static ?Closure $responseHandler = null;

    /**
     * Enable wiretap for both requests and responses.
     */
    public static function enable(): void
    {
        self::$tapRequests = true;
        self::$tapResponses = true;
    }

    /**
     * Disable all wiretapping.
     */
    public static function disable(): void
    {
        self::$tapRequests = false;
        self::$tapResponses = false;
        self::$requestHandler = null;
        self::$responseHandler = null;
    }

    /**
     * Tap into all outgoing requests.
     *
     * @param null|Closure(Request, RequestInterface): void $handler Custom handler
     */
    public static function requests(?Closure $handler = null): void
    {
        self::$tapRequests = true;
        self::$requestHandler = $handler;
    }

    /**
     * Tap into all incoming responses.
     *
     * @param null|Closure(Response, ResponseInterface): void $handler Custom handler
     */
    public static function responses(?Closure $handler = null): void
    {
        self::$tapResponses = true;
        self::$responseHandler = $handler;
    }

    /**
     * Check if request tapping is enabled.
     */
    public static function isTappingRequests(): bool
    {
        return self::$tapRequests;
    }

    /**
     * Check if response tapping is enabled.
     */
    public static function isTappingResponses(): bool
    {
        return self::$tapResponses;
    }

    /**
     * Output request debug info if tapping is enabled.
     *
     * @internal Called by Connector
     */
    public static function tapRequest(Request $request, RequestInterface $psrRequest): void
    {
        if (!self::$tapRequests) {
            return;
        }

        if (self::$requestHandler instanceof Closure) {
            (self::$requestHandler)($request, $psrRequest);

            return;
        }

        $debugger = new Debugger();
        $baseUrl = $request->connector()?->resolveBaseUrl() ?? '';
        $output = $debugger->formatRequest($request, $baseUrl);

        echo $output."\n";
    }

    /**
     * Output response debug info if tapping is enabled.
     *
     * @internal Called by Connector
     */
    public static function tapResponse(Response $response, ResponseInterface $psrResponse): void
    {
        if (!self::$tapResponses) {
            return;
        }

        if (self::$responseHandler instanceof Closure) {
            (self::$responseHandler)($response, $psrResponse);

            return;
        }

        $debugger = new Debugger();
        $output = $debugger->formatResponse($response);

        echo $output."\n";
    }

    /**
     * Reset state (useful for testing).
     *
     * @internal
     */
    public static function reset(): void
    {
        self::disable();
    }
}

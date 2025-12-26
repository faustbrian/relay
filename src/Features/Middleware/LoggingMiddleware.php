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
use Psr\Log\LoggerInterface;

use function microtime;
use function round;

/**
 * Middleware that logs requests and responses.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class LoggingMiddleware implements Middleware
{
    public function __construct(
        private LoggerInterface $logger,
        private bool $logRequestBody = false,
        private bool $logResponseBody = false,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        $context = [
            'method' => $request->method(),
            'endpoint' => $request->endpoint(),
        ];

        if ($this->logRequestBody && $request->body() !== null) {
            $context['request_body'] = $request->body();
        }

        $this->logger->info('HTTP Request', $context);

        $response = $next($request);

        $duration = (microtime(true) - $startTime) * 1_000;

        $responseContext = [
            'method' => $request->method(),
            'endpoint' => $request->endpoint(),
            'status' => $response->status(),
            'duration_ms' => round($duration, 2),
        ];

        if ($this->logResponseBody) {
            $responseContext['response_body'] = $response->json();
        }

        $this->logger->info('HTTP Response', $responseContext);

        return $response;
    }
}

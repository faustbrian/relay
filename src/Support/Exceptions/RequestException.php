<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Exceptions;

use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;
use GuzzleHttp\Exception\RequestException as GuzzleException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

use function sprintf;

/**
 * Base exception for request failures.
 *
 * @author Brian Faust <brian@cline.sh>
 */
abstract class RequestException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly Request $request,
        private readonly ?Response $response = null,
    ) {
        parent::__construct($message, $response?->status() ?? 0);
    }

    public static function fromResponse(Request $request, Response $response): static
    {
        // @phpstan-ignore-next-line - Abstract class is meant to be extended, static instantiation is intentional
        return new static(
            sprintf(
                'HTTP request returned status code %d',
                $response->status(),
            ),
            $request,
            $response,
        );
    }

    /**
     * Create from a Guzzle exception.
     */
    public static function fromGuzzleException(GuzzleException $exception, Request $request): static
    {
        $psrResponse = $exception->getResponse();
        $response = ($exception->hasResponse() && $psrResponse instanceof ResponseInterface)
            ? new Response($psrResponse, $request)
            : null;

        // @phpstan-ignore-next-line - Abstract class is meant to be extended, static instantiation is intentional
        return new static(
            $exception->getMessage(),
            $request,
            $response,
        );
    }

    public function request(): Request
    {
        return $this->request;
    }

    public function response(): ?Response
    {
        return $this->response;
    }

    public function status(): int
    {
        return $this->response?->status() ?? 0;
    }
}

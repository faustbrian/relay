<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Exceptions\Client;

use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;
use Cline\Relay\Support\Exceptions\ClientException;
use Override;

/**
 * Exception for 429 Too Many Requests responses.
 *
 * Also thrown by client-side rate limiting before requests are made.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class RateLimitException extends ClientException
{
    private ?int $retryAfterSeconds = null;

    private ?int $rateLimitLimit = null;

    private ?int $rateLimitRemaining = null;

    public static function exceeded(?int $retryAfterSeconds, int $limit, int $remaining): self
    {
        $exception = new self(
            'Rate limit exceeded',
            new class() extends Request
            {
                public function endpoint(): string
                {
                    return '';
                }
            },
        );

        $exception->retryAfterSeconds = $retryAfterSeconds;
        $exception->rateLimitLimit = $limit;
        $exception->rateLimitRemaining = $remaining;

        return $exception;
    }

    #[Override()]
    public static function fromResponse(Request $request, Response $response): static
    {
        $exception = new self(
            'Rate limit exceeded',
            $request,
            $response,
        );

        $retryAfter = $response->header('Retry-After');
        $limit = $response->header('X-RateLimit-Limit');
        $remaining = $response->header('X-RateLimit-Remaining');

        $exception->retryAfterSeconds = $retryAfter !== null ? (int) $retryAfter : null;
        $exception->rateLimitLimit = $limit !== null ? (int) $limit : null;
        $exception->rateLimitRemaining = $remaining !== null ? (int) $remaining : null;

        return $exception;
    }

    /**
     * Seconds until the rate limit resets.
     */
    public function retryAfter(): ?int
    {
        return $this->retryAfterSeconds;
    }

    /**
     * Total rate limit.
     */
    public function limit(): ?int
    {
        return $this->rateLimitLimit;
    }

    /**
     * Remaining requests before limit.
     */
    public function remaining(): ?int
    {
        return $this->rateLimitRemaining;
    }

    /**
     * Check if this was a server-side rate limit (429 response).
     */
    public function isServerSide(): bool
    {
        return $this->response() instanceof Response;
    }

    /**
     * Check if this was a client-side rate limit.
     */
    public function isClientSide(): bool
    {
        return !$this->response() instanceof Response;
    }
}

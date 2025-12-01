<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Features\RateLimiting;

use DateTimeImmutable;
use Illuminate\Support\Facades\Date;

use function max;

/**
 * Rate limit information from response headers.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class RateLimitInfo
{
    public function __construct(
        public ?int $limit,
        public ?int $remaining,
        public ?int $reset,
    ) {}

    /**
     * Get the total rate limit.
     */
    public function limit(): ?int
    {
        return $this->limit;
    }

    /**
     * Get the remaining requests.
     */
    public function remaining(): ?int
    {
        return $this->remaining;
    }

    /**
     * Get the reset timestamp.
     */
    public function reset(): ?DateTimeImmutable
    {
        if ($this->reset === null) {
            return null;
        }

        return DateTimeImmutable::createFromFormat('U', (string) $this->reset) ?: null;
    }

    /**
     * Get seconds until reset.
     */
    public function secondsUntilReset(): ?int
    {
        if ($this->reset === null) {
            return null;
        }

        return max(0, $this->reset - Date::now()->getTimestamp());
    }
}

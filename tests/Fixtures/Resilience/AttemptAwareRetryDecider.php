<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\Resilience;

use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;
use Cline\Relay\Support\Contracts\RetryDecider;

/**
 * Test retry decider that uses attempt count.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class AttemptAwareRetryDecider implements RetryDecider
{
    public function __invoke(Request $request, Response $response, int $attempt): bool
    {
        // Only retry if attempt < 3
        return $attempt < 3;
    }
}

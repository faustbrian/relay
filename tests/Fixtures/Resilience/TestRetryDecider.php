<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\Resilience;

use Cline\Relay\Core\AbstractRequest;
use Cline\Relay\Core\Response;
use Cline\Relay\Support\Contracts\RetryDeciderInterface;

use function in_array;

/**
 * Test retry decider for unit tests.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class TestRetryDecider implements RetryDeciderInterface
{
    public function __invoke(AbstractRequest $request, Response $response, int $attempt): bool
    {
        // Only retry on 429 and 503
        return in_array($response->status(), [429, 503], true);
    }
}

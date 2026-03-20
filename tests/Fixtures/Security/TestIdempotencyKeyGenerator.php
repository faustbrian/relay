<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\Security;

use Cline\Relay\Core\AbstractRequest;
use Cline\Relay\Support\Contracts\IdempotencyKeyGeneratorInterface;

use function hash;
use function serialize;

/**
 * Test idempotency key generator for unit tests.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class TestIdempotencyKeyGenerator implements IdempotencyKeyGeneratorInterface
{
    public function generate(AbstractRequest $request): string
    {
        // Generate key based on request endpoint and body
        return hash('sha256', $request->endpoint().serialize($request->body() ?? []));
    }
}

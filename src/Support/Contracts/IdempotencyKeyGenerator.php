<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Contracts;

use Cline\Relay\Core\Request;

/**
 * Interface for idempotency key generation.
 *
 * Implement this interface to create reusable idempotency key generators
 * that can be shared across requests.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface IdempotencyKeyGenerator
{
    /**
     * Generate an idempotency key for the given request.
     */
    public function generate(Request $request): string;
}

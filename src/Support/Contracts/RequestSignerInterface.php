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
 * Interface for request signing implementations.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface RequestSignerInterface
{
    /**
     * Sign a request.
     */
    public function sign(Request $request): Request;

    /**
     * Verify a request signature.
     */
    public function verify(Request $request, string $signature, ?string $timestamp = null): bool;
}

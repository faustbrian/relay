<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Features\Auth;

use Cline\Relay\Core\Request;
use Cline\Relay\Support\Contracts\Authenticator;
use Closure;

/**
 * Custom callable authentication.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class CallableAuth implements Authenticator
{
    /**
     * @param Closure(Request): Request $callback
     */
    public function __construct(
        private Closure $callback,
    ) {}

    public function authenticate(Request $request): Request
    {
        return ($this->callback)($request);
    }
}

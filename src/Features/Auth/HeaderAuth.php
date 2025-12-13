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

/**
 * Custom header authentication.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class HeaderAuth implements Authenticator
{
    public function __construct(
        private string $headerName,
        private string $value,
    ) {}

    public function authenticate(Request $request): Request
    {
        return $request->withHeader($this->headerName, $this->value);
    }
}

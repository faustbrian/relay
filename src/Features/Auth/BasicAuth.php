<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Features\Auth;

use Cline\Relay\Core\AbstractRequest;
use Cline\Relay\Support\Contracts\AuthenticatorInterface;

/**
 * HTTP Basic authentication.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class BasicAuth implements AuthenticatorInterface
{
    public function __construct(
        private string $username,
        private string $password,
    ) {}

    public function authenticate(AbstractRequest $request): AbstractRequest
    {
        return $request->withBasicAuth($this->username, $this->password);
    }
}

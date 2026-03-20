<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Contracts;

use Cline\Relay\Core\AbstractRequest;

/**
 * Interface for authentication strategies.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface AuthenticatorInterface
{
    /**
     * Authenticate the request.
     */
    public function authenticate(AbstractRequest $request): AbstractRequest;
}

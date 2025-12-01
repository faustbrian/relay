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
 * Interface for authentication strategies.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface Authenticator
{
    /**
     * Authenticate the request.
     */
    public function authenticate(Request $request): Request;
}

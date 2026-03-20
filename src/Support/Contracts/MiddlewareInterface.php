<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Contracts;

use Cline\Relay\Core\AbstractRequest;
use Cline\Relay\Core\Response;
use Closure;

/**
 * Interface for request/response middleware.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface MiddlewareInterface
{
    /**
     * Process the request and return a response.
     *
     * @param Closure(AbstractRequest): Response $next
     */
    public function handle(AbstractRequest $request, Closure $next): Response;
}

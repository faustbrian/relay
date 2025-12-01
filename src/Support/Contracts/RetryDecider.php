<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Contracts;

use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;

/**
 * Interface for invokable retry deciders.
 *
 * Implement this interface to create reusable retry decision logic
 * that can be passed as a callback to the Retry attribute.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface RetryDecider
{
    /**
     * Determine if the request should be retried.
     */
    public function __invoke(Request $request, Response $response, int $attempt): bool;
}

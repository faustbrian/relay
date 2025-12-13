<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Exceptions;

use Exception;

/**
 * Exception for OAuth state mismatch.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidStateException extends Exception
{
    public function __construct()
    {
        parent::__construct('The state parameter does not match the expected value.');
    }
}

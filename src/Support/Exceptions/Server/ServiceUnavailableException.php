<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Exceptions\Server;

use Cline\Relay\Support\Exceptions\ServerException;

/**
 * Exception for 503 Service Unavailable responses.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ServiceUnavailableException extends ServerException {}

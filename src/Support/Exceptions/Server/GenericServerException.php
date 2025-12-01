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
 * Generic server exception for unhandled 5xx status codes.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class GenericServerException extends ServerException {}

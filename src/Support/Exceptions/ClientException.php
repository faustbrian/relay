<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Exceptions;

/**
 * Exception for 4xx client errors.
 *
 * @author Brian Faust <brian@cline.sh>
 */
abstract class ClientException extends RequestException {}

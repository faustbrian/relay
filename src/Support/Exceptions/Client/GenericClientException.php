<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Exceptions\Client;

use Cline\Relay\Support\Exceptions\ClientException;

/**
 * Generic client exception for unhandled 4xx status codes.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class GenericClientException extends ClientException {}

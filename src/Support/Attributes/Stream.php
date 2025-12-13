<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Attributes;

use Attribute;

/**
 * Mark a request to use streaming for the response.
 *
 * When applied to a request, the response body will be streamed
 * instead of fully buffered in memory. This is useful for
 * downloading large files or reading server-sent events.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Stream {}

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Attributes\ContentTypes;

use Attribute;
use Cline\Relay\Support\Contracts\ContentType;

/**
 * Mark a request as using multipart form data content type (multipart/form-data).
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Multipart implements ContentType
{
    public function contentType(): string
    {
        return 'multipart/form-data';
    }
}

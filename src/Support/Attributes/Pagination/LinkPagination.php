<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Attributes\Pagination;

use Attribute;

/**
 * Configure Link header pagination (GitHub/REST style) for a request.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class LinkPagination
{
    public function __construct(
        /**
         * Response key containing the items.
         */
        public string $dataKey = 'data',
    ) {}
}

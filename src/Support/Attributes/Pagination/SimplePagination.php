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
 * Configure simple pagination (no total count) for a request.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class SimplePagination
{
    public function __construct(
        /**
         * Query parameter name for the page number.
         */
        public string $page = 'page',
        /**
         * Query parameter name for items per page.
         */
        public string $perPage = 'per_page',
        /**
         * Response key containing the items.
         */
        public string $dataKey = 'data',
        /**
         * Response key indicating if there are more pages.
         */
        public string $hasMoreKey = 'meta.has_more',
    ) {}
}

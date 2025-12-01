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
 * Configure cursor-based pagination for a request.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class CursorPagination
{
    public function __construct(
        /**
         * Query parameter name for the cursor.
         */
        public string $cursor = 'cursor',
        /**
         * Query parameter name for items per page.
         */
        public string $perPage = 'per_page',
        /**
         * Response key for the next cursor.
         */
        public string $nextKey = 'meta.next_cursor',
        /**
         * Response key containing the items.
         */
        public string $dataKey = 'data',
    ) {}
}

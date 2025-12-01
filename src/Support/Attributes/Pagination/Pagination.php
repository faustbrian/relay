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
 * Configure page-based pagination for a request.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Pagination
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
         * Response key for total pages (optional).
         */
        public ?string $totalPagesKey = 'meta.last_page',
        /**
         * Response key for total items (optional).
         */
        public ?string $totalKey = 'meta.total',
    ) {}
}

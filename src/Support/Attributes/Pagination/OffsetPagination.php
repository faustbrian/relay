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
 * Configure offset-based pagination for a request.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class OffsetPagination
{
    public function __construct(
        /**
         * Query parameter name for the offset.
         */
        public string $offset = 'offset',
        /**
         * Query parameter name for the limit.
         */
        public string $limit = 'limit',
        /**
         * Response key containing the items.
         */
        public string $dataKey = 'data',
        /**
         * Response key for total items (optional).
         */
        public ?string $totalKey = 'meta.total',
    ) {}
}

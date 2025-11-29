<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Features\Pagination;

use Cline\Relay\Core\Response;
use Cline\Relay\Support\Attributes\Pagination\OffsetPagination;
use Cline\Relay\Support\Contracts\Paginator;

use function array_values;
use function count;
use function is_array;
use function is_int;

/**
 * Offset-based pagination strategy.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class OffsetPaginator implements Paginator
{
    public function __construct(
        private OffsetPagination $config,
        private int $limit = 15,
        private int $currentOffset = 0,
    ) {}

    public function getNextPage(Response $response): ?array
    {
        if (!$this->hasMorePages($response)) {
            return null;
        }

        $items = $this->getItems($response);

        return [
            $this->config->offset => $this->currentOffset + count($items),
            $this->config->limit => $this->limit,
        ];
    }

    public function getItems(Response $response): array
    {
        /** @var mixed $items */
        $items = $response->json($this->config->dataKey);

        if (!is_array($items)) {
            return [];
        }

        /** @var array<int, mixed> */
        return array_values($items);
    }

    public function hasMorePages(Response $response): bool
    {
        $items = $this->getItems($response);

        if ($items === []) {
            return false;
        }

        // Check total if available
        if ($this->config->totalKey !== null) {
            /** @var mixed $total */
            $total = $response->json($this->config->totalKey);

            if (is_int($total)) {
                return $this->currentOffset + count($items) < $total;
            }
        }

        // Fallback: assume more pages if we got a full page of items
        return count($items) >= $this->limit;
    }
}

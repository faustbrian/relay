<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Features\Pagination;

use Cline\Relay\Core\Response;
use Cline\Relay\Support\Attributes\Pagination\Pagination;
use Cline\Relay\Support\Contracts\Paginator;

use function array_values;
use function count;
use function is_array;
use function is_int;

/**
 * Page-based pagination strategy.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class PagePaginator implements Paginator
{
    public function __construct(
        private Pagination $config,
        private int $currentPage = 1,
        private int $perPage = 15,
    ) {}

    public function getNextPage(Response $response): ?array
    {
        if (!$this->hasMorePages($response)) {
            return null;
        }

        return [
            $this->config->page => $this->getCurrentPage($response) + 1,
            $this->config->perPage => $this->perPage,
        ];
    }

    public function getItems(Response $response): array
    {
        /** @var mixed $items */
        $items = $response->json($this->config->dataKey);

        if (!is_array($items)) {
            return [];
        }

        return array_values($items);
    }

    public function hasMorePages(Response $response): bool
    {
        $items = $this->getItems($response);

        if ($items === []) {
            return false;
        }

        // Check total pages if available
        if ($this->config->totalPagesKey !== null) {
            /** @var mixed $totalPages */
            $totalPages = $response->json($this->config->totalPagesKey);

            if (is_int($totalPages)) {
                return $this->getCurrentPage($response) < $totalPages;
            }
        }

        // Check total items if available
        if ($this->config->totalKey !== null) {
            /** @var mixed $total */
            $total = $response->json($this->config->totalKey);

            if (is_int($total)) {
                $currentOffset = ($this->getCurrentPage($response) - 1) * $this->perPage;

                return $currentOffset + count($items) < $total;
            }
        }

        // Fallback: assume more pages if we got a full page of items
        return count($items) >= $this->perPage;
    }

    private function getCurrentPage(Response $response): int
    {
        // Try to get current page from response meta
        /** @var mixed $currentPage */
        $currentPage = $response->json('meta.current_page');

        return is_int($currentPage) ? $currentPage : $this->currentPage;
    }
}

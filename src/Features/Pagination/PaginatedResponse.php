<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Features\Pagination;

use Cline\Relay\Core\Connector;
use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;
use Cline\Relay\Support\Contracts\Paginator;
use Generator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator as LaravelPaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Iterator;
use IteratorAggregate;

use function array_key_exists;
use function collect;
use function count;
use function is_array;
use function is_int;
use function iterator_to_array;

/**
 * Paginated response wrapper with iterator support.
 *
 * @author Brian Faust <brian@cline.sh>
 * @implements IteratorAggregate<int, mixed>
 */
final class PaginatedResponse implements IteratorAggregate
{
    private ?int $maxPages = null;

    private int $pagesLoaded = 0;

    public function __construct(
        private readonly Connector $connector,
        private readonly Request $request,
        private readonly Paginator $paginator,
        private readonly Response $initialResponse,
    ) {}

    /**
     * Get the items from the first page.
     *
     * @return array<int, mixed>
     */
    public function items(): array
    {
        return $this->paginator->getItems($this->initialResponse);
    }

    /**
     * Get the next cursor (for cursor pagination).
     */
    public function nextCursor(): mixed
    {
        $nextPage = $this->paginator->getNextPage($this->initialResponse);

        return $nextPage['cursor'] ?? $nextPage['after'] ?? null;
    }

    /**
     * Check if there are more pages.
     */
    public function hasMore(): bool
    {
        return $this->paginator->hasMorePages($this->initialResponse);
    }

    /**
     * Limit the number of pages to fetch.
     */
    public function take(int $maxPages): self
    {
        $this->maxPages = $maxPages;

        return $this;
    }

    /**
     * Get all items from all pages as a single collection.
     *
     * @return Collection<int, mixed>
     */
    public function collect(): Collection
    {
        return collect(iterator_to_array($this));
    }

    /**
     * Get the first page.
     */
    public function first(): self
    {
        return $this;
    }

    /**
     * Get a lazy collection for memory-efficient iteration.
     *
     * @return LazyCollection<int, mixed>
     */
    public function lazy(): LazyCollection
    {
        return LazyCollection::make(function (): Generator {
            yield from $this;
        });
    }

    /**
     * Convert to Laravel's LengthAwarePaginator.
     *
     * @param  array<string, mixed>             $options
     * @return LengthAwarePaginator<int, mixed>
     */
    public function toLaravelPaginator(
        int $perPage = 15,
        string $pageName = 'page',
        ?int $page = null,
        array $options = [],
    ): LengthAwarePaginator {
        $items = $this->items();
        $meta = $this->initialResponse->json('meta');
        $total = is_array($meta) && array_key_exists('total', $meta) && is_int($meta['total']) ? $meta['total'] : count($items);

        $currentPage = $page ?? (is_array($meta) && array_key_exists('current_page', $meta) && is_int($meta['current_page']) ? $meta['current_page'] : 1);

        return new LengthAwarePaginator(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: $currentPage,
            options: $options,
        );
    }

    /**
     * Convert to Laravel's simple Paginator (no total).
     *
     * @param  array<string, mixed>         $options
     * @return LaravelPaginator<int, mixed>
     */
    public function toLaravelSimplePaginator(
        int $perPage = 15,
        string $pageName = 'page',
        ?int $page = null,
        array $options = [],
    ): LaravelPaginator {
        $items = $this->items();
        $meta = $this->initialResponse->json('meta');
        $currentPage = $page ?? (is_array($meta) && array_key_exists('current_page', $meta) && is_int($meta['current_page']) ? $meta['current_page'] : 1);

        return new LaravelPaginator(
            items: $items,
            perPage: $perPage,
            currentPage: $currentPage,
            options: $options,
        );
    }

    /**
     * Iterate over each item with a callback.
     *
     * @param callable(mixed, int): void $callback
     */
    public function each(callable $callback): void
    {
        foreach ($this as $index => $item) {
            $callback($item, $index);
        }
    }

    /**
     * Iterator implementation - yields each item from all pages.
     *
     * @return Iterator<int, mixed>
     */
    public function getIterator(): Iterator
    {
        // Yield items from first page
        foreach ($this->paginator->getItems($this->initialResponse) as $item) {
            yield $item;
        }

        $this->pagesLoaded = 1;

        // Fetch and yield items from subsequent pages
        $currentResponse = $this->initialResponse;

        while ($this->shouldContinue($currentResponse)) {
            $nextParams = $this->paginator->getNextPage($currentResponse);

            if ($nextParams === null) {
                break;
            }

            // Create a new request with next page parameters
            $nextRequest = $this->request->clone();

            foreach ($nextParams as $key => $value) {
                $nextRequest = $nextRequest->withQuery($key, $value);
            }

            // Send the request
            $currentResponse = $this->connector->send($nextRequest);
            ++$this->pagesLoaded;

            // Yield items from this page
            foreach ($this->paginator->getItems($currentResponse) as $item) {
                yield $item;
            }
        }
    }

    /**
     * Check if we should continue fetching pages.
     */
    private function shouldContinue(Response $response): bool
    {
        // Check max pages limit
        if ($this->maxPages !== null && $this->pagesLoaded >= $this->maxPages) {
            return false;
        }

        return $this->paginator->hasMorePages($response);
    }
}

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Features\Pagination;

use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;
use Cline\Relay\Support\Attributes\Pagination\CursorPagination;
use Cline\Relay\Support\Contracts\Paginator;

use function array_values;
use function is_array;
use function is_int;
use function is_string;

/**
 * Cursor-based pagination strategy.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class CursorPaginator implements Paginator
{
    public function __construct(
        private CursorPagination $config,
        private int $perPage = 15,
    ) {}

    public function getNextPage(Response $response): ?array
    {
        $nextCursor = $response->json($this->config->nextKey);

        if (!is_string($nextCursor) && !is_int($nextCursor)) {
            return null;
        }

        return [
            $this->config->cursor => $nextCursor,
            $this->config->perPage => $this->perPage,
        ];
    }

    public function nextRequest(Request $request, Response $response): ?Request
    {
        $nextPage = $this->getNextPage($response);

        if ($nextPage === null) {
            return null;
        }

        $nextRequest = $request->clone();

        foreach ($nextPage as $key => $value) {
            $nextRequest = $nextRequest->withQuery($key, $value);
        }

        return $nextRequest;
    }

    public function getItems(Response $response): array
    {
        $items = $response->json($this->config->dataKey);

        if (!is_array($items)) {
            return [];
        }

        return array_values($items);
    }

    public function hasMorePages(Response $response): bool
    {
        $nextCursor = $response->json($this->config->nextKey);

        return is_string($nextCursor) || is_int($nextCursor);
    }
}

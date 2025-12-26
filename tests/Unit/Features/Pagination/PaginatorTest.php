<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Relay\Core\Response;
use Cline\Relay\Features\Pagination\CursorPaginator;
use Cline\Relay\Features\Pagination\LinkHeaderPaginator;
use Cline\Relay\Features\Pagination\OffsetPaginator;
use Cline\Relay\Features\Pagination\PagePaginator;
use Cline\Relay\Support\Attributes\Pagination\CursorPagination;
use Cline\Relay\Support\Attributes\Pagination\LinkPagination;
use Cline\Relay\Support\Attributes\Pagination\OffsetPagination;
use Cline\Relay\Support\Attributes\Pagination\Pagination;

describe('PagePaginator', function (): void {
    it('extracts items from data key', function (): void {
        $paginator = new PagePaginator(
            new Pagination(),
        );
        $response = Response::make([
            'data' => [
                ['id' => 1],
                ['id' => 2],
            ],
            'meta' => [
                'current_page' => 1,
                'last_page' => 3,
            ],
        ]);

        $items = $paginator->getItems($response);

        expect($items)->toBe([
            ['id' => 1],
            ['id' => 2],
        ]);
    });

    it('detects more pages from total pages', function (): void {
        $paginator = new PagePaginator(
            new Pagination(),
        );
        $response = Response::make([
            'data' => [['id' => 1]],
            'meta' => [
                'current_page' => 1,
                'last_page' => 3,
            ],
        ]);

        expect($paginator->hasMorePages($response))->toBeTrue();
    });

    it('detects no more pages on last page', function (): void {
        $paginator = new PagePaginator(
            new Pagination(),
        );
        $response = Response::make([
            'data' => [['id' => 1]],
            'meta' => [
                'current_page' => 3,
                'last_page' => 3,
            ],
        ]);

        expect($paginator->hasMorePages($response))->toBeFalse();
    });

    it('returns next page parameters', function (): void {
        $paginator = new PagePaginator(
            new Pagination(),
            perPage: 25,
        );
        $response = Response::make([
            'data' => [['id' => 1]],
            'meta' => [
                'current_page' => 1,
                'last_page' => 3,
            ],
        ]);

        $nextPage = $paginator->getNextPage($response);

        expect($nextPage)->toBe([
            'page' => 2,
            'per_page' => 25,
        ]);
    });

    it('returns null when there are no more pages', function (): void {
        $paginator = new PagePaginator(
            new Pagination(),
        );
        $response = Response::make([
            'data' => [['id' => 1]],
            'meta' => [
                'current_page' => 3,
                'last_page' => 3,
            ],
        ]);

        $nextPage = $paginator->getNextPage($response);

        expect($nextPage)->toBeNull();
    });

    it('returns false when items array is empty', function (): void {
        $paginator = new PagePaginator(
            new Pagination(),
        );
        $response = Response::make([
            'data' => [],
        ]);

        expect($paginator->hasMorePages($response))->toBeFalse();
    });

    it('detects more pages using totalKey when totalPagesKey is null', function (): void {
        $paginator = new PagePaginator(
            new Pagination(
                totalPagesKey: null,
                totalKey: 'meta.total',
            ),
            currentPage: 1,
            perPage: 10,
        );
        $response = Response::make([
            'data' => array_fill(0, 10, ['id' => 1]),
            'meta' => [
                'current_page' => 1,
                'total' => 50,
            ],
        ]);

        expect($paginator->hasMorePages($response))->toBeTrue();
    });

    it('detects no more pages using totalKey when on last page', function (): void {
        $paginator = new PagePaginator(
            new Pagination(
                totalPagesKey: null,
                totalKey: 'meta.total',
            ),
            currentPage: 5,
            perPage: 10,
        );
        $response = Response::make([
            'data' => array_fill(0, 5, ['id' => 1]),
            'meta' => [
                'current_page' => 5,
                'total' => 45,
            ],
        ]);

        expect($paginator->hasMorePages($response))->toBeFalse();
    });

    it('uses item count fallback when totalPagesKey and totalKey are null', function (): void {
        $paginator = new PagePaginator(
            new Pagination(
                totalPagesKey: null,
                totalKey: null,
            ),
            perPage: 15,
        );
        $response = Response::make([
            'data' => array_fill(0, 15, ['id' => 1]),
        ]);

        expect($paginator->hasMorePages($response))->toBeTrue();
    });

    it('detects no more pages using item count fallback when fewer items than perPage', function (): void {
        $paginator = new PagePaginator(
            new Pagination(
                totalPagesKey: null,
                totalKey: null,
            ),
            perPage: 15,
        );
        $response = Response::make([
            'data' => array_fill(0, 10, ['id' => 1]),
        ]);

        expect($paginator->hasMorePages($response))->toBeFalse();
    });

    it('handles getItems when data key contains non-array value', function (): void {
        $paginator = new PagePaginator(
            new Pagination(),
        );
        $response = Response::make([
            'data' => null,
        ]);

        $items = $paginator->getItems($response);

        expect($items)->toBe([]);
    });
});

describe('CursorPaginator', function (): void {
    it('extracts items from data key', function (): void {
        $paginator = new CursorPaginator(
            new CursorPagination(),
        );
        $response = Response::make([
            'data' => [
                ['id' => 1],
                ['id' => 2],
            ],
            'meta' => [
                'next_cursor' => 'abc123',
            ],
        ]);

        $items = $paginator->getItems($response);

        expect($items)->toBe([
            ['id' => 1],
            ['id' => 2],
        ]);
    });

    it('detects more pages when next cursor exists', function (): void {
        $paginator = new CursorPaginator(
            new CursorPagination(),
        );
        $response = Response::make([
            'data' => [['id' => 1]],
            'meta' => [
                'next_cursor' => 'abc123',
            ],
        ]);

        expect($paginator->hasMorePages($response))->toBeTrue();
    });

    it('detects no more pages when next cursor is null', function (): void {
        $paginator = new CursorPaginator(
            new CursorPagination(),
        );
        $response = Response::make([
            'data' => [['id' => 1]],
            'meta' => [
                'next_cursor' => null,
            ],
        ]);

        expect($paginator->hasMorePages($response))->toBeFalse();
    });

    it('returns next page parameters with cursor', function (): void {
        $paginator = new CursorPaginator(
            new CursorPagination(),
            perPage: 50,
        );
        $response = Response::make([
            'data' => [['id' => 1]],
            'meta' => [
                'next_cursor' => 'abc123',
            ],
        ]);

        $nextPage = $paginator->getNextPage($response);

        expect($nextPage)->toBe([
            'cursor' => 'abc123',
            'per_page' => 50,
        ]);
    });

    it('returns null when next cursor is null in getNextPage', function (): void {
        $paginator = new CursorPaginator(
            new CursorPagination(),
            perPage: 25,
        );
        $response = Response::make([
            'data' => [['id' => 1]],
            'meta' => [
                'next_cursor' => null,
            ],
        ]);

        $nextPage = $paginator->getNextPage($response);

        expect($nextPage)->toBeNull();
    });

    it('handles getItems when data key contains non-array value', function (): void {
        $paginator = new CursorPaginator(
            new CursorPagination(),
        );
        $response = Response::make([
            'data' => null,
        ]);

        $items = $paginator->getItems($response);

        expect($items)->toBe([]);
    });
});

describe('OffsetPaginator', function (): void {
    it('extracts items from data key', function (): void {
        $paginator = new OffsetPaginator(
            new OffsetPagination(),
        );
        $response = Response::make([
            'data' => [
                ['id' => 1],
                ['id' => 2],
            ],
            'meta' => [
                'total' => 100,
            ],
        ]);

        $items = $paginator->getItems($response);

        expect($items)->toBe([
            ['id' => 1],
            ['id' => 2],
        ]);
    });

    it('detects more pages based on total', function (): void {
        $paginator = new OffsetPaginator(
            new OffsetPagination(),
            limit: 10,
            currentOffset: 0,
        );
        $response = Response::make([
            'data' => array_fill(0, 10, ['id' => 1]),
            'meta' => [
                'total' => 100,
            ],
        ]);

        expect($paginator->hasMorePages($response))->toBeTrue();
    });

    it('returns next page parameters with offset', function (): void {
        $paginator = new OffsetPaginator(
            new OffsetPagination(),
            limit: 10,
            currentOffset: 0,
        );
        $response = Response::make([
            'data' => array_fill(0, 10, ['id' => 1]),
            'meta' => [
                'total' => 100,
            ],
        ]);

        $nextPage = $paginator->getNextPage($response);

        expect($nextPage)->toBe([
            'offset' => 10,
            'limit' => 10,
        ]);
    });

    it('returns null when there are no more pages', function (): void {
        $paginator = new OffsetPaginator(
            new OffsetPagination(),
            limit: 10,
            currentOffset: 90,
        );
        $response = Response::make([
            'data' => array_fill(0, 10, ['id' => 1]),
            'meta' => [
                'total' => 100,
            ],
        ]);

        $nextPage = $paginator->getNextPage($response);

        expect($nextPage)->toBeNull();
    });

    it('returns false when items array is empty', function (): void {
        $paginator = new OffsetPaginator(
            new OffsetPagination(),
            limit: 10,
            currentOffset: 0,
        );
        $response = Response::make([
            'data' => [],
        ]);

        expect($paginator->hasMorePages($response))->toBeFalse();
    });

    it('uses item count fallback when totalKey is null', function (): void {
        $paginator = new OffsetPaginator(
            new OffsetPagination(totalKey: null),
            limit: 15,
            currentOffset: 0,
        );
        $response = Response::make([
            'data' => array_fill(0, 15, ['id' => 1]),
        ]);

        expect($paginator->hasMorePages($response))->toBeTrue();
    });

    it('detects no more pages using item count fallback when fewer items than limit', function (): void {
        $paginator = new OffsetPaginator(
            new OffsetPagination(totalKey: null),
            limit: 15,
            currentOffset: 0,
        );
        $response = Response::make([
            'data' => array_fill(0, 10, ['id' => 1]),
        ]);

        expect($paginator->hasMorePages($response))->toBeFalse();
    });

    it('handles getItems when data key contains non-array value', function (): void {
        $paginator = new OffsetPaginator(
            new OffsetPagination(),
        );
        $response = Response::make([
            'data' => null,
        ]);

        $items = $paginator->getItems($response);

        expect($items)->toBe([]);
    });
});

describe('LinkHeaderPaginator', function (): void {
    it('extracts items from data key', function (): void {
        $paginator = new LinkHeaderPaginator(
            new LinkPagination(),
        );
        $response = Response::make([
            'data' => [
                ['id' => 1],
                ['id' => 2],
            ],
        ]);

        $items = $paginator->getItems($response);

        expect($items)->toBe([
            ['id' => 1],
            ['id' => 2],
        ]);
    });

    it('parses Link header for next URL', function (): void {
        $paginator = new LinkHeaderPaginator(
            new LinkPagination(),
        );
        $response = Response::make([], 200, [
            'Link' => '<https://api.example.com/users?page=2>; rel="next", <https://api.example.com/users?page=5>; rel="last"',
        ]);

        expect($paginator->hasMorePages($response))->toBeTrue();
    });

    it('returns next page parameters from Link header', function (): void {
        $paginator = new LinkHeaderPaginator(
            new LinkPagination(),
        );
        $response = Response::make([], 200, [
            'Link' => '<https://api.example.com/users?page=2&per_page=10>; rel="next"',
        ]);

        $nextPage = $paginator->getNextPage($response);

        expect($nextPage)->toBe([
            'page' => '2',
            'per_page' => '10',
        ]);
    });

    it('returns null when no next link', function (): void {
        $paginator = new LinkHeaderPaginator(
            new LinkPagination(),
        );
        $response = Response::make([], 200, [
            'Link' => '<https://api.example.com/users?page=1>; rel="first"',
        ]);

        expect($paginator->getNextPage($response))->toBeNull();
        expect($paginator->hasMorePages($response))->toBeFalse();
    });

    it('returns null when next URL has no query string', function (): void {
        $paginator = new LinkHeaderPaginator(
            new LinkPagination(),
        );
        $response = Response::make([], 200, [
            'Link' => '<https://api.example.com/users>; rel="next"',
        ]);

        $nextPage = $paginator->getNextPage($response);

        expect($nextPage)->toBeNull();
    });

    it('extracts items from response root when dataKey is empty', function (): void {
        $paginator = new LinkHeaderPaginator(
            new LinkPagination(dataKey: ''),
        );
        $response = Response::make([
            ['id' => 1],
            ['id' => 2],
        ]);

        $items = $paginator->getItems($response);

        expect($items)->toBe([
            ['id' => 1],
            ['id' => 2],
        ]);
    });

    it('returns null when Link header is missing', function (): void {
        $paginator = new LinkHeaderPaginator(
            new LinkPagination(),
        );
        $response = Response::make([]);

        expect($paginator->getNextPage($response))->toBeNull();
        expect($paginator->hasMorePages($response))->toBeFalse();
    });

    it('handles malformed Link header with incomplete segments', function (): void {
        $paginator = new LinkHeaderPaginator(
            new LinkPagination(),
        );
        $response = Response::make([], 200, [
            'Link' => '<https://api.example.com/users?page=2>, <https://api.example.com/users?page=3>; rel="next"',
        ]);

        $nextPage = $paginator->getNextPage($response);

        expect($nextPage)->toBe([
            'page' => '3',
        ]);
    });
});

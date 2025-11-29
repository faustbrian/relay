<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Relay\Core\Connector;
use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;
use Cline\Relay\Features\Pagination\PaginatedResponse;
use Cline\Relay\Support\Contracts\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator as LaravelPaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Tests\Fixtures\MockableTrait;

/**
 * Test paginator that implements cursor-based pagination.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class TestPaginator implements Paginator
{
    public function getItems(Response $response): array
    {
        return $response->json('data') ?? [];
    }

    public function hasMorePages(Response $response): bool
    {
        return $response->json('has_more') ?? false;
    }

    public function getNextPage(Response $response): ?array
    {
        if (!$this->hasMorePages($response)) {
            return null;
        }

        $cursor = $response->json('next_cursor');
        $page = $response->json('next_page');

        if ($cursor !== null) {
            return ['cursor' => $cursor];
        }

        if ($page !== null) {
            return ['page' => $page];
        }

        return null;
    }
}

/**
 * Test paginator that uses 'after' parameter.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class AfterPaginator implements Paginator
{
    public function getItems(Response $response): array
    {
        return $response->json('items') ?? [];
    }

    public function hasMorePages(Response $response): bool
    {
        return $response->json('pagination.has_next') ?? false;
    }

    public function getNextPage(Response $response): ?array
    {
        if (!$this->hasMorePages($response)) {
            return null;
        }

        $after = $response->json('pagination.after');

        return $after !== null ? ['after' => $after] : null;
    }
}

/**
 * Test connector with MockableTrait for pagination testing.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class PaginationTestConnector extends Connector
{
    use MockableTrait;

    public function baseUrl(): string
    {
        return 'https://api.test.com';
    }
}

/**
 * Test request for pagination.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class TestRequest extends Request
{
    public function endpoint(): string
    {
        return '/items';
    }

    #[Override()]
    public function method(): string
    {
        return 'GET';
    }
}

describe('PaginatedResponse', function (): void {
    describe('Happy Paths', function (): void {
        test('returns items from initial response', function (): void {
            // Arrange
            $connector = new PaginationTestConnector();
            $request = new TestRequest();
            $paginator = new TestPaginator();
            $response = Response::make([
                'data' => [
                    ['id' => 1, 'name' => 'Item 1'],
                    ['id' => 2, 'name' => 'Item 2'],
                ],
                'has_more' => false,
            ], 200);

            // Act
            $paginatedResponse = new PaginatedResponse($connector, $request, $paginator, $response);
            $items = $paginatedResponse->items();

            // Assert
            expect($items)->toHaveCount(2)
                ->and($items[0])->toBe(['id' => 1, 'name' => 'Item 1'])
                ->and($items[1])->toBe(['id' => 2, 'name' => 'Item 2']);
        });

        test('returns cursor value from next page with cursor parameter', function (): void {
            // Arrange
            $connector = new PaginationTestConnector();
            $request = new TestRequest();
            $paginator = new TestPaginator();
            $response = Response::make([
                'data' => [['id' => 1]],
                'has_more' => true,
                'next_cursor' => 'abc123',
            ], 200);

            // Act
            $paginatedResponse = new PaginatedResponse($connector, $request, $paginator, $response);
            $cursor = $paginatedResponse->nextCursor();

            // Assert
            expect($cursor)->toBe('abc123');
        });

        test('returns cursor value from next page with after parameter', function (): void {
            // Arrange
            $connector = new PaginationTestConnector();
            $request = new TestRequest();
            $paginator = new AfterPaginator();
            $response = Response::make([
                'items' => [['id' => 1]],
                'pagination' => [
                    'has_next' => true,
                    'after' => 'xyz789',
                ],
            ], 200);

            // Act
            $paginatedResponse = new PaginatedResponse($connector, $request, $paginator, $response);
            $cursor = $paginatedResponse->nextCursor();

            // Assert
            expect($cursor)->toBe('xyz789');
        });

        test('returns null cursor when no more pages exist', function (): void {
            // Arrange
            $connector = new PaginationTestConnector();
            $request = new TestRequest();
            $paginator = new TestPaginator();
            $response = Response::make([
                'data' => [['id' => 1]],
                'has_more' => false,
            ], 200);

            // Act
            $paginatedResponse = new PaginatedResponse($connector, $request, $paginator, $response);
            $cursor = $paginatedResponse->nextCursor();

            // Assert
            expect($cursor)->toBeNull();
        });

        test('returns true when more pages exist', function (): void {
            // Arrange
            $connector = new PaginationTestConnector();
            $request = new TestRequest();
            $paginator = new TestPaginator();
            $response = Response::make([
                'data' => [['id' => 1]],
                'has_more' => true,
                'next_cursor' => 'abc',
            ], 200);

            // Act
            $paginatedResponse = new PaginatedResponse($connector, $request, $paginator, $response);

            // Assert
            expect($paginatedResponse->hasMore())->toBeTrue();
        });

        test('returns false when no more pages exist', function (): void {
            // Arrange
            $connector = new PaginationTestConnector();
            $request = new TestRequest();
            $paginator = new TestPaginator();
            $response = Response::make([
                'data' => [['id' => 1]],
                'has_more' => false,
            ], 200);

            // Act
            $paginatedResponse = new PaginatedResponse($connector, $request, $paginator, $response);

            // Assert
            expect($paginatedResponse->hasMore())->toBeFalse();
        });

        test('limits pages fetched with take method', function (): void {
            // Arrange
            $connector = new PaginationTestConnector();
            $connector->addResponses([
                Response::make([
                    'data' => [['id' => 3], ['id' => 4]],
                    'has_more' => true,
                    'next_cursor' => 'page3',
                ], 200),
                Response::make([
                    'data' => [['id' => 5], ['id' => 6]],
                    'has_more' => true,
                    'next_cursor' => 'page4',
                ], 200),
            ]);

            $request = new TestRequest();
            $paginator = new TestPaginator();
            $initialResponse = Response::make([
                'data' => [['id' => 1], ['id' => 2]],
                'has_more' => true,
                'next_cursor' => 'page2',
            ], 200);

            // Act
            $paginatedResponse = new PaginatedResponse($connector, $request, $paginator, $initialResponse);
            $items = $paginatedResponse->take(2)->collect();

            // Assert
            expect($items)->toHaveCount(4)
                ->and($items[0])->toBe(['id' => 1])
                ->and($items[1])->toBe(['id' => 2])
                ->and($items[2])->toBe(['id' => 3])
                ->and($items[3])->toBe(['id' => 4]);
        });

        test('collects all items from multiple pages as Collection', function (): void {
            // Arrange
            $connector = new PaginationTestConnector();
            $connector->addResponses([
                Response::make([
                    'data' => [['id' => 3], ['id' => 4]],
                    'has_more' => true,
                    'next_cursor' => 'page3',
                ], 200),
                Response::make([
                    'data' => [['id' => 5], ['id' => 6]],
                    'has_more' => false,
                ], 200),
            ]);

            $request = new TestRequest();
            $paginator = new TestPaginator();
            $initialResponse = Response::make([
                'data' => [['id' => 1], ['id' => 2]],
                'has_more' => true,
                'next_cursor' => 'page2',
            ], 200);

            // Act
            $paginatedResponse = new PaginatedResponse($connector, $request, $paginator, $initialResponse);
            $collection = $paginatedResponse->collect();

            // Assert
            expect($collection)->toBeInstanceOf(Collection::class)
                ->and($collection)->toHaveCount(6)
                ->and($collection->first())->toBe(['id' => 1])
                ->and($collection->last())->toBe(['id' => 6]);
        });

        test('returns self from first method', function (): void {
            // Arrange
            $connector = new PaginationTestConnector();
            $request = new TestRequest();
            $paginator = new TestPaginator();
            $response = Response::make([
                'data' => [['id' => 1]],
                'has_more' => false,
            ], 200);

            // Act
            $paginatedResponse = new PaginatedResponse($connector, $request, $paginator, $response);
            $first = $paginatedResponse->first();

            // Assert
            expect($first)->toBe($paginatedResponse);
        });

        test('returns LazyCollection from lazy method', function (): void {
            // Arrange
            $connector = new PaginationTestConnector();
            $connector->addResponse(
                Response::make([
                    'data' => [['id' => 3]],
                    'has_more' => false,
                ], 200),
            );

            $request = new TestRequest();
            $paginator = new TestPaginator();
            $initialResponse = Response::make([
                'data' => [['id' => 1], ['id' => 2]],
                'has_more' => true,
                'next_cursor' => 'page2',
            ], 200);

            // Act
            $paginatedResponse = new PaginatedResponse($connector, $request, $paginator, $initialResponse);
            $lazy = $paginatedResponse->lazy();

            // Assert
            expect($lazy)->toBeInstanceOf(LazyCollection::class);
            $items = $lazy->all();
            expect($items)->toHaveCount(3);
        });

        test('converts to Laravel LengthAwarePaginator with total from meta', function (): void {
            // Arrange
            $connector = new PaginationTestConnector();
            $request = new TestRequest();
            $paginator = new TestPaginator();
            $response = Response::make([
                'data' => [
                    ['id' => 1],
                    ['id' => 2],
                ],
                'meta' => [
                    'total' => 50,
                    'current_page' => 2,
                ],
                'has_more' => true,
            ], 200);

            // Act
            $paginatedResponse = new PaginatedResponse($connector, $request, $paginator, $response);
            $laravel = $paginatedResponse->toLaravelPaginator(perPage: 10);

            // Assert
            expect($laravel)->toBeInstanceOf(LengthAwarePaginator::class)
                ->and($laravel->total())->toBe(50)
                ->and($laravel->perPage())->toBe(10)
                ->and($laravel->currentPage())->toBe(2)
                ->and($laravel->count())->toBe(2);
        });

        test('converts to Laravel LengthAwarePaginator with custom parameters', function (): void {
            // Arrange
            $connector = new PaginationTestConnector();
            $request = new TestRequest();
            $paginator = new TestPaginator();
            $response = Response::make([
                'data' => [['id' => 1]],
                'has_more' => false,
            ], 200);

            // Act
            $paginatedResponse = new PaginatedResponse($connector, $request, $paginator, $response);
            $laravel = $paginatedResponse->toLaravelPaginator(
                perPage: 20,
                pageName: 'p',
                page: 3,
                options: ['path' => '/custom'],
            );

            // Assert
            expect($laravel)->toBeInstanceOf(LengthAwarePaginator::class)
                ->and($laravel->perPage())->toBe(20)
                ->and($laravel->currentPage())->toBe(3);
        });

        test('converts to Laravel LengthAwarePaginator with item count as total when meta missing', function (): void {
            // Arrange
            $connector = new PaginationTestConnector();
            $request = new TestRequest();
            $paginator = new TestPaginator();
            $response = Response::make([
                'data' => [
                    ['id' => 1],
                    ['id' => 2],
                    ['id' => 3],
                ],
                'has_more' => false,
            ], 200);

            // Act
            $paginatedResponse = new PaginatedResponse($connector, $request, $paginator, $response);
            $laravel = $paginatedResponse->toLaravelPaginator();

            // Assert
            expect($laravel->total())->toBe(3);
        });

        test('converts to Laravel simple Paginator without total', function (): void {
            // Arrange
            $connector = new PaginationTestConnector();
            $request = new TestRequest();
            $paginator = new TestPaginator();
            $response = Response::make([
                'data' => [
                    ['id' => 1],
                    ['id' => 2],
                ],
                'meta' => [
                    'current_page' => 3,
                ],
                'has_more' => true,
            ], 200);

            // Act
            $paginatedResponse = new PaginatedResponse($connector, $request, $paginator, $response);
            $laravel = $paginatedResponse->toLaravelSimplePaginator(perPage: 5);

            // Assert
            expect($laravel)->toBeInstanceOf(LaravelPaginator::class)
                ->and($laravel->perPage())->toBe(5)
                ->and($laravel->currentPage())->toBe(3)
                ->and($laravel->count())->toBe(2);
        });

        test('converts to Laravel simple Paginator with custom parameters', function (): void {
            // Arrange
            $connector = new PaginationTestConnector();
            $request = new TestRequest();
            $paginator = new TestPaginator();
            $response = Response::make([
                'data' => [['id' => 1]],
                'has_more' => false,
            ], 200);

            // Act
            $paginatedResponse = new PaginatedResponse($connector, $request, $paginator, $response);
            $laravel = $paginatedResponse->toLaravelSimplePaginator(
                perPage: 25,
                pageName: 'page',
                page: 5,
                options: ['path' => '/test'],
            );

            // Assert
            expect($laravel)->toBeInstanceOf(LaravelPaginator::class)
                ->and($laravel->perPage())->toBe(25)
                ->and($laravel->currentPage())->toBe(5);
        });

        test('iterates over each item with callback function', function (): void {
            // Arrange
            $connector = new PaginationTestConnector();
            $connector->addResponse(
                Response::make([
                    'data' => [['id' => 3]],
                    'has_more' => false,
                ], 200),
            );

            $request = new TestRequest();
            $paginator = new TestPaginator();
            $initialResponse = Response::make([
                'data' => [['id' => 1], ['id' => 2]],
                'has_more' => true,
                'next_cursor' => 'page2',
            ], 200);

            $collected = [];

            // Act
            $paginatedResponse = new PaginatedResponse($connector, $request, $paginator, $initialResponse);
            $paginatedResponse->each(function ($item, $index) use (&$collected): void {
                $collected[] = ['item' => $item, 'index' => $index];
            });

            // Assert
            expect($collected)->toHaveCount(3)
                ->and($collected[0])->toBe(['item' => ['id' => 1], 'index' => 0])
                ->and($collected[1])->toBe(['item' => ['id' => 2], 'index' => 1])
                ->and($collected[2])->toBe(['item' => ['id' => 3], 'index' => 2]);
        });

        test('iterates through single page with getIterator', function (): void {
            // Arrange
            $connector = new PaginationTestConnector();
            $request = new TestRequest();
            $paginator = new TestPaginator();
            $response = Response::make([
                'data' => [
                    ['id' => 1, 'name' => 'First'],
                    ['id' => 2, 'name' => 'Second'],
                ],
                'has_more' => false,
            ], 200);

            // Act
            $paginatedResponse = new PaginatedResponse($connector, $request, $paginator, $response);
            $items = [];

            foreach ($paginatedResponse as $item) {
                $items[] = $item;
            }

            // Assert
            expect($items)->toHaveCount(2)
                ->and($items[0])->toBe(['id' => 1, 'name' => 'First'])
                ->and($items[1])->toBe(['id' => 2, 'name' => 'Second']);
        });

        test('iterates through multiple pages with getIterator', function (): void {
            // Arrange
            $connector = new PaginationTestConnector();
            $connector->addResponses([
                Response::make([
                    'data' => [['id' => 3], ['id' => 4]],
                    'has_more' => true,
                    'next_cursor' => 'page3',
                ], 200),
                Response::make([
                    'data' => [['id' => 5]],
                    'has_more' => false,
                ], 200),
            ]);

            $request = new TestRequest();
            $paginator = new TestPaginator();
            $initialResponse = Response::make([
                'data' => [['id' => 1], ['id' => 2]],
                'has_more' => true,
                'next_cursor' => 'page2',
            ], 200);

            // Act
            $paginatedResponse = new PaginatedResponse($connector, $request, $paginator, $initialResponse);
            $items = [];

            foreach ($paginatedResponse as $item) {
                $items[] = $item;
            }

            // Assert
            expect($items)->toHaveCount(5)
                ->and($items[0]['id'])->toBe(1)
                ->and($items[2]['id'])->toBe(3)
                ->and($items[4]['id'])->toBe(5);
        });

        test('sends requests with correct query parameters for next pages', function (): void {
            // Arrange
            $connector = new PaginationTestConnector();
            $connector->addResponses([
                Response::make([
                    'data' => [['id' => 3]],
                    'has_more' => true,
                    'next_page' => 3,
                ], 200),
                Response::make([
                    'data' => [['id' => 4]],
                    'has_more' => false,
                ], 200),
            ]);

            $request = new TestRequest();
            $paginator = new TestPaginator();
            $initialResponse = Response::make([
                'data' => [['id' => 1]],
                'has_more' => true,
                'next_page' => 2,
            ], 200);

            // Act
            $paginatedResponse = new PaginatedResponse($connector, $request, $paginator, $initialResponse);
            $items = iterator_to_array($paginatedResponse);

            // Assert
            $requests = $connector->sentRequests();
            expect($requests)->toHaveCount(2);

            // First subsequent request should have page=2
            $firstRequest = $requests[0];
            expect($firstRequest->allQuery())->toHaveKey('page')
                ->and($firstRequest->allQuery()['page'])->toBe(2);

            // Second subsequent request should have page=3
            $secondRequest = $requests[1];
            expect($secondRequest->allQuery()['page'])->toBe(3);
        });

        test('stops iteration when getNextPage returns null', function (): void {
            // Arrange
            $paginator = new class() implements Paginator
            {
                private int $callCount = 0;

                public function getItems(Response $response): array
                {
                    return $response->json('data') ?? [];
                }

                public function hasMorePages(Response $response): bool
                {
                    // Report more pages exist
                    return true;
                }

                public function getNextPage(Response $response): ?array
                {
                    // But return null on first call to test the break condition
                    ++$this->callCount;

                    return null;
                }
            };

            $connector = new PaginationTestConnector();
            $request = new TestRequest();
            $initialResponse = Response::make([
                'data' => [['id' => 1], ['id' => 2]],
            ], 200);

            // Act
            $paginatedResponse = new PaginatedResponse($connector, $request, $paginator, $initialResponse);
            $items = iterator_to_array($paginatedResponse);

            // Assert - should only have items from initial page
            expect($items)->toHaveCount(2);
            expect($connector->sentRequests())->toBeEmpty();
        });
    });

    describe('Edge Cases', function (): void {
        test('handles empty items array from response', function (): void {
            // Arrange
            $connector = new PaginationTestConnector();
            $request = new TestRequest();
            $paginator = new TestPaginator();
            $response = Response::make([
                'data' => [],
                'has_more' => false,
            ], 200);

            // Act
            $paginatedResponse = new PaginatedResponse($connector, $request, $paginator, $response);
            $items = $paginatedResponse->items();

            // Assert
            expect($items)->toBeArray()
                ->and($items)->toBeEmpty();
        });

        test('handles missing data key in response', function (): void {
            // Arrange
            $connector = new PaginationTestConnector();
            $request = new TestRequest();
            $paginator = new TestPaginator();
            $response = Response::make([
                'has_more' => false,
            ], 200);

            // Act
            $paginatedResponse = new PaginatedResponse($connector, $request, $paginator, $response);
            $items = $paginatedResponse->items();

            // Assert
            expect($items)->toBeArray()
                ->and($items)->toBeEmpty();
        });

        test('handles take with zero max pages by stopping immediately', function (): void {
            // Arrange
            $connector = new PaginationTestConnector();
            $request = new TestRequest();
            $paginator = new TestPaginator();
            $initialResponse = Response::make([
                'data' => [['id' => 1], ['id' => 2]],
                'has_more' => true,
                'next_cursor' => 'page2',
            ], 200);

            // Act
            $paginatedResponse = new PaginatedResponse($connector, $request, $paginator, $initialResponse);
            $items = $paginatedResponse->take(0)->collect();

            // Assert - should only get first page items, no additional requests
            expect($items)->toHaveCount(2);
            expect($connector->sentRequests())->toBeEmpty();
        });

        test('handles take limiting when exact page count matches', function (): void {
            // Arrange
            $connector = new PaginationTestConnector();
            $connector->addResponses([
                Response::make([
                    'data' => [['id' => 3]],
                    'has_more' => true,
                    'next_cursor' => 'page3',
                ], 200),
                Response::make([
                    'data' => [['id' => 4]],
                    'has_more' => false,
                ], 200),
            ]);

            $request = new TestRequest();
            $paginator = new TestPaginator();
            $initialResponse = Response::make([
                'data' => [['id' => 1]],
                'has_more' => true,
                'next_cursor' => 'page2',
            ], 200);

            // Act
            $paginatedResponse = new PaginatedResponse($connector, $request, $paginator, $initialResponse);
            $items = $paginatedResponse->take(3)->collect();

            // Assert
            expect($items)->toHaveCount(3);
            expect($connector->sentRequests())->toHaveCount(2);
        });

        test('collects items when single page has no more data', function (): void {
            // Arrange
            $connector = new PaginationTestConnector();
            $request = new TestRequest();
            $paginator = new TestPaginator();
            $initialResponse = Response::make([
                'data' => [['id' => 1], ['id' => 2]],
                'has_more' => false,
            ], 200);

            // Act
            $paginatedResponse = new PaginatedResponse($connector, $request, $paginator, $initialResponse);
            $collection = $paginatedResponse->collect();

            // Assert
            expect($collection)->toHaveCount(2);
            expect($connector->sentRequests())->toBeEmpty();
        });

        test('handles iteration when all pages are empty', function (): void {
            // Arrange
            $connector = new PaginationTestConnector();
            $connector->addResponse(
                Response::make([
                    'data' => [],
                    'has_more' => false,
                ], 200),
            );

            $request = new TestRequest();
            $paginator = new TestPaginator();
            $initialResponse = Response::make([
                'data' => [],
                'has_more' => true,
                'next_cursor' => 'page2',
            ], 200);

            // Act
            $paginatedResponse = new PaginatedResponse($connector, $request, $paginator, $initialResponse);
            $items = iterator_to_array($paginatedResponse);

            // Assert
            expect($items)->toBeEmpty();
        });

        test('handles each callback with empty result set', function (): void {
            // Arrange
            $connector = new PaginationTestConnector();
            $request = new TestRequest();
            $paginator = new TestPaginator();
            $response = Response::make([
                'data' => [],
                'has_more' => false,
            ], 200);

            $callbackExecuted = false;

            // Act
            $paginatedResponse = new PaginatedResponse($connector, $request, $paginator, $response);
            $paginatedResponse->each(function () use (&$callbackExecuted): void {
                $callbackExecuted = true;
            });

            // Assert
            expect($callbackExecuted)->toBeFalse();
        });

        test('handles lazy collection with single empty page', function (): void {
            // Arrange
            $connector = new PaginationTestConnector();
            $request = new TestRequest();
            $paginator = new TestPaginator();
            $response = Response::make([
                'data' => [],
                'has_more' => false,
            ], 200);

            // Act
            $paginatedResponse = new PaginatedResponse($connector, $request, $paginator, $response);
            $lazy = $paginatedResponse->lazy();

            // Assert
            expect($lazy->count())->toBe(0);
        });

        test('handles Laravel paginator with default current page when meta missing', function (): void {
            // Arrange
            $connector = new PaginationTestConnector();
            $request = new TestRequest();
            $paginator = new TestPaginator();
            $response = Response::make([
                'data' => [['id' => 1]],
                'has_more' => false,
            ], 200);

            // Act
            $paginatedResponse = new PaginatedResponse($connector, $request, $paginator, $response);
            $laravel = $paginatedResponse->toLaravelPaginator();

            // Assert
            expect($laravel->currentPage())->toBe(1);
        });

        test('handles simple paginator with default current page when meta missing', function (): void {
            // Arrange
            $connector = new PaginationTestConnector();
            $request = new TestRequest();
            $paginator = new TestPaginator();
            $response = Response::make([
                'data' => [['id' => 1]],
                'has_more' => false,
            ], 200);

            // Act
            $paginatedResponse = new PaginatedResponse($connector, $request, $paginator, $response);
            $laravel = $paginatedResponse->toLaravelSimplePaginator();

            // Assert
            expect($laravel->currentPage())->toBe(1);
        });

        test('stops iteration when paginator returns null for next page', function (): void {
            // Arrange
            $connector = new PaginationTestConnector();
            $request = new TestRequest();
            $paginator = new TestPaginator();
            $initialResponse = Response::make([
                'data' => [['id' => 1], ['id' => 2]],
                'has_more' => false, // Will cause getNextPage to return null
            ], 200);

            // Act
            $paginatedResponse = new PaginatedResponse($connector, $request, $paginator, $initialResponse);
            $items = iterator_to_array($paginatedResponse);

            // Assert
            expect($items)->toHaveCount(2);
            expect($connector->sentRequests())->toBeEmpty();
        });

        test('handles multiple iterations over same paginated response', function (): void {
            // Arrange
            $connector = new PaginationTestConnector();
            $connector->addResponses([
                Response::make([
                    'data' => [['id' => 3]],
                    'has_more' => false,
                ], 200),
                Response::make([
                    'data' => [['id' => 3]],
                    'has_more' => false,
                ], 200),
            ]);

            $request = new TestRequest();
            $paginator = new TestPaginator();
            $initialResponse = Response::make([
                'data' => [['id' => 1], ['id' => 2]],
                'has_more' => true,
                'next_cursor' => 'page2',
            ], 200);

            $paginatedResponse = new PaginatedResponse($connector, $request, $paginator, $initialResponse);

            // Act - iterate twice
            $firstIteration = iterator_to_array($paginatedResponse);
            $secondIteration = iterator_to_array($paginatedResponse);

            // Assert - both iterations should fetch pages
            expect($firstIteration)->toHaveCount(3)
                ->and($secondIteration)->toHaveCount(3)
                ->and($connector->sentRequests())->toHaveCount(2);
        });
    });
});

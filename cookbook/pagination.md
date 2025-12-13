# Pagination

Relay provides flexible pagination support for iterating through API results. It supports multiple pagination strategies out of the box and allows custom implementations.

## Overview

Pagination in Relay:
- Supports page-based, cursor-based, offset-based, and link header pagination
- Automatically fetches multiple pages when iterating
- Memory-efficient with lazy collections
- Configurable via attributes or programmatically
- Integrates with Laravel's pagination classes

## Pagination Strategies

### Page-Based Pagination

Traditional pagination using page numbers:

```php
use Cline\Relay\Support\Attributes\Methods\Get;
use Cline\Relay\Support\Attributes\Pagination\Pagination;
use Cline\Relay\Core\Request;

#[Get]
#[Pagination(
    page: 'page',           // Query parameter for page number
    perPage: 'per_page',    // Query parameter for items per page
    dataKey: 'data',        // Response key containing items
    totalPagesKey: 'meta.last_page',  // Response key for total pages
    totalKey: 'meta.total',           // Response key for total items
)]
class GetUsersRequest extends Request
{
    public function endpoint(): string
    {
        return '/users';
    }
}
```

### Cursor-Based Pagination

For APIs that use cursors (like Twitter, Stripe):

```php
use Cline\Relay\Support\Attributes\Pagination\CursorPagination;

#[Get]
#[CursorPagination(
    cursor: 'cursor',           // Query parameter for cursor
    perPage: 'per_page',        // Query parameter for items per page
    nextKey: 'meta.next_cursor', // Response key for next cursor
    dataKey: 'data',            // Response key containing items
)]
class GetTimelineRequest extends Request
{
    public function endpoint(): string
    {
        return '/timeline';
    }
}
```

### Offset-Based Pagination

For APIs that use offset/limit:

```php
use Cline\Relay\Support\Attributes\Pagination\OffsetPagination;

#[Get]
#[OffsetPagination(
    offset: 'offset',       // Query parameter for offset
    limit: 'limit',         // Query parameter for limit
    dataKey: 'data',        // Response key containing items
    totalKey: 'meta.total', // Response key for total items
)]
class SearchRequest extends Request
{
    public function endpoint(): string
    {
        return '/search';
    }
}
```

### Simple Pagination

For APIs that only indicate if more pages exist:

```php
use Cline\Relay\Support\Attributes\Pagination\SimplePagination;

#[Get]
#[SimplePagination(
    page: 'page',
    perPage: 'per_page',
    dataKey: 'data',
    hasMoreKey: 'meta.has_more',  // Boolean indicating more pages
)]
class GetPostsRequest extends Request
{
    public function endpoint(): string
    {
        return '/posts';
    }
}
```

### Link Header Pagination

For APIs following RFC 5988 (like GitHub):

```php
use Cline\Relay\Support\Attributes\Pagination\LinkPagination;

#[Get]
#[LinkPagination(dataKey: '')]  // Empty string if response is array itself
class GetRepositoriesRequest extends Request
{
    public function endpoint(): string
    {
        return '/repos';
    }
}
```

Link header format:
```
Link: <https://api.github.com/users?page=2>; rel="next", <https://api.github.com/users?page=5>; rel="last"
```

## Using Paginated Responses

### Fetching Paginated Data

```php
$connector = new GitHubConnector();

// Get paginated response
$paginated = $connector->paginate(new GetUsersRequest());

// Get items from first page
$users = $paginated->items();

// Check if more pages exist
if ($paginated->hasMore()) {
    // More pages available
}
```

### Iterating All Pages

```php
$paginated = $connector->paginate(new GetUsersRequest());

// Iterate through ALL items from ALL pages
foreach ($paginated as $user) {
    processUser($user);
}
```

### Limiting Pages

```php
// Only fetch first 5 pages
$paginated = $connector->paginate(new GetUsersRequest())
    ->take(5);

foreach ($paginated as $user) {
    // Maximum 5 pages of users
}
```

### Collecting All Items

```php
// Get all items as a Laravel Collection
$allUsers = $connector->paginate(new GetUsersRequest())
    ->collect();

// Now you can use collection methods
$activeUsers = $allUsers->where('active', true);
```

### Memory-Efficient Iteration

For large datasets, use lazy collections:

```php
$paginated = $connector->paginate(new GetUsersRequest());

// Lazy collection - one item in memory at a time
$paginated->lazy()
    ->filter(fn ($user) => $user['active'])
    ->each(function ($user) {
        processUser($user);
    });
```

### Using Each Callback

```php
$connector->paginate(new GetUsersRequest())
    ->each(function ($user, $index) {
        echo "Processing user #{$index}: {$user['name']}\n";
    });
```

## Converting to Laravel Paginators

### LengthAwarePaginator

```php
$paginated = $connector->paginate(new GetUsersRequest());

// Convert to Laravel's LengthAwarePaginator
$laravelPaginator = $paginated->toLaravelPaginator(
    perPage: 15,
    pageName: 'page',
    page: 1,
    options: ['path' => '/users'],
);

// Use in Blade views
return view('users.index', ['users' => $laravelPaginator]);
```

### SimplePaginator

```php
// For simple pagination without total count
$simplePaginator = $paginated->toLaravelSimplePaginator(
    perPage: 15,
    pageName: 'page',
);
```

## Custom Paginators

Implement the `Paginator` contract for custom pagination strategies:

```php
use Cline\Relay\Support\Contracts\Paginator;
use Cline\Relay\Core\Response;

class KeysetPaginator implements Paginator
{
    public function __construct(
        private readonly string $afterKey = 'after',
        private readonly string $dataKey = 'items',
    ) {}

    public function getNextPage(Response $response): ?array
    {
        $items = $this->getItems($response);

        if ($items === []) {
            return null;
        }

        // Use last item's ID as cursor
        $lastItem = end($items);

        return [
            $this->afterKey => $lastItem['id'] ?? null,
        ];
    }

    public function getItems(Response $response): array
    {
        $items = $response->json($this->dataKey);

        return is_array($items) ? $items : [];
    }

    public function hasMorePages(Response $response): bool
    {
        $items = $this->getItems($response);

        // If we got a full page, assume more pages exist
        return count($items) >= 20;
    }
}
```

### Using Custom Paginator

```php
$connector = new ApiConnector();
$paginator = new KeysetPaginator('after', 'results');

$paginated = $connector->paginateWith(
    new GetItemsRequest(),
    $paginator,
);
```

## Connector Pagination Methods

### paginate()

Standard pagination using request attributes:

```php
class MyConnector extends Connector
{
    public function getUsers(): PaginatedResponse
    {
        return $this->paginate(new GetUsersRequest());
    }
}
```

### paginateWith()

Pagination with a specific paginator:

```php
class MyConnector extends Connector
{
    public function searchItems(string $query): PaginatedResponse
    {
        return $this->paginateWith(
            new SearchRequest($query),
            new OffsetPaginator(
                new OffsetPagination(
                    offset: 'start',
                    limit: 'count',
                    dataKey: 'results',
                ),
                limit: 50,
            ),
        );
    }
}
```

## Pagination Configuration

### API Response Structures

Configure attributes to match your API's response format:

#### Laravel-Style Response

```json
{
    "data": [...],
    "meta": {
        "current_page": 1,
        "last_page": 10,
        "total": 100,
        "per_page": 10
    }
}
```

```php
#[Pagination(
    page: 'page',
    perPage: 'per_page',
    dataKey: 'data',
    totalPagesKey: 'meta.last_page',
    totalKey: 'meta.total',
)]
```

#### Stripe-Style Response

```json
{
    "data": [...],
    "has_more": true
}
```

```php
#[SimplePagination(
    page: 'page',
    perPage: 'limit',
    dataKey: 'data',
    hasMoreKey: 'has_more',
)]
```

#### Twitter-Style Cursor Response

```json
{
    "data": [...],
    "meta": {
        "next_token": "abc123"
    }
}
```

```php
#[CursorPagination(
    cursor: 'pagination_token',
    perPage: 'max_results',
    nextKey: 'meta.next_token',
    dataKey: 'data',
)]
```

#### Elasticsearch-Style Response

```json
{
    "hits": {
        "total": 1000,
        "hits": [...]
    }
}
```

```php
#[OffsetPagination(
    offset: 'from',
    limit: 'size',
    dataKey: 'hits.hits',
    totalKey: 'hits.total',
)]
```

## Best Practices

### 1. Use Appropriate Strategy

```php
// For stable, sortable data - use page or offset
#[Pagination(...)]
class GetUsersRequest extends Request {}

// For real-time feeds - use cursor
#[CursorPagination(...)]
class GetFeedRequest extends Request {}

// For GitHub-style APIs - use link headers
#[LinkPagination]
class GetReposRequest extends Request {}
```

### 2. Limit Pages for Large Datasets

```php
// Don't fetch unlimited pages
$paginated = $connector->paginate(new GetAllUsersRequest())
    ->take(100); // Maximum 100 pages

foreach ($paginated as $user) {
    // Process safely
}
```

### 3. Use Lazy Collections for Memory Efficiency

```php
// Good: Memory efficient
$connector->paginate(new GetUsersRequest())
    ->lazy()
    ->chunk(100)
    ->each(function ($chunk) {
        User::insert($chunk->toArray());
    });

// Bad: Loads all into memory
$allUsers = $connector->paginate(new GetUsersRequest())->collect();
```

### 4. Handle Empty Pages

```php
$paginated = $connector->paginate(new SearchRequest($query));

if ($paginated->items() === []) {
    // No results found
    return response()->json(['message' => 'No results']);
}
```

### 5. Cursor Pagination for Real-Time Data

```php
// For data that changes frequently, cursor pagination
// prevents missing or duplicate items

#[CursorPagination(
    cursor: 'since_id',
    nextKey: 'meta.next_cursor',
)]
class GetNotificationsRequest extends Request {}
```

## Full Example

Complete pagination setup:

```php
<?php

namespace App\Http\Connectors;

use Cline\Relay\Core\Connector;
use Cline\Relay\Features\Pagination\PaginatedResponse;

class ApiConnector extends Connector
{
    public function baseUrl(): string
    {
        return 'https://api.example.com/v1';
    }

    public function getUsers(): PaginatedResponse
    {
        return $this->paginate(new GetUsersRequest());
    }

    public function searchProducts(string $query): PaginatedResponse
    {
        return $this->paginate(new SearchProductsRequest($query));
    }
}
```

Requests with pagination:

```php
use Cline\Relay\Support\Attributes\Methods\Get;
use Cline\Relay\Support\Attributes\Pagination\Pagination;
use Cline\Relay\Support\Attributes\Pagination\CursorPagination;

// Page-based request
#[Get]
#[Pagination(
    page: 'page',
    perPage: 'limit',
    dataKey: 'users',
    totalKey: 'total_count',
)]
class GetUsersRequest extends Request
{
    public function endpoint(): string
    {
        return '/users';
    }
}

// Cursor-based request
#[Get]
#[CursorPagination(
    cursor: 'cursor',
    perPage: 'limit',
    nextKey: 'next_cursor',
    dataKey: 'products',
)]
class SearchProductsRequest extends Request
{
    public function __construct(
        private readonly string $query,
    ) {}

    public function endpoint(): string
    {
        return '/products/search';
    }

    public function query(): array
    {
        return ['q' => $this->query];
    }
}
```

Usage:

```php
$connector = new ApiConnector();

// Iterate all users efficiently
$connector->getUsers()
    ->lazy()
    ->filter(fn ($user) => $user['active'])
    ->each(function ($user) {
        syncUser($user);
    });

// Get specific number of pages
$products = $connector->searchProducts('laptop')
    ->take(5)
    ->collect();

// Convert for Blade view
$users = $connector->getUsers()->toLaravelPaginator(perPage: 20);

return view('users.index', compact('users'));
```

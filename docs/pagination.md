---
title: Pagination
description: Handle paginated API responses with multiple strategies in Relay
---

Relay provides flexible pagination support for iterating through API results with multiple strategies.

## Pagination Strategies

### Page-Based Pagination

```php
use Cline\Relay\Support\Attributes\Pagination\Pagination;

#[Get]
#[Pagination(
    page: 'page',
    perPage: 'per_page',
    dataKey: 'data',
    totalPagesKey: 'meta.last_page',
    totalKey: 'meta.total',
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

For APIs like Twitter, Stripe:

```php
use Cline\Relay\Support\Attributes\Pagination\CursorPagination;

#[Get]
#[CursorPagination(
    cursor: 'cursor',
    perPage: 'per_page',
    nextKey: 'meta.next_cursor',
    dataKey: 'data',
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

```php
use Cline\Relay\Support\Attributes\Pagination\OffsetPagination;

#[Get]
#[OffsetPagination(
    offset: 'offset',
    limit: 'limit',
    dataKey: 'data',
    totalKey: 'meta.total',
)]
class SearchRequest extends Request
{
    public function endpoint(): string
    {
        return '/search';
    }
}
```

### Link Header Pagination

For APIs following RFC 5988 (like GitHub):

```php
use Cline\Relay\Support\Attributes\Pagination\LinkPagination;

#[Get]
#[LinkPagination(dataKey: '')]
class GetRepositoriesRequest extends Request
{
    public function endpoint(): string
    {
        return '/repos';
    }
}
```

## Using Paginated Responses

### Fetching Paginated Data

```php
$connector = new GitHubConnector();
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
$allUsers = $connector->paginate(new GetUsersRequest())
    ->collect();

$activeUsers = $allUsers->where('active', true);
```

### Memory-Efficient Iteration

```php
$connector->paginate(new GetUsersRequest())
    ->lazy()
    ->filter(fn ($user) => $user['active'])
    ->each(function ($user) {
        processUser($user);
    });
```

## Converting to Laravel Paginators

### LengthAwarePaginator

```php
$laravelPaginator = $paginated->toLaravelPaginator(
    perPage: 15,
    pageName: 'page',
    page: 1,
);

return view('users.index', ['users' => $laravelPaginator]);
```

### SimplePaginator

```php
$simplePaginator = $paginated->toLaravelSimplePaginator(
    perPage: 15,
    pageName: 'page',
);
```

## Custom Paginators

Implement the `Paginator` contract for custom pagination strategies:

```php
use Cline\Relay\Support\Contracts\Paginator;

class KeysetPaginator implements Paginator
{
    public function getNextPage(Response $response): ?array
    {
        $items = $this->getItems($response);
        if ($items === []) {
            return null;
        }
        $lastItem = end($items);
        return ['after' => $lastItem['id'] ?? null];
    }

    public function getItems(Response $response): array
    {
        return $response->json('data') ?? [];
    }

    public function hasMorePages(Response $response): bool
    {
        return count($this->getItems($response)) >= 20;
    }
}

// Usage
$paginated = $connector->paginateWith(
    new GetItemsRequest(),
    new KeysetPaginator(),
);
```

## Best Practices

1. **Use appropriate strategy** - Page/offset for stable data, cursor for real-time feeds
2. **Limit pages for large datasets** - Use `->take(100)` to prevent runaway pagination
3. **Use lazy collections** - Memory efficient for large datasets
4. **Handle empty pages** - Check `$paginated->items() === []` for no results

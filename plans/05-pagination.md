# Pagination

## Via Attributes (Recommended)

Declare pagination strategy directly on the request:

```php
// Laravel-style page pagination (default: page, per_page)
#[Get, Json, Pagination]
class GetUsers extends Request
{
    public function endpoint(): string
    {
        return '/users';
    }
}

// Custom parameter names
#[Get, Json, Pagination(page: 'p', perPage: 'limit')]
class GetUsers extends Request { ... }

// Cursor-based (default: cursor, per_page, extracts from meta.next_cursor)
#[Get, Json, CursorPagination]
class GetUsers extends Request { ... }

// Custom cursor config
#[Get, Json, CursorPagination(
    cursor: 'after',
    perPage: 'limit',
    nextKey: 'pagination.next',
    dataKey: 'results',
)]
class GetUsers extends Request { ... }

// Simple pagination (Laravel's simplePaginate - no total count)
#[Get, Json, SimplePagination]
class GetUsers extends Request { ... }

// Offset-based
#[Get, Json, OffsetPagination(offset: 'skip', limit: 'take')]
class GetUsers extends Request { ... }

// Link header (GitHub/REST style)
#[Get, Json, LinkPagination]
class GetUsers extends Request { ... }
```

## Usage

```php
// Iterate through all pages automatically
foreach ($connector->paginate(new GetUsers()) as $user) {
    // Each $user is a single item from all pages
}

// Get paginated response with meta
$page = $connector->paginate(new GetUsers())->first();
$page->items();      // Collection of items
$page->nextCursor(); // For cursor pagination
$page->hasMore();    // bool

// Collect all into single collection
$allUsers = $connector->paginate(new GetUsers())->collect();

// Limit pages fetched
$connector->paginate(new GetUsers())->take(5); // Max 5 pages

// Lazy collection (memory efficient)
$connector->paginate(new GetUsers())->lazy();
```

## Laravel Integration

```php
// Returns LengthAwarePaginator for Blade/Inertia
$paginator = $connector->paginate(new GetUsers())->toLaravelPaginator();

// In controller
return view('users.index', [
    'users' => $stripe->customers()->paginate()->toLaravelPaginator(),
]);

// SimplePagination returns Paginator (no total)
$paginator = $connector->paginate(new GetUsers())->toLaravelSimplePaginator();
```

## Custom Paginator

For APIs with non-standard pagination:

```php
class WeirdApiPaginator implements Paginator
{
    public function getNextPage(Response $response): ?array
    {
        $meta = $response->json()['_metadata'];

        if (!$meta['has_more']) {
            return null;
        }

        return [
            'token' => $meta['continuation_token'],
            'batch' => $meta['batch_size'],
        ];
    }

    public function getItems(Response $response): array
    {
        return $response->json()['_embedded']['items'];
    }
}

// Use custom paginator
foreach ($connector->paginate($request, new WeirdApiPaginator()) as $item) {
    // ...
}
```

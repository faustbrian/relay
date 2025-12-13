# Getting Started

Welcome to Relay, a powerful PHP 8.4+ attribute-driven HTTP client for building elegant API SDKs. This guide will help you install, configure, and create your first API connector.

## Installation

Install Relay via Composer:

```bash
composer require cline/relay
```

## Requirements

- PHP 8.4+
- Laravel 11+ (optional, for Laravel integration)

## Laravel Integration

If using Laravel, Relay auto-registers its service provider. Optionally publish the configuration:

```bash
php artisan vendor:publish --tag=relay-config
```

## Quick Start with Generators

The fastest way to create an API integration is using the built-in generators:

```bash
# Create a complete GitHub integration with OAuth
php artisan make:integration GitHub --oauth --resources=Users,Repositories

# This creates:
# app/Http/Integrations/GitHub/
# ├── GitHubConnector.php
# ├── Requests/
# │   ├── ExampleRequest.php
# │   ├── GetUsersRequest.php
# │   ├── ListUserssRequest.php
# │   ├── CreateUsersRequest.php
# │   └── ...
# ├── Resources/
# │   ├── BaseResource.php
# │   ├── UsersResource.php
# │   └── RepositoriesResource.php
# └── Dto/
```

Or build piece by piece:

```bash
# Create connector
php artisan make:connector GitHub --bearer

# Create resource with CRUD requests
php artisan make:resource Users GitHub --crud --requests

# Create individual request
php artisan make:request GetRepository GitHub --method=get
```

See the **[Generators](generators.md)** cookbook for all available options.

## Core Concepts

Relay uses three main building blocks:

1. **Connector** - Represents an API service (e.g., GitHub, Stripe, Twilio)
2. **Request** - Represents a single API endpoint call
3. **Response** - Wraps the HTTP response with typed accessors

## Your First Connector

Create a connector for the JSONPlaceholder API:

```php
<?php

namespace App\Http\Connectors;

use Cline\Relay\Core\Connector;

class JsonPlaceholderConnector extends Connector
{
    public function baseUrl(): string
    {
        return 'https://jsonplaceholder.typicode.com';
    }
}
```

## Your First Request

Create a request to fetch posts:

```php
<?php

namespace App\Http\Requests;

use Cline\Relay\Support\Attributes\Methods\Get;
use Cline\Relay\Core\Request;

#[Get]
class GetPostsRequest extends Request
{
    public function endpoint(): string
    {
        return '/posts';
    }
}
```

## Sending a Request

```php
use App\Http\Connectors\JsonPlaceholderConnector;
use App\Http\Requests\GetPostsRequest;

$connector = new JsonPlaceholderConnector();
$response = $connector->send(new GetPostsRequest());

// Get the response as an array
$posts = $response->json();

// Get specific key
$firstPostTitle = $response->json('0.title');

// Get as collection
$posts = $response->collect();
```

## Requests with Parameters

Create a request that accepts parameters:

```php
<?php

namespace App\Http\Requests;

use Cline\Relay\Support\Attributes\Methods\Get;
use Cline\Relay\Core\Request;

#[Get]
class GetPostRequest extends Request
{
    public function __construct(
        private readonly int $postId,
    ) {}

    public function endpoint(): string
    {
        return "/posts/{$this->postId}";
    }
}
```

Usage:

```php
$response = $connector->send(new GetPostRequest(1));
$post = $response->json();
```

## POST Requests with Body

Create a request that sends data:

```php
<?php

namespace App\Http\Requests;

use Cline\Relay\Support\Attributes\ContentTypes\Json;
use Cline\Relay\Support\Attributes\Methods\Post;
use Cline\Relay\Core\Request;

#[Post]
#[Json]
class CreatePostRequest extends Request
{
    public function __construct(
        private readonly string $title,
        private readonly string $body,
        private readonly int $userId,
    ) {}

    public function endpoint(): string
    {
        return '/posts';
    }

    public function body(): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'userId' => $this->userId,
        ];
    }
}
```

Usage:

```php
$response = $connector->send(new CreatePostRequest(
    title: 'My First Post',
    body: 'This is the content of my post.',
    userId: 1,
));

$createdPost = $response->json();
```

## Query Parameters

Add query parameters to requests:

```php
<?php

namespace App\Http\Requests;

use Cline\Relay\Support\Attributes\Methods\Get;
use Cline\Relay\Core\Request;

#[Get]
class GetPostsRequest extends Request
{
    public function __construct(
        private readonly ?int $userId = null,
        private readonly int $limit = 10,
    ) {}

    public function endpoint(): string
    {
        return '/posts';
    }

    public function query(): array
    {
        return array_filter([
            '_limit' => $this->limit,
            'userId' => $this->userId,
        ]);
    }
}
```

Usage:

```php
// Fetch posts by user
$response = $connector->send(new GetPostsRequest(userId: 1));

// Fetch with pagination
$response = $connector->send(new GetPostsRequest(limit: 5));
```

## Response Handling

Relay provides rich response handling:

```php
$response = $connector->send(new GetPostsRequest());

// Check status
if ($response->ok()) {
    // 2xx response
}

if ($response->failed()) {
    // 4xx or 5xx response
}

// Get data in different formats
$array = $response->json();           // As array
$object = $response->object();        // As stdClass
$collection = $response->collect();   // As Laravel Collection

// Get specific keys with dot notation
$title = $response->json('data.title');

// Get headers
$contentType = $response->header('Content-Type');
$allHeaders = $response->headers();

// Get raw body
$rawBody = $response->body();

// Get status code
$status = $response->status();
```

## Error Handling

Handle errors gracefully:

```php
use Cline\Relay\Support\Exceptions\RequestException;

try {
    $response = $connector->send(new GetPostRequest(9999));
} catch (RequestException $e) {
    echo "Request failed: " . $e->getMessage();
    echo "Status: " . $e->status();
    echo "Response: " . $e->response()?->body();
}
```

Or throw on errors explicitly:

```php
$response = $connector->send(new GetPostRequest(1));
$response->throw(); // Throws if response is 4xx or 5xx
```

## Convenience Methods

Use connector convenience methods for simple requests:

```php
// GET request
$response = $connector->get('/posts', ['_limit' => 5]);

// POST request
$response = $connector->post('/posts', [
    'title' => 'New Post',
    'body' => 'Content here',
    'userId' => 1,
]);

// PUT request
$response = $connector->put('/posts/1', [
    'title' => 'Updated Title',
]);

// PATCH request
$response = $connector->patch('/posts/1', [
    'title' => 'Patched Title',
]);

// DELETE request
$response = $connector->delete('/posts/1');
```

## Adding Authentication

Configure authentication in your connector:

```php
<?php

namespace App\Http\Connectors;

use Cline\Relay\Features\Auth\BearerToken;
use Cline\Relay\Core\Connector;
use Cline\Relay\Core\Request;

class GitHubConnector extends Connector
{
    public function __construct(
        private readonly string $token,
    ) {}

    public function baseUrl(): string
    {
        return 'https://api.github.com';
    }

    public function authenticate(Request $request): Request
    {
        return (new BearerToken($this->token))->authenticate($request);
    }

    public function defaultHeaders(): array
    {
        return [
            'Accept' => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
        ];
    }
}
```

## Mapping to DTOs

Map responses to Data Transfer Objects:

```php
<?php

namespace App\DTOs;

class Post
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $body,
        public readonly int $userId,
    ) {}
}
```

```php
// Map single response to DTO
$post = $response->dto(Post::class);

// Map collection to DTOs
$posts = $response->dtoCollection(Post::class);

// Map nested data
$posts = $response->dtoCollection(Post::class, 'data.posts');
```

## Next Steps

Now that you've created your first connector, explore more advanced features:

- **[Generators](generators.md)** - Scaffold integrations quickly
- **[Connectors](connectors.md)** - Learn about connector configuration
- **[Requests](requests.md)** - Deep dive into request options
- **[Responses](responses.md)** - Response handling and transformation
- **[Authentication](authentication.md)** - Authentication strategies
- **[Attributes](attributes.md)** - All available attributes
- **[Middleware](middleware.md)** - Request/response pipeline
- **[Caching](caching.md)** - Cache responses
- **[Rate Limiting](rate-limiting.md)** - Handle API rate limits
- **[Resilience](resilience.md)** - Retries and circuit breakers
- **[Pagination](pagination.md)** - Paginated API responses
- **[Pooling](pooling.md)** - Concurrent requests
- **[Testing](testing.md)** - Mock connectors for testing
- **[Debugging](debugging.md)** - Debug tools and dumpers

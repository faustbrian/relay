---
title: Getting Started
description: Install and configure Relay, a modern attribute-driven HTTP client for PHP
---

Relay is a powerful PHP 8.4+ attribute-driven HTTP client for building elegant API SDKs. This guide covers installation, configuration, and creating your first API connector.

## Installation

Install via Composer:

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

The fastest way to create an API integration:

```bash
# Create a complete GitHub integration with OAuth
php artisan make:integration GitHub --oauth --resources=Users,Repositories

# Or build piece by piece
php artisan make:connector GitHub --bearer
php artisan make:resource Users GitHub --crud --requests
php artisan make:request GetRepository GitHub --method=get
```

See **[Generators](generators)** for all available options.

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

## Query Parameters

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

## Response Handling

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

// Get status code
$status = $response->status();
```

## Error Handling

```php
use Cline\Relay\Support\Exceptions\RequestException;

try {
    $response = $connector->send(new GetPostRequest(9999));
} catch (RequestException $e) {
    echo "Request failed: " . $e->getMessage();
    echo "Status: " . $e->status();
    echo "Response: " . $e->response()?->body();
}

// Or throw on errors explicitly
$response = $connector->send(new GetPostRequest(1));
$response->throw(); // Throws if response is 4xx or 5xx
```

## Convenience Methods

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
$response = $connector->put('/posts/1', ['title' => 'Updated Title']);

// PATCH request
$response = $connector->patch('/posts/1', ['title' => 'Patched Title']);

// DELETE request
$response = $connector->delete('/posts/1');
```

## Adding Authentication

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

- **[Generators](generators)** - Scaffold integrations quickly
- **[Connectors](connectors)** - Connector configuration
- **[Requests](requests)** - Request options
- **[Responses](responses)** - Response handling and transformation
- **[Authentication](authentication)** - Authentication strategies
- **[Attributes](attributes)** - All available attributes
- **[Middleware](middleware)** - Request/response pipeline
- **[Caching](caching)** - Cache responses
- **[Rate Limiting](rate-limiting)** - Handle API rate limits
- **[Resilience](resilience)** - Retries and circuit breakers
- **[Pagination](pagination)** - Paginated API responses
- **[Pooling](pooling)** - Concurrent requests
- **[Testing](testing)** - Mock connectors for testing
- **[Debugging](debugging)** - Debug tools and dumpers
- **[Advanced Usage](advanced-usage)** - DTOs, GraphQL, streaming, and more

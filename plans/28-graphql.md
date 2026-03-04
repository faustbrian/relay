# GraphQL Support

> **Status: Draft** - This feature is planned but not yet implemented.

First-class GraphQL support with query building, fragments, and subscriptions.

## Basic Query

```php
#[GraphQL]
class GetUser extends GraphQLRequest
{
    public function __construct(
        private readonly string $id,
    ) {}

    public function query(): string
    {
        return <<<'GRAPHQL'
            query GetUser($id: ID!) {
                user(id: $id) {
                    id
                    name
                    email
                    createdAt
                }
            }
        GRAPHQL;
    }

    public function variables(): array
    {
        return ['id' => $this->id];
    }
}
```

## Mutations

```php
#[GraphQL]
class CreateUser extends GraphQLRequest
{
    public function __construct(
        private readonly string $name,
        private readonly string $email,
    ) {}

    public function query(): string
    {
        return <<<'GRAPHQL'
            mutation CreateUser($input: CreateUserInput!) {
                createUser(input: $input) {
                    id
                    name
                    email
                }
            }
        GRAPHQL;
    }

    public function variables(): array
    {
        return [
            'input' => [
                'name' => $this->name,
                'email' => $this->email,
            ],
        ];
    }
}
```

## Fragments

```php
class UserFragment
{
    public static function basic(): string
    {
        return <<<'GRAPHQL'
            fragment UserBasic on User {
                id
                name
                email
            }
        GRAPHQL;
    }

    public static function full(): string
    {
        return <<<'GRAPHQL'
            fragment UserFull on User {
                ...UserBasic
                createdAt
                updatedAt
                posts {
                    id
                    title
                }
            }
        GRAPHQL;
    }
}

#[GraphQL, Fragments([UserFragment::class])]
class GetUsers extends GraphQLRequest
{
    public function query(): string
    {
        return <<<'GRAPHQL'
            query GetUsers {
                users {
                    ...UserBasic
                }
            }
        GRAPHQL;
    }
}
```

## Query Builder

```php
$query = GraphQL::query('GetUser')
    ->variable('id', 'ID!')
    ->select('user', ['id' => '$id'], [
        'id',
        'name',
        'email',
        'posts' => [
            'id',
            'title',
        ],
    ])
    ->build();

$response = $connector->graphql($query, ['id' => '123']);
```

## Connector Setup

```php
class GitHubGraphQLConnector extends Connector
{
    public function baseUrl(): string
    {
        return 'https://api.github.com/graphql';
    }

    public function authenticate(Request $request): void
    {
        $request->withBearerToken($this->token);
    }
}

// Usage
$connector = new GitHubGraphQLConnector($token);
$response = $connector->send(new GetViewer());

$response->data();   // ['viewer' => [...]]
$response->errors(); // null or array of errors
```

## Error Handling

```php
$response = $connector->send(new GetUser('invalid'));

if ($response->hasErrors()) {
    foreach ($response->errors() as $error) {
        echo $error['message'];
        echo $error['path'];
        echo $error['extensions']['code'];
    }
}

// Or throw on errors
#[GraphQL, ThrowOnGraphQLError]
class GetUser extends GraphQLRequest { ... }
```

## Batched Queries

```php
$response = $connector->graphqlBatch([
    new GetUser('1'),
    new GetUser('2'),
    new GetPosts(limit: 10),
]);

// Returns array of responses
[$user1, $user2, $posts] = $response;
```

## Subscriptions (WebSocket)

```php
#[GraphQL]
class OnPostCreated extends GraphQLSubscription
{
    public function query(): string
    {
        return <<<'GRAPHQL'
            subscription OnPostCreated {
                postCreated {
                    id
                    title
                    author {
                        name
                    }
                }
            }
        GRAPHQL;
    }
}

// Usage
$connector->subscribe(new OnPostCreated(), function (array $data) {
    echo "New post: {$data['postCreated']['title']}";
});
```

## Persisted Queries

```php
#[GraphQL, PersistedQuery(id: 'abc123')]
class GetUser extends GraphQLRequest
{
    // Query is stored server-side, only ID sent
    public function variables(): array
    {
        return ['id' => $this->id];
    }
}
```

## Automatic Persisted Queries (APQ)

```php
class GraphQLConnector extends Connector
{
    public function graphql(): GraphQLConfig
    {
        return new GraphQLConfig(
            // Enable APQ
            automaticPersistedQueries: true,

            // Hash algorithm for query
            hashAlgorithm: 'sha256',
        );
    }
}

// First request sends hash only
// If server doesn't have it, retry with full query
// Subsequent requests use hash only
```

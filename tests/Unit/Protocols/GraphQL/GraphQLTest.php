<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Relay\Protocols\GraphQL\GraphQLRequest;
use Cline\Relay\Protocols\GraphQL\GraphQLResponse;
use Cline\Relay\Support\Attributes\ContentTypes\Json;
use Cline\Relay\Support\Attributes\Methods\Post;
use Cline\Relay\Testing\MockResponse;

function createGraphQLRequest(): GraphQLRequest
{
    return new #[Post(), Json()] class extends GraphQLRequest
    {
        public function graphqlQuery(): string
        {
            return '{ users { id name } }';
        }
    };
}

function createGraphQLRequestWithVariables(): GraphQLRequest
{
    return new #[Post(), Json()] class extends GraphQLRequest
    {
        public function graphqlQuery(): string
        {
            return 'query GetUser($id: ID!) { user(id: $id) { id name } }';
        }

        public function variables(): array
        {
            return ['id' => '123'];
        }

        public function operationName(): string
        {
            return 'GetUser';
        }
    };
}

describe('GraphQLRequest', function (): void {
    it('uses POST method', function (): void {
        $request = createGraphQLRequest();

        expect($request->method())->toBe('POST');
    });

    it('uses JSON content type', function (): void {
        $request = createGraphQLRequest();

        expect($request->contentType())->toBe('application/json');
    });

    it('uses default /graphql endpoint', function (): void {
        $request = createGraphQLRequest();

        expect($request->endpoint())->toBe('/graphql');
    });

    it('builds body with query', function (): void {
        $request = createGraphQLRequest();

        $body = $request->body();

        expect($body)->toHaveKey('query');
        expect($body['query'])->toBe('{ users { id name } }');
        expect($body)->not->toHaveKey('variables');
        expect($body)->not->toHaveKey('operationName');
    });

    it('includes variables in body', function (): void {
        $request = createGraphQLRequestWithVariables();

        $body = $request->body();

        expect($body['variables'])->toBe(['id' => '123']);
    });

    it('includes operation name in body', function (): void {
        $request = createGraphQLRequestWithVariables();

        $body = $request->body();

        expect($body['operationName'])->toBe('GetUser');
    });

    it('allows custom endpoint', function (): void {
        $request = createGraphQLRequest()->withEndpoint('/api/graphql');

        expect($request->endpoint())->toBe('/api/graphql');
    });
});

describe('GraphQLResponse', function (): void {
    it('extracts data', function (): void {
        $response = MockResponse::json([
            'data' => [
                'users' => [
                    ['id' => 1, 'name' => 'John'],
                    ['id' => 2, 'name' => 'Jane'],
                ],
            ],
        ]);

        $graphqlResponse = new GraphQLResponse($response);

        expect($graphqlResponse->data('users'))->toHaveCount(2);
        expect($graphqlResponse->data('users.0.name'))->toBe('John');
    });

    it('returns all data when no key specified', function (): void {
        $response = MockResponse::json([
            'data' => [
                'users' => [
                    ['id' => 1, 'name' => 'John'],
                ],
                'posts' => [
                    ['id' => 100, 'title' => 'Test'],
                ],
            ],
        ]);

        $graphqlResponse = new GraphQLResponse($response);

        $allData = $graphqlResponse->data();

        expect($allData)->toBeArray();
        expect($allData)->toHaveKey('users');
        expect($allData)->toHaveKey('posts');
        expect($allData['users'])->toHaveCount(1);
        expect($allData['posts'])->toHaveCount(1);
    });

    it('extracts errors', function (): void {
        $response = MockResponse::json([
            'errors' => [
                ['message' => 'Field not found', 'path' => ['user', 'unknown']],
                ['message' => 'Invalid argument'],
            ],
        ]);

        $graphqlResponse = new GraphQLResponse($response);

        expect($graphqlResponse->hasErrors())->toBeTrue();
        expect($graphqlResponse->errors())->toHaveCount(2);
        expect($graphqlResponse->firstError())->toBe('Field not found');
        expect($graphqlResponse->errorMessages())->toBe([
            'Field not found',
            'Invalid argument',
        ]);
    });

    it('handles successful response', function (): void {
        $response = MockResponse::json([
            'data' => ['user' => ['id' => 1]],
        ]);

        $graphqlResponse = new GraphQLResponse($response);

        expect($graphqlResponse->successful())->toBeTrue();
        expect($graphqlResponse->failed())->toBeFalse();
        expect($graphqlResponse->hasErrors())->toBeFalse();
    });

    it('handles failed response with errors', function (): void {
        $response = MockResponse::json([
            'data' => null,
            'errors' => [['message' => 'Unauthorized']],
        ]);

        $graphqlResponse = new GraphQLResponse($response);

        expect($graphqlResponse->successful())->toBeFalse();
        expect($graphqlResponse->failed())->toBeTrue();
    });

    it('extracts extensions', function (): void {
        $response = MockResponse::json([
            'data' => ['user' => ['id' => 1]],
            'extensions' => [
                'complexity' => 5,
                'cost' => 10,
            ],
        ]);

        $graphqlResponse = new GraphQLResponse($response);

        expect($graphqlResponse->extensions())->toBe([
            'complexity' => 5,
            'cost' => 10,
        ]);
    });

    it('returns status code', function (): void {
        $response = MockResponse::json(['data' => null], 401);

        $graphqlResponse = new GraphQLResponse($response);

        expect($graphqlResponse->status())->toBe(401);
    });

    it('converts to array', function (): void {
        $response = MockResponse::json([
            'data' => ['user' => ['id' => 1]],
        ]);

        $graphqlResponse = new GraphQLResponse($response);

        expect($graphqlResponse->toArray())->toBe([
            'data' => ['user' => ['id' => 1]],
        ]);
    });

    it('returns underlying response', function (): void {
        $response = MockResponse::json(['data' => []]);

        $graphqlResponse = new GraphQLResponse($response);

        expect($graphqlResponse->response())->toBe($response);
    });
});

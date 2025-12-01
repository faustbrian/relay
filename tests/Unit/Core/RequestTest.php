<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Relay\Core\Connector;
use Cline\Relay\Core\Request;
use Cline\Relay\Core\Resource;
use Cline\Relay\Core\Response;
use Cline\Relay\Support\Attributes\ContentTypes\Json;
use Cline\Relay\Support\Attributes\Idempotent;
use Cline\Relay\Support\Attributes\Methods\Get;
use Cline\Relay\Support\Attributes\Methods\Post;
use Cline\Relay\Support\Attributes\ThrowOnError;
use Cline\Relay\Support\Exceptions\AttributeConflictException;
use Cline\Relay\Support\Exceptions\MissingAttributeException;
use Tests\Fixtures\CreateUserRequest;
use Tests\Fixtures\GetUserRequest;
use Tests\Fixtures\JsonConnector;
use Tests\Fixtures\MultipleContentTypesRequest;
use Tests\Fixtures\MultipleHttpMethodsRequest;
use Tests\Fixtures\NoContentTypeRequest;
use Tests\Fixtures\PlainConnector;
use Tests\Fixtures\PlainResource;
use Tests\Fixtures\XmlResource;

describe('Request', function (): void {
    describe('endpoint()', function (): void {
        it('returns the endpoint', function (): void {
            $request = new GetUserRequest(1);

            expect($request->endpoint())->toBe('/users/1');
        });
    });

    describe('method()', function (): void {
        it('returns GET for #[Get] attribute', function (): void {
            $request = new GetUserRequest(1);

            expect($request->method())->toBe('GET');
        });

        it('returns POST for #[Post] attribute', function (): void {
            $request = new CreateUserRequest('John', 'john@example.com');

            expect($request->method())->toBe('POST');
        });

        it('throws when no HTTP method attribute is present', function (): void {
            $request = new class() extends Request
            {
                public function endpoint(): string
                {
                    return '/test';
                }
            };

            $request->method();
        })->throws(MissingAttributeException::class);
    });

    describe('contentType()', function (): void {
        it('returns application/json for #[Json] attribute', function (): void {
            $request = new GetUserRequest(1);

            expect($request->contentType())->toBe('application/json');
        });

        it('returns null when no content type attribute is present', function (): void {
            $request = new NoContentTypeRequest();

            expect($request->contentType())->toBeNull();
        });

        it('inherits content type from Connector when Request has none', function (): void {
            $connector = new JsonConnector();

            $request = new NoContentTypeRequest();
            $request->setConnector($connector);

            expect($request->contentType())->toBe('application/json');
        });

        it('inherits content type from Resource when Request has none', function (): void {
            $connector = new PlainConnector();
            $resource = new XmlResource($connector);

            $request = new NoContentTypeRequest();
            $request->setResource($resource);

            expect($request->contentType())->toBe('application/xml');
        });

        it('Request content type overrides Resource content type', function (): void {
            $connector = new PlainConnector();
            $resource = new XmlResource($connector);

            $request = new GetUserRequest(1); // Has #[Json]
            $request->setResource($resource);

            expect($request->contentType())->toBe('application/json');
        });

        it('Resource content type overrides Connector content type', function (): void {
            $connector = new JsonConnector();
            $resource = new XmlResource($connector);

            $request = new NoContentTypeRequest();
            $request->setResource($resource);

            expect($request->contentType())->toBe('application/xml');
        });

        it('falls back to Connector when Resource has no content type', function (): void {
            $connector = new JsonConnector();
            $resource = new PlainResource($connector);

            $request = new NoContentTypeRequest();
            $request->setResource($resource);

            expect($request->contentType())->toBe('application/json');
        });
    });

    describe('body()', function (): void {
        it('returns the request body', function (): void {
            $request = new CreateUserRequest('John', 'john@example.com');

            expect($request->body())->toBe([
                'name' => 'John',
                'email' => 'john@example.com',
            ]);
        });

        it('returns null by default', function (): void {
            $request = new GetUserRequest(1);

            expect($request->body())->toBeNull();
        });
    });

    describe('headers()', function (): void {
        it('returns null by default', function (): void {
            $request = new GetUserRequest(1);

            expect($request->headers())->toBeNull();
        });
    });

    describe('query()', function (): void {
        it('returns null by default', function (): void {
            $request = new GetUserRequest(1);

            expect($request->query())->toBeNull();
        });
    });

    describe('withHeader()', function (): void {
        it('adds a header to the request', function (): void {
            $request = new GetUserRequest(1);
            $modified = $request->withHeader('X-Custom', 'value');

            expect($modified->allHeaders())->toBe(['X-Custom' => 'value']);
            expect($request->allHeaders())->toBe([]);
        });
    });

    describe('withQuery()', function (): void {
        it('adds a query parameter to the request', function (): void {
            $request = new GetUserRequest(1);
            $modified = $request->withQuery('include', 'posts');

            expect($modified->allQuery())->toBe(['include' => 'posts']);
            expect($request->allQuery())->toBe([]);
        });
    });

    describe('withBearerToken()', function (): void {
        it('adds authorization header with bearer token', function (): void {
            $request = new GetUserRequest(1);
            $modified = $request->withBearerToken('my-token');

            expect($modified->allHeaders())->toBe([
                'Authorization' => 'Bearer my-token',
            ]);
        });
    });

    describe('withBasicAuth()', function (): void {
        it('adds authorization header with basic auth', function (): void {
            $request = new GetUserRequest(1);
            $modified = $request->withBasicAuth('user', 'pass');

            expect($modified->allHeaders())->toBe([
                'Authorization' => 'Basic '.base64_encode('user:pass'),
            ]);
        });
    });

    describe('clone()', function (): void {
        it('creates an independent copy', function (): void {
            $request = new GetUserRequest(1);
            $clone = $request->clone();

            expect($clone)->not->toBe($request);
            expect($clone->endpoint())->toBe($request->endpoint());
        });
    });

    describe('hasAttribute()', function (): void {
        it('returns true when attribute is present', function (): void {
            $request = new CreateUserRequest('John', 'john@example.com');

            expect($request->hasAttribute(ThrowOnError::class))->toBeTrue();
            expect($request->hasAttribute(Post::class))->toBeTrue();
            expect($request->hasAttribute(Json::class))->toBeTrue();
        });

        it('returns false when attribute is not present', function (): void {
            $request = new GetUserRequest(1);

            expect($request->hasAttribute(ThrowOnError::class))->toBeFalse();
        });
    });

    describe('getAttribute()', function (): void {
        it('returns the attribute instance when present', function (): void {
            $request = new CreateUserRequest('John', 'john@example.com');

            $attr = $request->getAttribute(ThrowOnError::class);

            expect($attr)->toBeInstanceOf(ThrowOnError::class);
            expect($attr->clientErrors)->toBeTrue();
            expect($attr->serverErrors)->toBeTrue();
        });

        it('returns null when attribute is not present', function (): void {
            $request = new GetUserRequest(1);

            expect($request->getAttribute(ThrowOnError::class))->toBeNull();
        });
    });

    describe('method() - edge cases', function (): void {
        it('throws AttributeConflictException when multiple HTTP method attributes are present', function (): void {
            $request = new MultipleHttpMethodsRequest();

            $request->method();
        })->throws(AttributeConflictException::class, 'has multiple HTTP method attributes');
    });

    describe('contentType() - edge cases', function (): void {
        it('throws AttributeConflictException when multiple content type attributes are present', function (): void {
            $request = new MultipleContentTypesRequest();

            $request->contentType();
        })->throws(AttributeConflictException::class, 'has multiple content type attributes');
    });

    describe('withHeaders()', function (): void {
        it('adds multiple headers to the request', function (): void {
            $request = new GetUserRequest(1);
            $modified = $request->withHeaders([
                'X-Custom' => 'value1',
                'X-Another' => 'value2',
            ]);

            expect($modified->allHeaders())->toBe([
                'X-Custom' => 'value1',
                'X-Another' => 'value2',
            ]);
            expect($request->allHeaders())->toBe([]);
        });
    });

    describe('withIdempotencyKey()', function (): void {
        it('sets the idempotency key for the request', function (): void {
            $request = new GetUserRequest(1);
            $modified = $request->withIdempotencyKey('my-custom-key');

            expect($modified->idempotencyKey())->toBe('my-custom-key');
            expect($request->idempotencyKey())->toBeNull();
        });
    });

    describe('isIdempotent()', function (): void {
        it('returns true when Idempotent attribute is present and enabled', function (): void {
            $request = new #[Get(), Idempotent()] class() extends Request
            {
                public function endpoint(): string
                {
                    return '/test';
                }
            };

            expect($request->isIdempotent())->toBeTrue();
        });

        it('returns false when Idempotent attribute is disabled', function (): void {
            $request = new #[Get(), Idempotent(enabled: false)] class() extends Request
            {
                public function endpoint(): string
                {
                    return '/test';
                }
            };

            expect($request->isIdempotent())->toBeFalse();
        });

        it('returns false when Idempotent attribute is not present', function (): void {
            $request = new GetUserRequest(1);

            expect($request->isIdempotent())->toBeFalse();
        });
    });

    describe('idempotencyHeader()', function (): void {
        it('returns custom header name when specified in Idempotent attribute', function (): void {
            $request = new #[Get(), Idempotent(header: 'X-Custom-Idempotency')] class() extends Request
            {
                public function endpoint(): string
                {
                    return '/test';
                }
            };

            expect($request->idempotencyHeader())->toBe('X-Custom-Idempotency');
        });

        it('returns default header name when Idempotent attribute not present', function (): void {
            $request = new GetUserRequest(1);

            expect($request->idempotencyHeader())->toBe('Idempotency-Key');
        });
    });

    describe('initialize() - idempotency', function (): void {
        it('generates random idempotency key when request is idempotent', function (): void {
            $request = new #[Get(), Idempotent()] class() extends Request
            {
                public function endpoint(): string
                {
                    return '/test';
                }
            };

            $request->initialize();

            expect($request->idempotencyKey())->not->toBeNull();
            expect($request->allHeaders())->toHaveKey('Idempotency-Key');
        });

        it('uses existing idempotency key when already set', function (): void {
            $request = new #[Get(), Idempotent()] class() extends Request
            {
                public function endpoint(): string
                {
                    return '/test';
                }
            };

            $modified = $request->withIdempotencyKey('existing-key');
            $modified->initialize();

            expect($modified->idempotencyKey())->toBe('existing-key');
            expect($modified->allHeaders()['Idempotency-Key'])->toBe('existing-key');
        });

        it('uses custom key method when specified in Idempotent attribute', function (): void {
            $request = new #[Get(), Idempotent(keyMethod: 'customKey')] class() extends Request
            {
                public function endpoint(): string
                {
                    return '/test';
                }

                public function customKey(): string
                {
                    return 'custom-generated-key';
                }
            };

            $request->initialize();

            expect($request->idempotencyKey())->toBe('custom-generated-key');
            expect($request->allHeaders()['Idempotency-Key'])->toBe('custom-generated-key');
        });
    });

    describe('dump()', function (): void {
        it('returns the same request instance for method chaining', function (): void {
            $request = new CreateUserRequest('John', 'john@example.com');
            $modified = $request->withHeader('X-Test', 'value');

            // dump() should return the same instance for chaining
            $result = $modified->dump();

            expect($result)->toBe($modified);
        });
    });

    describe('transformResponse()', function (): void {
        it('returns the response unchanged by default', function (): void {
            $request = new GetUserRequest(1);
            $mockResponse = Response::make(['data' => 'test']);

            $result = $request->transformResponse($mockResponse);

            expect($result)->toBe($mockResponse);
        });
    });

    describe('resource and connector access', function (): void {
        it('returns null for resource when not set', function (): void {
            $request = new GetUserRequest(1);

            expect($request->resource())->toBeNull();
        });

        it('returns null for connector when neither set', function (): void {
            $request = new GetUserRequest(1);

            expect($request->connector())->toBeNull();
        });

        it('returns resource after setResource is called', function (): void {
            $connector = new class() extends Connector
            {
                public function baseUrl(): string
                {
                    return 'https://api.example.com';
                }
            };

            $resource = new class($connector) extends Resource {};

            $request = new GetUserRequest(1);
            $request->setResource($resource);

            expect($request->resource())->toBe($resource);
        });

        it('returns connector through resource when only resource is set', function (): void {
            $connector = new class() extends Connector
            {
                public function baseUrl(): string
                {
                    return 'https://api.example.com';
                }
            };

            $resource = new class($connector) extends Resource {};

            $request = new GetUserRequest(1);
            $request->setResource($resource);

            expect($request->connector())->toBe($connector);
        });

        it('returns directly set connector', function (): void {
            $connector = new class() extends Connector
            {
                public function baseUrl(): string
                {
                    return 'https://api.example.com';
                }
            };

            $request = new GetUserRequest(1);
            $request->setConnector($connector);

            expect($request->connector())->toBe($connector);
            expect($request->resource())->toBeNull();
        });

        it('prefers directly set connector over resource connector', function (): void {
            $connectorDirect = new class() extends Connector
            {
                public function baseUrl(): string
                {
                    return 'https://direct.example.com';
                }
            };

            $connectorFromResource = new class() extends Connector
            {
                public function baseUrl(): string
                {
                    return 'https://resource.example.com';
                }
            };

            $resource = new class($connectorFromResource) extends Resource {};

            $request = new GetUserRequest(1);
            $request->setResource($resource);
            $request->setConnector($connectorDirect);

            expect($request->connector())->toBe($connectorDirect);
            expect($request->connector()->baseUrl())->toBe('https://direct.example.com');
        });
    });
});

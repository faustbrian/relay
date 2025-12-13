<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;
use Cline\Relay\Support\Attributes\Methods\Get;
use Cline\Relay\Support\Attributes\Methods\Post;
use Cline\Relay\Support\Exceptions\MockClientException;
use Cline\Relay\Testing\Fixture;
use Cline\Relay\Testing\MockClient;
use Cline\Relay\Testing\MockResponse;

beforeEach(function (): void {
    MockClient::destroyGlobal();
});

afterEach(function (): void {
    MockClient::destroyGlobal();
});

function createMockClientTestRequest(string $endpoint = '/users', string $method = 'GET'): Request
{
    if ($method === 'POST') {
        return new #[Post()] class($endpoint) extends Request
        {
            public function __construct(
                private readonly string $ep,
            ) {}

            public function endpoint(): string
            {
                return $this->ep;
            }
        };
    }

    return new #[Get()] class($endpoint) extends Request
    {
        public function __construct(
            private readonly string $ep,
        ) {}

        public function endpoint(): string
        {
            return $this->ep;
        }
    };
}

describe('MockClient Global Instance', function (): void {
    it('creates global mock client', function (): void {
        $mockClient = MockClient::global([
            createMockClientTestRequest::class => MockResponse::json(['id' => 1]),
        ]);

        expect(MockClient::hasGlobal())->toBeTrue();
        expect(MockClient::getGlobal())->toBe($mockClient);
    });

    it('destroys global mock client', function (): void {
        MockClient::global([]);

        expect(MockClient::hasGlobal())->toBeTrue();

        MockClient::destroyGlobal();

        expect(MockClient::hasGlobal())->toBeFalse();
        expect(MockClient::getGlobal())->toBeNull();
    });

    it('returns null when no global instance exists', function (): void {
        expect(MockClient::getGlobal())->toBeNull();
        expect(MockClient::hasGlobal())->toBeFalse();
    });
});

describe('MockClient Response Mapping', function (): void {
    it('returns response for request class', function (): void {
        $request = createMockClientTestRequest('/users');
        $mockClient = new MockClient([
            $request::class => MockResponse::json(['id' => 1]),
        ]);

        $response = $mockClient->resolve($request, 'https://api.example.com');

        expect($response->json('id'))->toBe(1);
    });

    it('returns response for URL pattern with exact match', function (): void {
        $request = createMockClientTestRequest('/users');
        $mockClient = new MockClient([
            'https://api.example.com/users' => MockResponse::json(['matched' => 'exact']),
        ]);

        $response = $mockClient->resolve($request, 'https://api.example.com');

        expect($response->json('matched'))->toBe('exact');
    });

    it('returns response for URL pattern with wildcard', function (): void {
        $request = createMockClientTestRequest('/users/123/orders');
        $mockClient = new MockClient([
            'https://api.example.com/users/*/orders' => MockResponse::json(['matched' => 'wildcard']),
        ]);

        $response = $mockClient->resolve($request, 'https://api.example.com');

        expect($response->json('matched'))->toBe('wildcard');
    });

    it('returns response for URL pattern with double wildcard', function (): void {
        $request = createMockClientTestRequest('/api/v1/users/123');
        $mockClient = new MockClient([
            '*/api/v1/*' => MockResponse::json(['matched' => 'double-wildcard']),
        ]);

        $response = $mockClient->resolve($request, 'https://api.example.com');

        expect($response->json('matched'))->toBe('double-wildcard');
    });

    it('returns response for URL containing pattern', function (): void {
        $request = createMockClientTestRequest('/users/123');
        $mockClient = new MockClient([
            '/users/' => MockResponse::json(['matched' => 'contains']),
        ]);

        $response = $mockClient->resolve($request, 'https://api.example.com');

        expect($response->json('matched'))->toBe('contains');
    });

    it('uses closure to generate dynamic response', function (): void {
        $request = createMockClientTestRequest('/users/42');
        $mockClient = new MockClient([
            $request::class => fn (Request $r): Response => MockResponse::json([
                'endpoint' => $r->endpoint(),
            ]),
        ]);

        $response = $mockClient->resolve($request, 'https://api.example.com');

        expect($response->json('endpoint'))->toBe('/users/42');
    });
});

describe('MockClient Sequential Responses', function (): void {
    it('returns responses in sequence', function (): void {
        $mockClient = new MockClient([
            MockResponse::json(['seq' => 1]),
            MockResponse::json(['seq' => 2]),
            MockResponse::json(['seq' => 3]),
        ]);

        $request = createMockClientTestRequest('/users');

        $response1 = $mockClient->resolve($request, 'https://api.example.com');
        $response2 = $mockClient->resolve($request, 'https://api.example.com');
        $response3 = $mockClient->resolve($request, 'https://api.example.com');

        expect($response1->json('seq'))->toBe(1);
        expect($response2->json('seq'))->toBe(2);
        expect($response3->json('seq'))->toBe(3);
    });

    it('adds sequence responses with addSequenceResponse', function (): void {
        $mockClient = new MockClient();
        $mockClient->addSequenceResponse(MockResponse::json(['seq' => 1]));
        $mockClient->addSequenceResponse(MockResponse::json(['seq' => 2]));

        $request = createMockClientTestRequest('/users');

        $response1 = $mockClient->resolve($request, 'https://api.example.com');
        $response2 = $mockClient->resolve($request, 'https://api.example.com');

        expect($response1->json('seq'))->toBe(1);
        expect($response2->json('seq'))->toBe(2);
    });

    it('tracks remaining sequence responses', function (): void {
        $mockClient = new MockClient([
            MockResponse::json([]),
            MockResponse::json([]),
        ]);

        expect($mockClient->remainingResponses())->toBe(2);

        $mockClient->resolve(createMockClientTestRequest(), 'https://api.example.com');

        expect($mockClient->remainingResponses())->toBe(1);
    });
});

describe('MockClient Request Tracking', function (): void {
    it('records sent requests', function (): void {
        $mockClient = new MockClient([
            MockResponse::json([]),
            MockResponse::json([]),
        ]);

        $request1 = createMockClientTestRequest('/users');
        $request2 = createMockClientTestRequest('/posts');

        $mockClient->resolve($request1, 'https://api.example.com');
        $mockClient->resolve($request2, 'https://api.example.com');

        expect($mockClient->sentRequests())->toHaveCount(2);
        expect($mockClient->sentRequests()[0])->toBe($request1);
        expect($mockClient->sentRequests()[1])->toBe($request2);
    });

    it('returns last request', function (): void {
        $mockClient = new MockClient([
            MockResponse::json([]),
            MockResponse::json([]),
        ]);

        $request1 = createMockClientTestRequest('/users');
        $request2 = createMockClientTestRequest('/posts');

        $mockClient->resolve($request1, 'https://api.example.com');
        $mockClient->resolve($request2, 'https://api.example.com');

        expect($mockClient->lastRequest())->toBe($request2);
    });

    it('returns null for last request when none sent', function (): void {
        $mockClient = new MockClient();

        expect($mockClient->lastRequest())->toBeNull();
    });

    it('tracks request/response history', function (): void {
        $mockClient = new MockClient([
            MockResponse::json(['data' => 'test']),
        ]);

        $request = createMockClientTestRequest('/users');
        $mockClient->resolve($request, 'https://api.example.com');

        $history = $mockClient->history();

        expect($history)->toHaveCount(1);
        expect($history[0]['request'])->toBe($request);
        expect($history[0]['response']->json('data'))->toBe('test');
    });

    it('resets state', function (): void {
        $mockClient = new MockClient([
            MockResponse::json([]),
        ]);

        $mockClient->resolve(createMockClientTestRequest(), 'https://api.example.com');
        $mockClient->reset();

        expect($mockClient->sentRequests())->toHaveCount(0);
        expect($mockClient->history())->toHaveCount(0);
    });
});

describe('MockClient Assertions', function (): void {
    it('asserts request was sent by class', function (): void {
        $request = createMockClientTestRequest('/users');
        $mockClient = new MockClient([
            MockResponse::json([]),
        ]);

        $mockClient->resolve($request, 'https://api.example.com');

        $mockClient->assertSent($request::class);

        expect(true)->toBeTrue();
    });

    it('asserts request was sent by endpoint', function (): void {
        $mockClient = new MockClient([
            MockResponse::json([]),
        ]);

        $mockClient->resolve(createMockClientTestRequest('/users'), 'https://api.example.com');

        $mockClient->assertSent('/users');

        expect(true)->toBeTrue();
    });

    it('asserts request was sent with closure', function (): void {
        $mockClient = new MockClient([
            MockResponse::json(['id' => 1]),
        ]);

        $mockClient->resolve(createMockClientTestRequest('/users'), 'https://api.example.com');

        $mockClient->assertSent(fn (Request $request, Response $response): bool => $request->endpoint() === '/users' && $response->json('id') === 1);

        expect(true)->toBeTrue();
    });

    it('throws when asserting unsent request', function (): void {
        $mockClient = new MockClient();

        $mockClient->assertSent('/users');
    })->throws(MockClientException::class, 'was not sent');

    it('throws when closure assertion fails', function (): void {
        $mockClient = new MockClient([
            MockResponse::json([]),
        ]);

        $mockClient->resolve(createMockClientTestRequest('/users'), 'https://api.example.com');

        $mockClient->assertSent(fn (Request $r): bool => $r->endpoint() === '/posts');
    })->throws(MockClientException::class, 'No request matched');

    it('asserts request was not sent by class', function (): void {
        $mockClient = new MockClient([
            MockResponse::json([]),
        ]);

        $mockClient->resolve(createMockClientTestRequest('/users'), 'https://api.example.com');

        $mockClient->assertNotSent('/posts');

        expect(true)->toBeTrue();
    });

    it('throws when assertNotSent finds matching request', function (): void {
        $mockClient = new MockClient([
            MockResponse::json([]),
        ]);

        $mockClient->resolve(createMockClientTestRequest('/users'), 'https://api.example.com');

        $mockClient->assertNotSent('/users');
    })->throws(MockClientException::class, 'was sent but should not');

    it('asserts not sent with closure', function (): void {
        $mockClient = new MockClient([
            MockResponse::json([]),
        ]);

        $mockClient->resolve(createMockClientTestRequest('/users'), 'https://api.example.com');

        $mockClient->assertNotSent(fn (Request $r): bool => $r->endpoint() === '/posts');

        expect(true)->toBeTrue();
    });

    it('throws when assertNotSent closure matches', function (): void {
        $mockClient = new MockClient([
            MockResponse::json([]),
        ]);

        $mockClient->resolve(createMockClientTestRequest('/users'), 'https://api.example.com');

        $mockClient->assertNotSent(fn (Request $r): bool => $r->endpoint() === '/users');
    })->throws(MockClientException::class, 'should not have been sent');

    it('asserts sent count', function (): void {
        $mockClient = new MockClient([
            MockResponse::json([]),
            MockResponse::json([]),
        ]);

        $mockClient->resolve(createMockClientTestRequest('/users'), 'https://api.example.com');
        $mockClient->resolve(createMockClientTestRequest('/posts'), 'https://api.example.com');

        $mockClient->assertSentCount(2);

        expect(true)->toBeTrue();
    });

    it('asserts sent count for specific endpoint', function (): void {
        $mockClient = new MockClient([
            MockResponse::json([]),
            MockResponse::json([]),
            MockResponse::json([]),
        ]);

        $mockClient->resolve(createMockClientTestRequest('/users'), 'https://api.example.com');
        $mockClient->resolve(createMockClientTestRequest('/users'), 'https://api.example.com');
        $mockClient->resolve(createMockClientTestRequest('/posts'), 'https://api.example.com');

        // Count by filtering the sentRequests
        $userRequests = array_filter(
            $mockClient->sentRequests(),
            fn (Request $r): bool => $r->endpoint() === '/users',
        );

        expect(count($userRequests))->toBe(2);
    });

    it('throws when sent count mismatch', function (): void {
        $mockClient = new MockClient([
            MockResponse::json([]),
        ]);

        $mockClient->resolve(createMockClientTestRequest('/users'), 'https://api.example.com');

        $mockClient->assertSentCount(5);
    })->throws(MockClientException::class, 'Expected 5 requests, but 1 were sent');

    it('asserts nothing was sent', function (): void {
        $mockClient = new MockClient();

        $mockClient->assertNothingSent();

        expect(true)->toBeTrue();
    });

    it('throws when asserting nothing sent but requests were made', function (): void {
        $mockClient = new MockClient([
            MockResponse::json([]),
        ]);

        $mockClient->resolve(createMockClientTestRequest('/users'), 'https://api.example.com');

        $mockClient->assertNothingSent();
    })->throws(MockClientException::class);
});

describe('MockClient Error Handling', function (): void {
    it('throws when no matching response found', function (): void {
        $mockClient = new MockClient([
            'https://other.example.com/*' => MockResponse::json([]),
        ]);

        $mockClient->resolve(createMockClientTestRequest('/users'), 'https://api.example.com');
    })->throws(MockClientException::class, 'No mock response configured');
});

describe('MockClient addResponse', function (): void {
    it('adds mapped response', function (): void {
        $mockClient = new MockClient();
        $mockClient->addResponse('/users', MockResponse::json(['id' => 1]));

        $response = $mockClient->resolve(createMockClientTestRequest('/users'), 'https://api.example.com');

        expect($response->json('id'))->toBe(1);
    });
});

describe('MockClient assertSentCount with request class', function (): void {
    it('counts requests by specific class', function (): void {
        $request1 = createMockClientTestRequest('/users', 'GET');
        $request2 = createMockClientTestRequest('/posts', 'POST');
        $request3 = createMockClientTestRequest('/comments', 'GET');

        $mockClient = new MockClient([
            MockResponse::json([]),
            MockResponse::json([]),
            MockResponse::json([]),
        ]);

        $mockClient->resolve($request1, 'https://api.example.com');
        $mockClient->resolve($request2, 'https://api.example.com');
        $mockClient->resolve($request3, 'https://api.example.com');

        // Count by specific request class - request1 and request3 have same class (GET)
        $mockClient->assertSentCount(2, $request1::class);

        expect(true)->toBeTrue();
    });

    it('throws when count by class does not match', function (): void {
        $request = createMockClientTestRequest('/users');
        $mockClient = new MockClient([
            MockResponse::json([]),
        ]);

        $mockClient->resolve($request, 'https://api.example.com');

        $mockClient->assertSentCount(5, $request::class);
    })->throws(MockClientException::class, 'Expected 5 requests, but 1 were sent');
});

describe('MockClient URL pattern matching', function (): void {
    it('matches URL with regex pattern starting with /', function (): void {
        $request = createMockClientTestRequest('/users/123');
        $mockClient = new MockClient([
            '/^https:\/\/api\.example\.com\/users\/\d+$/' => MockResponse::json(['matched' => 'regex']),
        ]);

        $response = $mockClient->resolve($request, 'https://api.example.com');

        expect($response->json('matched'))->toBe('regex');
    });

    it('skips class names during URL pattern matching', function (): void {
        // Create two different request types
        $getUserRequest = createMockClientTestRequest('/users', 'GET');
        $postUserRequest = createMockClientTestRequest('/users', 'POST');

        // Map only the GET request class, and add a URL pattern for the POST
        $mockClient = new MockClient([
            $getUserRequest::class => MockResponse::json(['type' => 'get-class']),
            '/users' => MockResponse::json(['type' => 'url-pattern']),
        ]);

        // The POST request should match the URL pattern, not the GET class
        // This ensures the class name is skipped during URL pattern matching
        $response = $mockClient->resolve($postUserRequest, 'https://api.example.com');

        expect($response->json('type'))->toBe('url-pattern');
    });
});

describe('MockClient Fixture Integration', function (): void {
    beforeEach(function (): void {
        Fixture::setFixturePath('tests/Fixtures/Saloon');
    });

    afterEach(function (): void {
        $testPath = 'tests/Fixtures/Saloon/mock-client-test.json';

        if (!file_exists($testPath)) {
            return;
        }

        unlink($testPath);
    });

    it('resolves fixture response', function (): void {
        // Create a fixture file
        $fixture = Fixture::make('mock-client-test');
        $path = $fixture->getFilePath();
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        file_put_contents($path, json_encode([
            'status' => 200,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => ['fixture' => 'data'],
        ]));

        $request = createMockClientTestRequest('/users');
        $mockClient = new MockClient([
            $request::class => $fixture,
        ]);

        $response = $mockClient->resolve($request, 'https://api.example.com');

        expect($response->json('fixture'))->toBe('data');
        expect($response->status())->toBe(200);
    });
});

describe('MockClientException', function (): void {
    it('creates noMatchingResponse exception', function (): void {
        $exception = MockClientException::noMatchingResponse('TestRequest', 'https://api.example.com/users');

        expect($exception->getMessage())->toContain('No mock response configured');
        expect($exception->getMessage())->toContain('TestRequest');
        expect($exception->getMessage())->toContain('https://api.example.com/users');
    });

    it('creates strayRequest exception', function (): void {
        $exception = MockClientException::strayRequest('TestRequest', 'https://api.example.com/users');

        expect($exception->getMessage())->toContain('Stray request attempted');
        expect($exception->getMessage())->toContain('TestRequest');
    });

    it('creates assertionFailed exception', function (): void {
        $exception = MockClientException::assertionFailed('Custom assertion message');

        expect($exception->getMessage())->toBe('Custom assertion message');
    });
});

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
use Cline\Relay\Support\Attributes\Methods\Get;
use Cline\Relay\Support\Exceptions\MockConnectorException;
use Cline\Relay\Testing\MockConnector;
use Cline\Relay\Testing\MockResponse;
use Cline\Relay\Testing\RequestRecorder;

function createMockRequest(string $endpoint = '/users'): Request
{
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

describe('MockResponse', function (): void {
    it('creates JSON response', function (): void {
        $response = MockResponse::json(['name' => 'John']);

        expect($response->status())->toBe(200);
        expect($response->json('name'))->toBe('John');
        expect($response->header('Content-Type'))->toBe('application/json');
    });

    it('creates text response', function (): void {
        $response = MockResponse::text('Hello World');

        expect($response->body())->toBe('Hello World');
        expect($response->header('Content-Type'))->toBe('text/plain');
    });

    it('creates empty response', function (): void {
        $response = MockResponse::empty();

        expect($response->status())->toBe(204);
        expect($response->body())->toBe('');
    });

    it('creates error responses', function (): void {
        expect(MockResponse::notFound()->status())->toBe(404);
        expect(MockResponse::unauthorized()->status())->toBe(401);
        expect(MockResponse::forbidden()->status())->toBe(403);
        expect(MockResponse::serverError()->status())->toBe(500);
        expect(MockResponse::serviceUnavailable()->status())->toBe(503);
    });

    it('creates validation error response', function (): void {
        $response = MockResponse::validationError([
            'email' => ['The email field is required.'],
        ]);

        expect($response->status())->toBe(422);
        expect($response->json('errors.email.0'))->toBe('The email field is required.');
    });

    it('creates rate limited response', function (): void {
        $response = MockResponse::rateLimited(120);

        expect($response->status())->toBe(429);
        expect($response->header('Retry-After'))->toBe('120');
        expect($response->header('X-RateLimit-Remaining'))->toBe('0');
    });

    it('creates file download response', function (): void {
        $response = MockResponse::file('PDF content', 'document.pdf', 'application/pdf');

        expect($response->status())->toBe(200);
        expect($response->header('Content-Type'))->toBe('application/pdf');
        expect($response->header('Content-Disposition'))->toContain('document.pdf');
        expect($response->body())->toBe('PDF content');
    });

    it('creates paginated response', function (): void {
        $response = MockResponse::paginated(
            items: [['id' => 1], ['id' => 2]],
            page: 2,
            perPage: 10,
            total: 50,
        );

        expect($response->json('data'))->toHaveCount(2);
        expect($response->json('meta.current_page'))->toBe(2);
        expect($response->json('meta.per_page'))->toBe(10);
        expect($response->json('meta.total'))->toBe(50);
        expect($response->json('meta.last_page'))->toBe(5);
    });

    it('creates cached response', function (): void {
        $response = MockResponse::cached('"abc123"', 'Mon, 01 Jan 2024 00:00:00 GMT');

        expect($response->header('ETag'))->toBe('"abc123"');
        expect($response->header('Last-Modified'))->toBe('Mon, 01 Jan 2024 00:00:00 GMT');
    });

    it('creates not modified response', function (): void {
        $response = MockResponse::notModified();

        expect($response->status())->toBe(304);
    });

    test('creates response with custom headers using static withHeaders method', function (): void {
        // Arrange & Act
        $response = MockResponse::withHeaders([
            'X-Custom-Header' => 'custom-value',
            'X-API-Version' => 'v2',
        ]);

        // Assert
        expect($response->status())->toBe(200)
            ->and($response->header('X-Custom-Header'))->toBe('custom-value')
            ->and($response->header('X-API-Version'))->toBe('v2')
            ->and($response->body())->toBe('{}');
    });

    test('creates response with empty headers array using withHeaders', function (): void {
        // Arrange & Act
        $response = MockResponse::withHeaders([]);

        // Assert
        expect($response->status())->toBe(200)
            ->and($response->body())->toBe('{}');
    });

    test('creates response with single header using withHeaders', function (): void {
        // Arrange & Act
        $response = MockResponse::withHeaders([
            'Authorization' => 'Bearer token123',
        ]);

        // Assert
        expect($response->status())->toBe(200)
            ->and($response->header('Authorization'))->toBe('Bearer token123')
            ->and($response->body())->toBe('{}');
    });

    test('adds headers immutably with withHeaders method', function (): void {
        // Arrange
        $original = MockResponse::json(['data' => 'test']);

        // Act
        $modified = $original->withHeaders([
            'X-Custom-Header' => 'custom-value',
            'X-Another-Header' => 'another-value',
        ]);

        // Assert - Original should not have new headers
        expect($original->header('X-Custom-Header'))->toBeNull();
        expect($original->header('X-Another-Header'))->toBeNull();

        // Modified should have new headers
        expect($modified->header('X-Custom-Header'))->toBe('custom-value');
        expect($modified->header('X-Another-Header'))->toBe('another-value');
    });

    test('preserves existing headers when adding new ones with withHeaders', function (): void {
        // Arrange
        $original = MockResponse::json(['data' => 'test'], 200, [
            'Content-Type' => 'application/json',
            'X-Original' => 'original-value',
        ]);

        // Act
        $modified = $original->withHeaders([
            'X-New-Header' => 'new-value',
        ]);

        // Assert - Modified should have both old and new headers
        expect($modified->header('Content-Type'))->toBe('application/json');
        expect($modified->header('X-Original'))->toBe('original-value');
        expect($modified->header('X-New-Header'))->toBe('new-value');
    });

    test('overwrites existing header with withHeaders when same key provided', function (): void {
        // Arrange
        $original = MockResponse::json(['data' => 'test'], 200, [
            'X-Test' => 'original',
        ]);

        // Act
        $modified = $original->withHeaders([
            'X-Test' => 'modified',
        ]);

        // Assert
        expect($original->header('X-Test'))->toBe('original');
        expect($modified->header('X-Test'))->toBe('modified');
    });
});

describe('MockConnector', function (): void {
    it('returns mock responses in sequence', function (): void {
        $connector = new MockConnector();
        $connector
            ->addResponse(MockResponse::json(['id' => 1]))
            ->addResponse(MockResponse::json(['id' => 2]));

        $request = createMockRequest();

        $response1 = $connector->send($request);
        $response2 = $connector->send($request);

        expect($response1->json('id'))->toBe(1);
        expect($response2->json('id'))->toBe(2);
    });

    it('returns same response for all requests with alwaysReturn', function (): void {
        $connector = new MockConnector();
        $connector->alwaysReturn(MockResponse::json(['success' => true]));

        $request = createMockRequest();

        $response1 = $connector->send($request);
        $response2 = $connector->send($request);

        expect($response1->json('success'))->toBeTrue();
        expect($response2->json('success'))->toBeTrue();
    });

    it('records sent requests', function (): void {
        $connector = new MockConnector();
        $connector->alwaysReturn(MockResponse::json([]));

        $request1 = createMockRequest('/users');
        $request2 = createMockRequest('/posts');

        $connector->send($request1);
        $connector->send($request2);

        expect($connector->sentRequests())->toHaveCount(2);
        expect($connector->lastRequest())->toBe($request2);
    });

    it('uses closure to generate dynamic responses', function (): void {
        $connector = new MockConnector();
        $connector->addResponse(fn (Request $request): Response => MockResponse::json([
            'endpoint' => $request->endpoint(),
        ]));

        $response = $connector->send(createMockRequest('/users'));

        expect($response->json('endpoint'))->toBe('/users');
    });

    it('returns mock base URL', function (): void {
        $connector = new MockConnector();

        expect($connector->baseUrl())->toBe('https://mock.api.test');
    });

    it('asserts requests were sent', function (): void {
        $connector = new MockConnector();
        $connector->alwaysReturn(MockResponse::json([]));

        $connector->send(createMockRequest('/users'));

        // These assertions pass if no exception is thrown
        $connector->assertSent('/users');
        $connector->assertSent('/users', 'GET');
        $connector->assertNotSent('/posts');
        $connector->assertSentCount(1);

        expect(true)->toBeTrue(); // Explicit assertion for test framework
    });

    it('asserts request was not sent when endpoint matches but method differs', function (): void {
        $connector = new MockConnector();
        $connector->alwaysReturn(MockResponse::json([]));

        $connector->send(createMockRequest('/users')); // GET request

        // Should not throw because method differs
        $connector->assertNotSent('/users', 'POST');
        $connector->assertNotSent('/users', 'DELETE');

        expect(true)->toBeTrue(); // Explicit assertion for test framework
    });

    test('throws RuntimeException when assertNotSent finds matching request', function (): void {
        // Arrange
        $connector = new MockConnector();
        $connector->alwaysReturn(MockResponse::json([]));
        $connector->send(createMockRequest('/users')); // GET request

        // Act & Assert - Should throw because request was actually sent
        $connector->assertNotSent('/users');
    })->throws(RuntimeException::class, 'Request was unexpectedly sent to');

    test('throws RuntimeException when assertNotSent finds matching endpoint and method', function (): void {
        // Arrange
        $connector = new MockConnector();
        $connector->alwaysReturn(MockResponse::json([]));
        $connector->send(createMockRequest('/users')); // GET request

        // Act & Assert
        $connector->assertNotSent('/users', 'GET');
    })->throws(RuntimeException::class, 'Request was unexpectedly sent to');

    it('asserts nothing was sent', function (): void {
        $connector = new MockConnector();

        // Should not throw when no requests sent
        $connector->assertNothingSent();

        expect(true)->toBeTrue(); // Explicit assertion for test framework
    });

    it('throws when asserting unsent request', function (): void {
        $connector = new MockConnector();

        $connector->assertSent('/users');
    })->throws(RuntimeException::class, 'No request was sent');

    it('throws when no responses configured', function (): void {
        $connector = new MockConnector();

        $connector->send(createMockRequest());
    })->throws(RuntimeException::class, 'No mock responses configured');

    it('tracks request/response history', function (): void {
        $connector = new MockConnector();
        $connector->addResponse(MockResponse::json(['data' => 'test']));

        $connector->send(createMockRequest('/users'));

        $history = $connector->history();

        expect($history)->toHaveCount(1);
        expect($history[0]['request']->endpoint())->toBe('/users');
        expect($history[0]['response']->json('data'))->toBe('test');
    });

    it('resets state', function (): void {
        $connector = new MockConnector();
        $connector->addResponse(MockResponse::json([]));
        $connector->send(createMockRequest());

        $connector->reset();

        expect($connector->sentRequests())->toHaveCount(0);
        expect($connector->history())->toHaveCount(0);
        expect($connector->remainingResponses())->toBe(0);
    });
});

describe('RequestRecorder', function (): void {
    it('records requests', function (): void {
        $recorder = new RequestRecorder();
        $request = createMockRequest();

        $recorder->record($request);

        expect($recorder->count())->toBe(1);
        expect($recorder->lastRequest())->toBe($request);
    });

    it('records requests with responses', function (): void {
        $recorder = new RequestRecorder();
        $request = createMockRequest();
        $response = MockResponse::json([]);

        $recorder->record($request, $response);

        expect($recorder->lastResponse())->toBe($response);
    });

    it('finds requests by endpoint', function (): void {
        $recorder = new RequestRecorder();
        $recorder->record(createMockRequest('/users'));
        $recorder->record(createMockRequest('/posts'));
        $recorder->record(createMockRequest('/users'));

        $matches = $recorder->findByEndpoint('/users');

        expect($matches)->toHaveCount(2);
    });

    it('finds requests by method', function (): void {
        $recorder = new RequestRecorder();
        $recorder->record(createMockRequest('/users'));

        $matches = $recorder->findByMethod('GET');

        expect($matches)->toHaveCount(1);
    });

    it('clears records', function (): void {
        $recorder = new RequestRecorder();
        $recorder->record(createMockRequest());

        $recorder->clear();

        expect($recorder->count())->toBe(0);
        expect($recorder->hasRecords())->toBeFalse();
    });

    it('asserts recorded endpoint', function (): void {
        $recorder = new RequestRecorder();
        $recorder->record(createMockRequest('/users'));

        // Assertion passes if no exception is thrown
        $recorder->assertRecordedEndpoint('/users');

        expect(true)->toBeTrue(); // Explicit assertion for test framework
    });

    it('throws when endpoint not recorded', function (): void {
        $recorder = new RequestRecorder();

        $recorder->assertRecordedEndpoint('/users');
    })->throws(RuntimeException::class, 'No request to /users was recorded');

    it('returns all records with request response and timestamp', function (): void {
        $recorder = new RequestRecorder();
        $request = createMockRequest('/users');
        $response = MockResponse::json(['id' => 1]);

        $recorder->record($request, $response);

        $records = $recorder->all();

        expect($records)->toHaveCount(1);
        expect($records[0]['request'])->toBe($request);
        expect($records[0]['response'])->toBe($response);
        expect($records[0]['timestamp'])->toBeFloat();
    });

    it('returns array of all recorded requests', function (): void {
        $recorder = new RequestRecorder();
        $request1 = createMockRequest('/users');
        $request2 = createMockRequest('/posts');

        $recorder->record($request1);
        $recorder->record($request2);

        $requests = $recorder->requests();

        expect($requests)->toHaveCount(2);
        expect($requests[0])->toBe($request1);
        expect($requests[1])->toBe($request2);
    });

    it('returns array of all recorded responses', function (): void {
        $recorder = new RequestRecorder();
        $response1 = MockResponse::json(['id' => 1]);
        $response2 = MockResponse::json(['id' => 2]);

        $recorder->record(createMockRequest('/users'), $response1);
        $recorder->record(createMockRequest('/posts'), $response2);

        $responses = $recorder->responses();

        expect($responses)->toHaveCount(2);
        expect($responses[0])->toBe($response1);
        expect($responses[1])->toBe($response2);
    });

    it('returns array with null responses when no response recorded', function (): void {
        $recorder = new RequestRecorder();

        $recorder->record(createMockRequest('/users'));

        $responses = $recorder->responses();

        expect($responses)->toHaveCount(1);
        expect($responses[0])->toBeNull();
    });

    it('asserts recorded request matching custom filter', function (): void {
        $recorder = new RequestRecorder();
        $recorder->record(createMockRequest('/users'));

        // Assertion passes if no exception is thrown
        $recorder->assertRecorded(fn ($r): bool => $r->endpoint() === '/users');

        expect(true)->toBeTrue(); // Explicit assertion for test framework
    });

    it('throws when no request matches custom filter', function (): void {
        $recorder = new RequestRecorder();
        $recorder->record(createMockRequest('/users'));

        $recorder->assertRecorded(fn ($r): bool => $r->endpoint() === '/posts');
    })->throws(RuntimeException::class, 'No matching request was recorded');
});

describe('Connector::fake()', function (): void {
    it('creates a mock connector', function (): void {
        $connector = Connector::fake();

        expect($connector)->toBeInstanceOf(MockConnector::class);
    });

    it('creates mock connector with responses', function (): void {
        $connector = Connector::fake([
            MockResponse::json(['id' => 1]),
            MockResponse::json(['id' => 2]),
        ]);

        $response1 = $connector->send(createMockRequest());
        $response2 = $connector->send(createMockRequest());

        expect($response1->json('id'))->toBe(1);
        expect($response2->json('id'))->toBe(2);
    });

    it('supports closure responses', function (): void {
        $connector = Connector::fake([
            fn (Request $request): Response => MockResponse::json([
                'path' => $request->endpoint(),
            ]),
        ]);

        $response = $connector->send(createMockRequest('/api/users'));

        expect($response->json('path'))->toBe('/api/users');
    });

    it('supports assertions', function (): void {
        $connector = Connector::fake([
            MockResponse::json([]),
        ]);

        $connector->send(createMockRequest('/users'));

        $connector->assertSent('/users');
        $connector->assertSentCount(1);

        expect(true)->toBeTrue();
    });
});

describe('MockConnectorException', function (): void {
    describe('Happy Paths', function (): void {
        test('creates exception for no responses configured', function (): void {
            // Arrange & Act
            $exception = MockConnectorException::noResponsesConfigured();

            // Assert
            expect($exception)->toBeInstanceOf(MockConnectorException::class)
                ->and($exception->getMessage())->toBe('No mock responses configured. Add responses with addResponse() before sending requests.');
        });

        test('creates exception for request not sent with endpoint only', function (): void {
            // Arrange & Act
            $exception = MockConnectorException::requestNotSent('/api/users');

            // Assert
            expect($exception)->toBeInstanceOf(MockConnectorException::class)
                ->and($exception->getMessage())->toBe('No request was sent to /api/users');
        });

        test('creates exception for request not sent with endpoint and method', function (): void {
            // Arrange & Act
            $exception = MockConnectorException::requestNotSent('/api/users', 'POST');

            // Assert
            expect($exception)->toBeInstanceOf(MockConnectorException::class)
                ->and($exception->getMessage())->toBe('No request was sent to /api/users with method POST');
        });

        test('creates exception for unexpected request', function (): void {
            // Arrange & Act
            $exception = MockConnectorException::unexpectedRequest('/api/posts');

            // Assert
            expect($exception)->toBeInstanceOf(MockConnectorException::class)
                ->and($exception->getMessage())->toBe('Request was unexpectedly sent to /api/posts');
        });

        test('creates exception for request count mismatch', function (): void {
            // Arrange & Act
            $exception = MockConnectorException::requestCountMismatch(3, 5);

            // Assert
            expect($exception)->toBeInstanceOf(MockConnectorException::class)
                ->and($exception->getMessage())->toBe('Expected 3 requests, but 5 were sent');
        });
    });

    describe('Edge Cases', function (): void {
        test('handles empty endpoint string in requestNotSent', function (): void {
            // Arrange & Act
            $exception = MockConnectorException::requestNotSent('');

            // Assert
            expect($exception->getMessage())->toContain('No request was sent to');
        });

        test('handles special characters in endpoint for requestNotSent', function (): void {
            // Arrange & Act
            $exception = MockConnectorException::requestNotSent('/api/users?id=123&type=admin');

            // Assert
            expect($exception->getMessage())->toContain('/api/users?id=123&type=admin');
        });

        test('handles null method parameter in requestNotSent', function (): void {
            // Arrange & Act
            $exception = MockConnectorException::requestNotSent('/api/users');

            // Assert
            expect($exception->getMessage())->toBe('No request was sent to /api/users')
                ->and($exception->getMessage())->not->toContain('with method');
        });

        test('handles zero counts in requestCountMismatch', function (): void {
            // Arrange & Act
            $exception = MockConnectorException::requestCountMismatch(0, 0);

            // Assert
            expect($exception->getMessage())->toBe('Expected 0 requests, but 0 were sent');
        });

        test('handles large count mismatch values', function (): void {
            // Arrange & Act
            $exception = MockConnectorException::requestCountMismatch(1_000, 2_500);

            // Assert
            expect($exception->getMessage())->toBe('Expected 1000 requests, but 2500 were sent');
        });

        test('is instance of RuntimeException', function (): void {
            // Arrange & Act
            $exception = MockConnectorException::noResponsesConfigured();

            // Assert
            expect($exception)->toBeInstanceOf(RuntimeException::class);
        });
    });
});

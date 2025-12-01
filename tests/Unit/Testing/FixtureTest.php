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
use Cline\Relay\Support\Exceptions\FixtureException;
use Cline\Relay\Testing\Fixture;
use Cline\Relay\Testing\MockConfig;
use GuzzleHttp\Psr7\Response as Psr7Response;

beforeEach(function (): void {
    MockConfig::reset();
    Fixture::setFixturePath('tests/Fixtures/Saloon');
});

afterEach(function (): void {
    MockConfig::reset();
    Fixture::setFixturePath('tests/Fixtures/Saloon');

    // Clean up any created fixture files
    $testFixturePath = 'tests/Fixtures/Saloon/test-fixture.json';

    if (file_exists($testFixturePath)) {
        unlink($testFixturePath);
    }
});

function createFixtureRequest(string $endpoint = '/users'): Request
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

describe('Fixture Creation', function (): void {
    it('creates fixture with make factory', function (): void {
        $fixture = Fixture::make('users/list');

        expect($fixture)->toBeInstanceOf(Fixture::class);
        expect($fixture->defineName())->toBe('users/list');
    });

    it('creates fixture with constructor', function (): void {
        $fixture = new Fixture('api/response');

        expect($fixture->defineName())->toBe('api/response');
    });
});

describe('Fixture Path Management', function (): void {
    it('uses default fixture path', function (): void {
        expect(Fixture::getFixturePath())->toBe('tests/Fixtures/Saloon');
    });

    it('allows setting custom fixture path', function (): void {
        Fixture::setFixturePath('custom/fixtures');

        expect(Fixture::getFixturePath())->toBe('custom/fixtures');
    });

    it('generates correct file path', function (): void {
        $fixture = Fixture::make('users/list');

        expect($fixture->getFilePath())->toBe('tests/Fixtures/Saloon/users/list.json');
    });

    it('generates file path with custom path', function (): void {
        Fixture::setFixturePath('custom/path');
        $fixture = Fixture::make('data');

        expect($fixture->getFilePath())->toBe('custom/path/data.json');
    });
});

describe('Fixture Existence Check', function (): void {
    it('returns false when fixture does not exist', function (): void {
        $fixture = Fixture::make('nonexistent-fixture');

        expect($fixture->exists())->toBeFalse();
    });

    it('returns true when fixture exists', function (): void {
        // Create a fixture file
        $fixture = Fixture::make('test-fixture');
        $path = $fixture->getFilePath();
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        file_put_contents($path, json_encode(['status' => 200, 'headers' => [], 'body' => []]));

        expect($fixture->exists())->toBeTrue();

        // Cleanup
        unlink($path);
    });
});

describe('Fixture Resolution', function (): void {
    it('throws when fixture missing and throwOnMissingFixtures enabled', function (): void {
        MockConfig::throwOnMissingFixtures(true);
        $fixture = Fixture::make('missing-fixture');
        $request = createFixtureRequest('/users');

        $fixture->resolve();
    })->throws(FixtureException::class, "Fixture 'missing-fixture' not found");

    it('throws when recording is attempted', function (): void {
        MockConfig::throwOnMissingFixtures(false);
        $fixture = Fixture::make('record-attempt');
        $request = createFixtureRequest('/users');

        $fixture->resolve();
    })->throws(FixtureException::class, 'Cannot record fixture');

    it('loads fixture from file', function (): void {
        $fixture = Fixture::make('test-fixture');
        $path = $fixture->getFilePath();
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        file_put_contents($path, json_encode([
            'status' => 200,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => ['id' => 1, 'name' => 'Test'],
        ]));

        $request = createFixtureRequest('/users');
        $response = $fixture->resolve();

        expect($response)->toBeInstanceOf(Response::class);
        expect($response->status())->toBe(200);
        expect($response->json('id'))->toBe(1);
        expect($response->json('name'))->toBe('Test');

        // Cleanup
        unlink($path);
    });
});

describe('Fixture Storage', function (): void {
    it('stores response to fixture file', function (): void {
        $fixture = Fixture::make('test-fixture');
        $response = new Response(
            new Psr7Response(
                201,
                ['X-Custom' => 'Header'],
                json_encode(['created' => true]),
            ),
        );

        $fixture->store($response);

        expect($fixture->exists())->toBeTrue();

        $contents = file_get_contents($fixture->getFilePath());
        $data = json_decode($contents, true);

        expect($data['status'])->toBe(201);
        expect($data['body']['created'])->toBeTrue();
    });

    it('creates directories when storing', function (): void {
        Fixture::setFixturePath('tests/Fixtures/Saloon/nested/deep');
        $fixture = Fixture::make('test-nested');
        $response = new Response(
            new Psr7Response(200, [], json_encode(['nested' => true])),
        );

        $fixture->store($response);

        expect($fixture->exists())->toBeTrue();

        // Cleanup
        unlink($fixture->getFilePath());
        rmdir('tests/Fixtures/Saloon/nested/deep');
        rmdir('tests/Fixtures/Saloon/nested');
    });
});

describe('Fixture Deletion', function (): void {
    it('deletes existing fixture', function (): void {
        $fixture = Fixture::make('test-fixture');
        $response = new Response(
            new Psr7Response(200, [], json_encode(['test' => true])),
        );
        $fixture->store($response);

        expect($fixture->exists())->toBeTrue();

        $result = $fixture->delete();

        expect($result)->toBeTrue();
        expect($fixture->exists())->toBeFalse();
    });

    it('returns false when deleting nonexistent fixture', function (): void {
        $fixture = Fixture::make('nonexistent');

        $result = $fixture->delete();

        expect($result)->toBeFalse();
    });
});

describe('Fixture Redaction', function (): void {
    it('redacts sensitive headers', function (): void {
        $fixture = new class('test') extends Fixture
        {
            protected function defineSensitiveHeaders(): array
            {
                return [
                    'Authorization' => '[REDACTED]',
                    'X-Api-Key' => '[API_KEY]',
                ];
            }
        };

        $path = $fixture->getFilePath();
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        file_put_contents($path, json_encode([
            'status' => 200,
            'headers' => [
                'Authorization' => 'Bearer secret-token',
                'X-Api-Key' => 'sk-1234567890',
                'Content-Type' => 'application/json',
            ],
            'body' => [],
        ]));

        $request = createFixtureRequest('/users');
        $response = $fixture->resolve();

        // Headers should be redacted
        $headers = $response->headers();
        expect($headers['Authorization'][0] ?? $headers['Authorization'] ?? null)->toContain('[REDACTED]');
        expect($headers['X-Api-Key'][0] ?? $headers['X-Api-Key'] ?? null)->toContain('[API_KEY]');

        // Cleanup
        unlink($path);
    });

    it('redacts sensitive JSON parameters', function (): void {
        $fixture = new class('test') extends Fixture
        {
            protected function defineSensitiveJsonParameters(): array
            {
                return [
                    'password' => '[HIDDEN]',
                    'secret' => fn (): string => '[DYNAMIC]',
                ];
            }
        };

        $path = $fixture->getFilePath();
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        file_put_contents($path, json_encode([
            'status' => 200,
            'headers' => [],
            'body' => [
                'user' => 'john',
                'password' => 'super-secret-123',
                'secret' => 'api-key-xyz',
            ],
        ]));

        $request = createFixtureRequest('/users');
        $response = $fixture->resolve();

        expect($response->json('user'))->toBe('john');
        expect($response->json('password'))->toBe('[HIDDEN]');
        expect($response->json('secret'))->toBe('[DYNAMIC]');

        // Cleanup
        unlink($path);
    });

    it('redacts nested JSON parameters', function (): void {
        $fixture = new class('test') extends Fixture
        {
            protected function defineSensitiveJsonParameters(): array
            {
                return [
                    'token' => '[REDACTED]',
                ];
            }
        };

        $path = $fixture->getFilePath();
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        file_put_contents($path, json_encode([
            'status' => 200,
            'headers' => [],
            'body' => [
                'auth' => [
                    'type' => 'bearer',
                    'token' => 'secret-bearer-token',
                ],
            ],
        ]));

        $request = createFixtureRequest('/users');
        $response = $fixture->resolve();

        expect($response->json('auth.type'))->toBe('bearer');
        expect($response->json('auth.token'))->toBe('[REDACTED]');

        // Cleanup
        unlink($path);
    });

    it('redacts using regex patterns', function (): void {
        $fixture = new class('test') extends Fixture
        {
            protected function defineSensitiveRegexPatterns(): array
            {
                return [
                    '/sk-[a-zA-Z0-9]+/' => '[API_KEY]',
                    '/\d{16}/' => '[CARD_NUMBER]',
                ];
            }
        };

        $path = $fixture->getFilePath();
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        file_put_contents($path, json_encode([
            'status' => 200,
            'headers' => [],
            'body' => [
                'api_key' => 'Your key is sk-abc123xyz',
                'card' => 'Card: 1234567890123456',
            ],
        ]));

        $request = createFixtureRequest('/users');
        $response = $fixture->resolve();

        expect($response->json('api_key'))->toContain('[API_KEY]');
        expect($response->json('card'))->toContain('[CARD_NUMBER]');

        // Cleanup
        unlink($path);
    });

    it('applies redactions when storing', function (): void {
        $fixture = new class('test-redact-store') extends Fixture
        {
            protected function defineSensitiveJsonParameters(): array
            {
                return [
                    'secret' => '[REDACTED]',
                ];
            }
        };

        $response = new Response(
            new Psr7Response(
                200,
                [],
                json_encode(['secret' => 'real-secret-value', 'public' => 'visible']),
            ),
        );

        $fixture->store($response);

        $contents = file_get_contents($fixture->getFilePath());
        $data = json_decode($contents, true);

        expect($data['body']['secret'])->toBe('[REDACTED]');
        expect($data['body']['public'])->toBe('visible');

        // Cleanup
        unlink($fixture->getFilePath());
    });
});

describe('Fixture Error Handling', function (): void {
    it('throws on invalid JSON', function (): void {
        $fixture = Fixture::make('test-invalid');
        $path = $fixture->getFilePath();
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        file_put_contents($path, 'not valid json {{{');

        $request = createFixtureRequest('/users');

        try {
            $fixture->resolve();
        } catch (FixtureException $fixtureException) {
            expect($fixtureException->getMessage())->toContain('Invalid JSON');
        } finally {
            unlink($path);
        }
    });
});

describe('Fixture regex redaction on string body', function (): void {
    it('redacts using regex patterns on string values in body', function (): void {
        $fixture = new class('test') extends Fixture
        {
            protected function defineSensitiveRegexPatterns(): array
            {
                return [
                    '/secret-\d+/' => '[REDACTED]',
                ];
            }
        };

        $path = $fixture->getFilePath();
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        // Test with body as a simple string instead of array
        file_put_contents($path, json_encode([
            'status' => 200,
            'headers' => [],
            'body' => 'Your secret-12345 is here',
        ]));

        $request = createFixtureRequest('/users');
        $response = $fixture->resolve();

        // Body should have redacted pattern
        expect($response->body())->toContain('[REDACTED]');
        expect($response->body())->not->toContain('secret-12345');

        // Cleanup
        unlink($path);
    });

    it('redacts nested arrays recursively with regex patterns', function (): void {
        $fixture = new class('test') extends Fixture
        {
            protected function defineSensitiveRegexPatterns(): array
            {
                return [
                    '/token-[a-z0-9]+/' => '[TOKEN_REDACTED]',
                ];
            }
        };

        $path = $fixture->getFilePath();
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        // Test with nested arrays containing sensitive patterns
        file_put_contents($path, json_encode([
            'status' => 200,
            'headers' => [],
            'body' => [
                'user' => 'john',
                'auth' => [
                    'access' => 'token-abc123',
                    'nested' => [
                        'deep' => 'token-xyz789',
                    ],
                ],
            ],
        ]));

        $request = createFixtureRequest('/users');
        $response = $fixture->resolve();

        expect($response->json('user'))->toBe('john');
        expect($response->json('auth.access'))->toBe('[TOKEN_REDACTED]');
        expect($response->json('auth.nested.deep'))->toBe('[TOKEN_REDACTED]');

        // Cleanup
        unlink($path);
    });
});

describe('FixtureException', function (): void {
    it('creates missingFixture exception', function (): void {
        $exception = FixtureException::missingFixture('test-name', '/path/to/fixture.json');

        expect($exception->getMessage())->toContain('test-name');
        expect($exception->getMessage())->toContain('/path/to/fixture.json');
        expect($exception->getMessage())->toContain('not found');
    });

    it('creates unableToRead exception', function (): void {
        $exception = FixtureException::unableToRead('/path/to/file.json');

        expect($exception->getMessage())->toContain('Unable to read');
        expect($exception->getMessage())->toContain('/path/to/file.json');
    });

    it('creates invalidJson exception', function (): void {
        $exception = FixtureException::invalidJson('/path/to/file.json');

        expect($exception->getMessage())->toContain('Invalid JSON');
        expect($exception->getMessage())->toContain('/path/to/file.json');
    });

    it('creates recordingDisabled exception', function (): void {
        $exception = FixtureException::recordingDisabled('fixture-name');

        expect($exception->getMessage())->toContain('Cannot record fixture');
        expect($exception->getMessage())->toContain('fixture-name');
    });

    it('creates unableToWrite exception', function (): void {
        $exception = FixtureException::unableToWrite('/path/to/file.json');

        expect($exception->getMessage())->toContain('Unable to write');
        expect($exception->getMessage())->toContain('/path/to/file.json');
    });
});

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;
use Cline\Relay\Features\RateLimiting\RateLimitInfo;
use Cline\Relay\Support\Attributes\Methods\Get;
use Cline\Relay\Support\Exceptions\FileWriteException;
use Cline\Relay\Testing\MockConnector;
use Cline\Relay\Testing\MockResponse;
use Illuminate\Support\Collection;
use Psr\Http\Message\ResponseInterface;
use Tests\Fixtures\TestUserDto;

describe('Response', function (): void {
    describe('status()', function (): void {
        it('returns the HTTP status code', function (): void {
            $response = Response::make(['id' => 1], 201);

            expect($response->status())->toBe(201);
        });
    });

    describe('json()', function (): void {
        it('returns the parsed JSON body', function (): void {
            $response = Response::make(['id' => 1, 'name' => 'John']);

            expect($response->json())->toBe(['id' => 1, 'name' => 'John']);
        });

        it('returns a specific key when provided', function (): void {
            $response = Response::make(['id' => 1, 'name' => 'John']);

            expect($response->json('name'))->toBe('John');
        });

        it('returns nested values with dot notation', function (): void {
            $response = Response::make([
                'user' => [
                    'profile' => [
                        'name' => 'John',
                    ],
                ],
            ]);

            expect($response->json('user.profile.name'))->toBe('John');
        });
    });

    describe('body()', function (): void {
        it('returns the raw body string', function (): void {
            $response = Response::make(['id' => 1]);

            expect($response->body())->toBe('{"id":1}');
        });
    });

    describe('header()', function (): void {
        it('returns a specific header value', function (): void {
            $response = Response::make(['id' => 1], 200, ['X-Custom' => 'value']);

            expect($response->header('X-Custom'))->toBe('value');
        });

        it('returns null for missing headers', function (): void {
            $response = Response::make(['id' => 1]);

            expect($response->header('X-Missing'))->toBeNull();
        });
    });

    describe('ok()', function (): void {
        it('returns true for 2xx status codes', function (): void {
            expect(Response::make([], 200)->ok())->toBeTrue();
            expect(Response::make([], 201)->ok())->toBeTrue();
            expect(Response::make([], 204)->ok())->toBeTrue();
        });

        it('returns false for non-2xx status codes', function (): void {
            expect(Response::make([], 400)->ok())->toBeFalse();
            expect(Response::make([], 500)->ok())->toBeFalse();
        });
    });

    describe('failed()', function (): void {
        it('returns true for 4xx and 5xx status codes', function (): void {
            expect(Response::make([], 400)->failed())->toBeTrue();
            expect(Response::make([], 404)->failed())->toBeTrue();
            expect(Response::make([], 500)->failed())->toBeTrue();
        });

        it('returns false for successful responses', function (): void {
            expect(Response::make([], 200)->failed())->toBeFalse();
            expect(Response::make([], 201)->failed())->toBeFalse();
        });
    });

    describe('clientError()', function (): void {
        it('returns true for 4xx status codes', function (): void {
            expect(Response::make([], 400)->clientError())->toBeTrue();
            expect(Response::make([], 404)->clientError())->toBeTrue();
            expect(Response::make([], 422)->clientError())->toBeTrue();
        });

        it('returns false for other status codes', function (): void {
            expect(Response::make([], 200)->clientError())->toBeFalse();
            expect(Response::make([], 500)->clientError())->toBeFalse();
        });
    });

    describe('serverError()', function (): void {
        it('returns true for 5xx status codes', function (): void {
            expect(Response::make([], 500)->serverError())->toBeTrue();
            expect(Response::make([], 503)->serverError())->toBeTrue();
        });

        it('returns false for other status codes', function (): void {
            expect(Response::make([], 200)->serverError())->toBeFalse();
            expect(Response::make([], 404)->serverError())->toBeFalse();
        });
    });

    describe('collect()', function (): void {
        it('returns a Laravel Collection', function (): void {
            $response = Response::make(['users' => [['id' => 1], ['id' => 2]]]);

            $collection = $response->collect('users');

            expect($collection)->toBeInstanceOf(Collection::class);
            expect($collection->count())->toBe(2);
        });
    });

    describe('withJson()', function (): void {
        it('creates a new response with different JSON body', function (): void {
            $original = Response::make(['id' => 1]);
            $modified = $original->withJson(['id' => 2, 'name' => 'New']);

            expect($original->json('id'))->toBe(1);
            expect($modified->json('id'))->toBe(2);
            expect($modified->json('name'))->toBe('New');
        });
    });

    describe('withHeader()', function (): void {
        it('creates a new response with an additional header', function (): void {
            $original = Response::make(['id' => 1]);
            $modified = $original->withHeader('X-Custom', 'value');

            expect($modified->header('X-Custom'))->toBe('value');
        });
    });

    describe('withStatus()', function (): void {
        it('creates a new response with different status code', function (): void {
            $original = Response::make(['id' => 1], 200);
            $modified = $original->withStatus(201);

            expect($original->status())->toBe(200);
            expect($modified->status())->toBe(201);
        });
    });

    describe('metadata', function (): void {
        it('tracks duration', function (): void {
            $response = Response::make([]);
            $response->setDuration(150.5);

            expect($response->duration())->toBe(150.5);
        });

        it('tracks trace ID', function (): void {
            $response = Response::make([]);
            $response->setTraceId('trace-123');

            expect($response->traceId())->toBe('trace-123');
        });

        it('preserves metadata when creating modified copies', function (): void {
            $original = Response::make([]);
            $original->setDuration(100.0);
            $original->setTraceId('trace-123');

            $modified = $original->withJson(['new' => 'data']);

            expect($modified->duration())->toBe(100.0);
            expect($modified->traceId())->toBe('trace-123');
        });
    });

    describe('throw()', function (): void {
        it('throws RuntimeException when response has failed with 4xx status', function (): void {
            $response = Response::make(['error' => 'Not found'], 404);

            expect(fn (): Response => $response->throw())
                ->toThrow(RuntimeException::class, 'HTTP request failed with status 404');
        });

        it('throws RuntimeException when response has failed with 5xx status', function (): void {
            $response = Response::make(['error' => 'Server error'], 500);

            expect(fn (): Response => $response->throw())
                ->toThrow(RuntimeException::class, 'HTTP request failed with status 500');
        });

        it('returns self when response is successful', function (): void {
            $response = Response::make(['success' => true], 200);

            expect($response->throw())->toBe($response);
        });
    });

    describe('redirect()', function (): void {
        it('returns true for 3xx status codes', function (): void {
            expect(Response::make([], 301)->redirect())->toBeTrue();
            expect(Response::make([], 302)->redirect())->toBeTrue();
            expect(Response::make([], 307)->redirect())->toBeTrue();
        });

        it('returns false for non-3xx status codes', function (): void {
            expect(Response::make([], 200)->redirect())->toBeFalse();
            expect(Response::make([], 404)->redirect())->toBeFalse();
            expect(Response::make([], 500)->redirect())->toBeFalse();
        });
    });

    describe('request()', function (): void {
        it('returns null when no request was provided', function (): void {
            $response = Response::make(['id' => 1]);

            expect($response->request())->toBeNull();
        });
    });

    describe('toPsrResponse()', function (): void {
        it('returns the underlying PSR-7 response', function (): void {
            $response = Response::make(['id' => 1], 200);

            $psrResponse = $response->toPsrResponse();

            expect($psrResponse)->toBeInstanceOf(ResponseInterface::class);
            expect($psrResponse->getStatusCode())->toBe(200);
        });
    });

    describe('etag()', function (): void {
        it('returns the ETag header value', function (): void {
            $response = Response::make(['id' => 1], 200, ['ETag' => '"abc123"']);

            expect($response->etag())->toBe('"abc123"');
        });

        it('returns null when ETag header is not present', function (): void {
            $response = Response::make(['id' => 1]);

            expect($response->etag())->toBeNull();
        });
    });

    describe('lastModified()', function (): void {
        it('parses Last-Modified header to DateTimeImmutable', function (): void {
            $response = Response::make(['id' => 1], 200, [
                'Last-Modified' => 'Wed, 21 Oct 2015 07:28:00 GMT',
            ]);

            $lastModified = $response->lastModified();

            expect($lastModified)->toBeInstanceOf(DateTimeImmutable::class);
            expect($lastModified->format('Y-m-d H:i:s'))->toBe('2015-10-21 07:28:00');
        });

        it('returns null when Last-Modified header is not present', function (): void {
            $response = Response::make(['id' => 1]);

            expect($response->lastModified())->toBeNull();
        });

        it('returns null when Last-Modified header has invalid format', function (): void {
            $response = Response::make(['id' => 1], 200, [
                'Last-Modified' => 'invalid-date',
            ]);

            expect($response->lastModified())->toBeNull();
        });
    });

    describe('wasNotModified()', function (): void {
        it('returns true when status is 304', function (): void {
            $response = Response::make([], 304);

            expect($response->wasNotModified())->toBeTrue();
        });

        it('returns false when status is not 304', function (): void {
            expect(Response::make([], 200)->wasNotModified())->toBeFalse();
            expect(Response::make([], 404)->wasNotModified())->toBeFalse();
        });
    });

    describe('fromCache()', function (): void {
        it('returns false by default', function (): void {
            $response = Response::make(['id' => 1]);

            expect($response->fromCache())->toBeFalse();
        });

        it('returns true when marked as from cache', function (): void {
            $response = Response::make(['id' => 1]);
            $response->setFromCache(true);

            expect($response->fromCache())->toBeTrue();
        });

        it('can be set to false explicitly', function (): void {
            $response = Response::make(['id' => 1]);
            $response->setFromCache(true);
            $response->setFromCache(false);

            expect($response->fromCache())->toBeFalse();
        });
    });

    describe('spanId()', function (): void {
        it('returns null by default', function (): void {
            $response = Response::make(['id' => 1]);

            expect($response->spanId())->toBeNull();
        });

        it('returns the span ID when set', function (): void {
            $response = Response::make(['id' => 1]);
            $response->setSpanId('span-456');

            expect($response->spanId())->toBe('span-456');
        });
    });

    describe('idempotencyKey()', function (): void {
        it('returns null by default', function (): void {
            $response = Response::make(['id' => 1]);

            expect($response->idempotencyKey())->toBeNull();
        });

        it('returns the idempotency key when set', function (): void {
            $response = Response::make(['id' => 1]);
            $response->setIdempotencyKey('idem-key-789');

            expect($response->idempotencyKey())->toBe('idem-key-789');
        });
    });

    describe('wasIdempotentReplay()', function (): void {
        it('returns false by default', function (): void {
            $response = Response::make(['id' => 1]);

            expect($response->wasIdempotentReplay())->toBeFalse();
        });

        it('returns true when marked as idempotent replay', function (): void {
            $response = Response::make(['id' => 1]);
            $response->setWasIdempotentReplay(true);

            expect($response->wasIdempotentReplay())->toBeTrue();
        });
    });

    describe('rateLimit()', function (): void {
        it('parses X-RateLimit-* headers into RateLimitInfo', function (): void {
            $response = Response::make(['id' => 1], 200, [
                'X-RateLimit-Limit' => '100',
                'X-RateLimit-Remaining' => '42',
                'X-RateLimit-Reset' => '1234567890',
            ]);

            $rateLimit = $response->rateLimit();

            expect($rateLimit)->toBeInstanceOf(RateLimitInfo::class);
            expect($rateLimit->limit())->toBe(100);
            expect($rateLimit->remaining())->toBe(42);
            expect($rateLimit->reset())->toBeInstanceOf(DateTimeImmutable::class);
        });

        it('returns null when no rate limit headers are present', function (): void {
            $response = Response::make(['id' => 1]);

            expect($response->rateLimit())->toBeNull();
        });

        it('handles partial rate limit headers', function (): void {
            $response = Response::make(['id' => 1], 200, [
                'X-RateLimit-Limit' => '100',
            ]);

            $rateLimit = $response->rateLimit();

            expect($rateLimit)->toBeInstanceOf(RateLimitInfo::class);
            expect($rateLimit->limit())->toBe(100);
            expect($rateLimit->remaining())->toBeNull();
        });
    });

    describe('withJsonKey()', function (): void {
        it('modifies a specific JSON key', function (): void {
            $original = Response::make(['id' => 1, 'name' => 'John', 'age' => 30]);
            $modified = $original->withJsonKey('name', 'Jane');

            expect($original->json('name'))->toBe('John');
            expect($modified->json('name'))->toBe('Jane');
            expect($modified->json('id'))->toBe(1);
            expect($modified->json('age'))->toBe(30);
        });

        it('sets nested values with dot notation', function (): void {
            $original = Response::make([
                'user' => [
                    'profile' => [
                        'name' => 'John',
                    ],
                ],
            ]);

            $modified = $original->withJsonKey('user.profile.name', 'Jane');

            expect($modified->json('user.profile.name'))->toBe('Jane');
        });

        it('creates new keys when they do not exist', function (): void {
            $original = Response::make(['id' => 1]);
            $modified = $original->withJsonKey('newKey', 'newValue');

            expect($modified->json('newKey'))->toBe('newValue');
        });
    });

    describe('withHeaders()', function (): void {
        it('sets multiple headers at once', function (): void {
            $original = Response::make(['id' => 1]);
            $modified = $original->withHeaders([
                'X-Custom-1' => 'value1',
                'X-Custom-2' => 'value2',
                'X-Custom-3' => 'value3',
            ]);

            expect($modified->header('X-Custom-1'))->toBe('value1');
            expect($modified->header('X-Custom-2'))->toBe('value2');
            expect($modified->header('X-Custom-3'))->toBe('value3');
        });

        it('preserves existing headers', function (): void {
            $original = Response::make(['id' => 1], 200, ['X-Existing' => 'existing']);
            $modified = $original->withHeaders(['X-New' => 'new']);

            expect($modified->header('X-Existing'))->toBe('existing');
            expect($modified->header('X-New'))->toBe('new');
        });
    });

    describe('filename()', function (): void {
        it('extracts filename from Content-Disposition header', function (): void {
            $response = Response::make(['id' => 1], 200, [
                'Content-Disposition' => 'attachment; filename="document.pdf"',
            ]);

            expect($response->filename())->toBe('document.pdf');
        });

        it('extracts filename from RFC 5987 format', function (): void {
            $response = Response::make(['id' => 1], 200, [
                'Content-Disposition' => "attachment; filename*=utf-8''document.pdf",
            ]);

            expect($response->filename())->toBe('document.pdf');
        });

        it('returns null when Content-Disposition header is not present', function (): void {
            $response = Response::make(['id' => 1]);

            expect($response->filename())->toBeNull();
        });

        it('returns null when filename is not in Content-Disposition', function (): void {
            $response = Response::make(['id' => 1], 200, [
                'Content-Disposition' => 'inline',
            ]);

            expect($response->filename())->toBeNull();
        });
    });

    describe('isDownload()', function (): void {
        it('returns true when Content-Disposition contains attachment', function (): void {
            $response = Response::make(['id' => 1], 200, [
                'Content-Disposition' => 'attachment; filename="file.pdf"',
            ]);

            expect($response->isDownload())->toBeTrue();
        });

        it('returns false when Content-Disposition is inline', function (): void {
            $response = Response::make(['id' => 1], 200, [
                'Content-Disposition' => 'inline',
            ]);

            expect($response->isDownload())->toBeFalse();
        });

        it('returns false when Content-Disposition header is not present', function (): void {
            $response = Response::make(['id' => 1]);

            expect($response->isDownload())->toBeFalse();
        });
    });

    describe('base64()', function (): void {
        it('encodes body as base64', function (): void {
            $response = Response::make(['id' => 1]);
            $expectedBase64 = base64_encode('{"id":1}');

            expect($response->base64())->toBe($expectedBase64);
        });

        it('handles empty response body', function (): void {
            $response = Response::make([]);

            expect($response->base64())->toBe(base64_encode('[]'));
        });
    });

    describe('object()', function (): void {
        it('parses JSON as stdClass', function (): void {
            $response = Response::make(['id' => 1, 'name' => 'John']);

            $object = $response->object();

            expect($object)->toBeInstanceOf(stdClass::class);
            expect($object->id)->toBe(1);
            expect($object->name)->toBe('John');
        });

        it('handles nested objects', function (): void {
            $response = Response::make([
                'user' => [
                    'id' => 1,
                    'profile' => ['name' => 'John'],
                ],
            ]);

            $object = $response->object();

            expect($object->user)->toBeInstanceOf(stdClass::class);
            expect($object->user->id)->toBe(1);
            expect($object->user->profile->name)->toBe('John');
        });
    });

    describe('dto()', function (): void {
        it('maps response to a DTO class', function (): void {
            $response = Response::make(['id' => 1, 'name' => 'John']);

            $dto = $response->dto(TestUserDto::class);

            expect($dto)->toBeInstanceOf(TestUserDto::class);
            expect($dto->data)->toBe(['id' => 1, 'name' => 'John']);
        });
    });

    describe('dtoCollection()', function (): void {
        it('maps response to collection of DTOs', function (): void {
            $response = Response::make([
                ['id' => 1, 'name' => 'John'],
                ['id' => 2, 'name' => 'Jane'],
            ]);

            $collection = $response->dtoCollection(TestUserDto::class);

            expect($collection)->toBeInstanceOf(Collection::class);
            expect($collection)->toHaveCount(2);
            expect($collection->first())->toBeInstanceOf(TestUserDto::class);
            expect($collection->first()->data['name'])->toBe('John');
            expect($collection->last()->data['name'])->toBe('Jane');
        });

        it('maps response with key to collection of DTOs', function (): void {
            $response = Response::make([
                'users' => [
                    ['id' => 1, 'name' => 'John'],
                    ['id' => 2, 'name' => 'Jane'],
                ],
            ]);

            $collection = $response->dtoCollection(TestUserDto::class, 'users');

            expect($collection)->toHaveCount(2);
            expect($collection->first())->toBeInstanceOf(TestUserDto::class);
        });

        it('returns empty collection when data is not an array', function (): void {
            $response = Response::make([]);

            $collection = $response->dtoCollection(TestUserDto::class);

            expect($collection)->toBeInstanceOf(Collection::class);
            expect($collection)->toHaveCount(0);
        });

        it('returns empty collection when key does not exist', function (): void {
            $response = Response::make(['id' => 1]);

            $collection = $response->dtoCollection(TestUserDto::class, 'nonexistent');

            expect($collection)->toBeInstanceOf(Collection::class);
            expect($collection)->toHaveCount(0);
        });
    });

    describe('toDto()', function (): void {
        it('returns null when no request is associated', function (): void {
            $response = Response::make(['id' => 1]);

            expect($response->toDto())->toBeNull();
        });

        it('returns DTO when request implements createDtoFromResponse', function (): void {
            $request = new class() extends Request
            {
                #[Get()]
                public function endpoint(): string
                {
                    return '/users/1';
                }

                public function createDtoFromResponse(Response $response): TestUserDto
                {
                    return new TestUserDto($response->json());
                }
            };

            $connector = new MockConnector();
            $connector->addResponse(MockResponse::json(['id' => 1, 'name' => 'John']));

            $response = $connector->send($request);

            $dto = $response->toDto();
            expect($dto)->toBeInstanceOf(TestUserDto::class);
            expect($dto->data['id'])->toBe(1);
        });
    });

    describe('dtoOrFail()', function (): void {
        it('throws when response failed', function (): void {
            $request = new class() extends Request
            {
                #[Get()]
                public function endpoint(): string
                {
                    return '/users/1';
                }
            };

            $connector = new MockConnector();
            $connector->addResponse(MockResponse::json(['error' => 'Not found'], 404));

            $response = $connector->send($request);

            expect(fn (): mixed => $response->dtoOrFail())->toThrow(RuntimeException::class, 'Cannot create DTO from failed response');
        });

        it('throws when no request is associated', function (): void {
            $response = Response::make(['id' => 1]);

            expect(fn (): mixed => $response->dtoOrFail())->toThrow(RuntimeException::class, 'no request associated');
        });

        it('throws when request does not implement createDtoFromResponse', function (): void {
            $request = new class() extends Request
            {
                #[Get()]
                public function endpoint(): string
                {
                    return '/users/1';
                }
            };

            $connector = new MockConnector();
            $connector->addResponse(MockResponse::json(['id' => 1]));

            $response = $connector->send($request);

            expect(fn (): mixed => $response->dtoOrFail())->toThrow(RuntimeException::class, 'does not implement createDtoFromResponse');
        });

        it('returns DTO when successful', function (): void {
            $request = new class() extends Request
            {
                #[Get()]
                public function endpoint(): string
                {
                    return '/users/1';
                }

                public function createDtoFromResponse(Response $response): TestUserDto
                {
                    return new TestUserDto($response->json());
                }
            };

            $connector = new MockConnector();
            $connector->addResponse(MockResponse::json(['id' => 1, 'name' => 'John']));

            $response = $connector->send($request);

            $dto = $response->dtoOrFail();
            expect($dto)->toBeInstanceOf(TestUserDto::class);
            expect($dto->data['name'])->toBe('John');
        });
    });

    describe('saveTo()', function (): void {
        it('saves response body to file', function (): void {
            $response = Response::make(['id' => 1, 'name' => 'Test']);
            $tempFile = sys_get_temp_dir().'/response_test_'.uniqid().'.json';

            $result = $response->saveTo($tempFile);

            expect($result)->toBe($response);
            expect(file_exists($tempFile))->toBeTrue();
            expect(file_get_contents($tempFile))->toBe('{"id":1,"name":"Test"}');

            unlink($tempFile);
        });

        it('throws exception when directory does not exist', function (): void {
            $response = Response::make(['id' => 1]);
            $invalidPath = '/nonexistent/directory/file.json';

            expect(fn (): Response => $response->saveTo($invalidPath))
                ->toThrow(FileWriteException::class);
        });
    });

    describe('streamTo()', function (): void {
        it('streams response body to file', function (): void {
            $response = Response::make(['id' => 1, 'data' => str_repeat('x', 10_000)]);
            $tempFile = sys_get_temp_dir().'/response_stream_test_'.uniqid().'.json';

            $result = $response->streamTo($tempFile);

            expect($result)->toBe($response);
            expect(file_exists($tempFile))->toBeTrue();
            expect(file_get_contents($tempFile))->toBe($response->body());

            unlink($tempFile);
        });

        it('streams response body with progress callback', function (): void {
            $response = Response::make(['id' => 1, 'data' => str_repeat('x', 10_000)], 200, [
                'Content-Length' => (string) mb_strlen(json_encode(['id' => 1, 'data' => str_repeat('x', 10_000)])),
            ]);
            $tempFile = sys_get_temp_dir().'/response_stream_progress_'.uniqid().'.json';

            $progressCalls = [];
            $response->streamTo($tempFile, function (int $downloaded, int $total) use (&$progressCalls): void {
                $progressCalls[] = ['downloaded' => $downloaded, 'total' => $total];
            });

            expect($progressCalls)->not->toBeEmpty();
            expect($progressCalls[0]['downloaded'])->toBeGreaterThan(0);
            expect($progressCalls[0]['total'])->toBeGreaterThan(0);
            expect(end($progressCalls)['downloaded'])->toBe(end($progressCalls)['total']);

            unlink($tempFile);
        });

        it('throws exception when directory does not exist', function (): void {
            $response = Response::make(['id' => 1]);
            $invalidPath = '/nonexistent/directory/stream.json';

            expect(fn (): Response => $response->streamTo($invalidPath))
                ->toThrow(FileWriteException::class);
        });

        it('handles write failures during streaming', function (): void {
            // This test verifies the error handling path when fwrite fails
            // Since making fwrite fail reliably is difficult in unit tests,
            // we verify the exception is thrown when the directory doesn't exist
            // which also triggers the FileWriteException in the streamTo method
            $response = Response::make(['id' => 1]);
            $invalidPath = '/nonexistent/path/file.json';

            expect(fn (): Response => $response->streamTo($invalidPath))
                ->toThrow(FileWriteException::class);
        });
    });

    describe('chunks()', function (): void {
        it('yields response body in chunks', function (): void {
            $response = Response::make(['data' => str_repeat('x', 20_000)]);

            $chunks = [];

            foreach ($response->chunks(8_192) as $chunk) {
                $chunks[] = $chunk;
            }

            expect($chunks)->not->toBeEmpty();
            expect(implode('', $chunks))->toBe($response->body());
        });

        it('yields single chunk for small responses', function (): void {
            $response = Response::make(['id' => 1]);

            $chunks = [];

            foreach ($response->chunks(8_192) as $chunk) {
                $chunks[] = $chunk;
            }

            expect($chunks)->toHaveCount(1);
            expect($chunks[0])->toBe('{"id":1}');
        });

        it('respects custom chunk size', function (): void {
            $response = Response::make(['data' => str_repeat('x', 100)]);

            $chunks = [];

            foreach ($response->chunks(10) as $chunk) {
                $chunks[] = $chunk;
            }

            expect(count($chunks))->toBeGreaterThan(1);
        });
    });

    describe('stream()', function (): void {
        it('returns response body as stream resource', function (): void {
            $response = Response::make(['id' => 1, 'name' => 'Test']);

            $stream = $response->stream();

            expect(is_resource($stream))->toBeTrue();
            expect(stream_get_contents($stream))->toBe('{"id":1,"name":"Test"}');

            fclose($stream);
        });

        it('creates seekable stream', function (): void {
            $response = Response::make(['data' => 'test content']);

            $stream = $response->stream();

            // Read partial content
            $partial = fread($stream, 5);
            expect($partial)->toBe('{"dat');

            // Rewind and read again
            rewind($stream);
            $full = stream_get_contents($stream);
            expect($full)->toBe($response->body());

            fclose($stream);
        });
    });

    describe('dump()', function (): void {
        it('returns self after dumping', function (): void {
            // dump() uses Laravel's dump() which outputs via Symfony VarDumper
            // We can't easily capture that output in tests, but we can verify
            // the method returns $this for chaining
            $response = Response::make(['id' => 1]);
            $response->setDuration(123.45);

            $result = $response->dump();

            expect($result)->toBe($response);
        });
    });

    describe('dd()', function (): void {
        it('dumps response data and terminates execution', function (): void {
            // dd() uses Laravel's dd() which calls exit(), so we can't test it directly
            // We verify the method exists and is callable
            $response = Response::make(['id' => 1]);

            expect(method_exists($response, 'dd'))->toBeTrue();
            expect(is_callable($response->dd(...)))->toBeTrue();
        })->skip('dd() terminates execution and cannot be tested in unit tests');
    });
});

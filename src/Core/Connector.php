<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Core;

use Cline\Relay\Features\Caching\CacheConfig;
use Cline\Relay\Features\Caching\RequestCache;
use Cline\Relay\Features\Pagination\CursorPaginator;
use Cline\Relay\Features\Pagination\LinkHeaderPaginator;
use Cline\Relay\Features\Pagination\OffsetPaginator;
use Cline\Relay\Features\Pagination\PagePaginator;
use Cline\Relay\Features\Pagination\PaginatedResponse;
use Cline\Relay\Features\RateLimiting\MemoryStore;
use Cline\Relay\Features\RateLimiting\RateLimitConfig;
use Cline\Relay\Features\RateLimiting\RateLimiter;
use Cline\Relay\Support\Attributes\Pagination\CursorPagination;
use Cline\Relay\Support\Attributes\Pagination\LinkPagination;
use Cline\Relay\Support\Attributes\Pagination\OffsetPagination;
use Cline\Relay\Support\Attributes\Pagination\Pagination;
use Cline\Relay\Support\Attributes\Pagination\SimplePagination;
use Cline\Relay\Support\Attributes\ThrowOnError;
use Cline\Relay\Support\Contracts\Paginator;
use Cline\Relay\Support\Contracts\RateLimitStore;
use Cline\Relay\Support\Exceptions\Client\ForbiddenException;
use Cline\Relay\Support\Exceptions\Client\GenericClientException;
use Cline\Relay\Support\Exceptions\Client\NotFoundException;
use Cline\Relay\Support\Exceptions\Client\RateLimitException;
use Cline\Relay\Support\Exceptions\Client\UnauthorizedException;
use Cline\Relay\Support\Exceptions\Client\ValidationException;
use Cline\Relay\Support\Exceptions\ClientException;
use Cline\Relay\Support\Exceptions\MockClientException;
use Cline\Relay\Support\Exceptions\Server\GenericServerException;
use Cline\Relay\Support\Exceptions\Server\InternalServerException;
use Cline\Relay\Support\Exceptions\Server\ServiceUnavailableException;
use Cline\Relay\Support\Exceptions\ServerException;
use Cline\Relay\Testing\MockClient;
use Cline\Relay\Testing\MockConfig;
use Cline\Relay\Testing\MockConnector;
use Cline\Relay\Transport\Http\GuzzleDriver;
use Cline\Relay\Transport\Pool\Pool;
use Closure;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request as Psr7Request;
use Illuminate\Support\Traits\Macroable;
use Psr\Http\Client\ClientInterface;
use Psr\SimpleCache\CacheInterface;
use ReflectionClass;

use function array_key_exists;
use function http_build_query;
use function json_encode;
use function mb_ltrim;
use function mb_rtrim;
use function method_exists;
use function microtime;
use function sprintf;

/**
 * Base class for API connectors.
 *
 * @author Brian Faust <brian@cline.sh>
 */
abstract class Connector
{
    use Macroable;

    protected ?ClientInterface $httpClient = null;

    protected ?RequestCache $requestCache = null;

    protected ?RateLimiter $rateLimiter = null;

    protected ?MockClient $mockClient = null;

    /**
     * Create a mock connector for testing.
     *
     * @param array<Closure(Request): Response|Response> $responses
     */
    public static function fake(array $responses = []): MockConnector
    {
        $mock = new MockConnector();

        if ($responses !== []) {
            $mock->addResponses($responses);
        }

        return $mock;
    }

    /**
     * Resolve the base URL (can be overridden for dynamic base URLs).
     */
    public function resolveBaseUrl(): string
    {
        return $this->baseUrl();
    }

    /**
     * Get the default headers for all requests.
     *
     * @return array<string, string>
     */
    public function defaultHeaders(): array
    {
        return [];
    }

    /**
     * Get the default Guzzle configuration.
     *
     * @return array<string, mixed>
     */
    public function defaultConfig(): array
    {
        return [];
    }

    /**
     * Authenticate the request.
     */
    public function authenticate(Request $request): void
    {
        // Override in subclass
    }

    /**
     * Get the middleware stack for this connector.
     */
    public function middleware(): HandlerStack
    {
        return HandlerStack::create();
    }

    /**
     * Get the default timeout in seconds.
     */
    public function timeout(): int
    {
        return 30;
    }

    /**
     * Get the default connection timeout in seconds.
     */
    public function connectTimeout(): int
    {
        return 10;
    }

    /**
     * Get the cache store for this connector.
     *
     * Override this method to enable caching for all requests.
     */
    public function cache(): ?CacheInterface
    {
        return null;
    }

    /**
     * Get the cache configuration for this connector.
     */
    public function cacheConfig(): ?CacheConfig
    {
        $cache = $this->cache();

        if (!$cache instanceof CacheInterface) {
            return null;
        }

        return new CacheConfig(store: $cache);
    }

    /**
     * Get the default cache TTL in seconds.
     */
    public function cacheTtl(): int
    {
        return 300;
    }

    /**
     * Get the cache key prefix for this connector.
     */
    public function cacheKeyPrefix(): string
    {
        return '';
    }

    /**
     * Get the HTTP methods that can be cached.
     *
     * @return array<string>
     */
    public function cacheableMethods(): array
    {
        return ['GET', 'HEAD'];
    }

    /**
     * Get the rate limit for this connector.
     */
    public function rateLimit(): ?RateLimitConfig
    {
        return null;
    }

    /**
     * Get the rate limit store for this connector.
     */
    public function rateLimitStore(): RateLimitStore
    {
        return new MemoryStore();
    }

    /**
     * Get the concurrency limit for this connector.
     */
    public function concurrencyLimit(): ?int
    {
        return null;
    }

    /**
     * Determine if a response indicates a failed request.
     *
     * Override this method to customize failure detection for APIs
     * that return errors in non-standard ways (e.g., 200 with error in body).
     */
    public function hasRequestFailed(Response $response): bool
    {
        return $response->failed();
    }

    /**
     * Set a mock client for testing.
     */
    public function withMockClient(MockClient $mockClient): static
    {
        $this->mockClient = $mockClient;

        return $this;
    }

    /**
     * Send a request and get a response.
     */
    public function send(Request $request): Response
    {
        // Set the connector on the request
        $request->setConnector($this);

        // Initialize the request
        $request->initialize();

        // Authenticate the request
        $this->authenticate($request);

        // Check for mock client (local or global)
        $mockClient = $this->getMockClient();

        if ($mockClient instanceof MockClient) {
            return $mockClient->resolve($request, $this->resolveBaseUrl());
        }

        // Check if stray requests are prevented
        if (MockConfig::shouldPreventStrayRequests()) {
            throw MockClientException::strayRequest(
                $request::class,
                $this->resolveBaseUrl().$request->endpoint(),
            );
        }

        // Check rate limit before making the request
        $rateLimiter = $this->getRateLimiter();

        if ($rateLimiter instanceof RateLimiter) {
            $rateLimiter->check($this, $request);
        }

        // Check cache before making the request
        $cache = $this->getRequestCache();

        if ($cache instanceof RequestCache) {
            $cachedResponse = $cache->get($this, $request);

            if ($cachedResponse instanceof Response) {
                return $cachedResponse;
            }
        }

        // Build the full URL
        $url = $this->buildUrl($request);

        // Merge headers
        $headers = $this->mergeHeaders($request);

        // Build request options
        $this->buildOptions($request, $headers);

        // Track timing
        $startTime = microtime(true);

        // Send the request using the HTTP client
        $psrResponse = $this->getHttpClient()->sendRequest(
            new Psr7Request(
                $request->method(),
                $url,
                $headers,
                $this->buildBody($request),
            ),
        );

        // Calculate duration
        $duration = (microtime(true) - $startTime) * 1_000;

        // Create our response wrapper
        $response = new Response($psrResponse, $request);
        $response->setDuration($duration);

        // Transform the response if the request has a transformer
        $response = $request->transformResponse($response);

        // Cache the response if applicable
        if ($cache instanceof RequestCache && $response->successful()) {
            $cache->put($this, $request, $response);

            // Handle cache invalidation for mutation requests
            $cache->handleInvalidation($this, $request);
        }

        // Check if we should throw on error
        $this->maybeThrow($request, $response);

        return $response;
    }

    /**
     * Send a GET request.
     *
     * @param array<string, mixed> $query
     */
    public function get(string $endpoint, array $query = []): Response
    {
        return $this->send(
            new class($endpoint, $query) extends Request
            {
                /**
                 * @param array<string, mixed> $query
                 */
                public function __construct(
                    private readonly string $endpoint,
                    private readonly array $query,
                ) {}

                public function endpoint(): string
                {
                    return $this->endpoint;
                }

                /**
                 * @return null|array<string, mixed>
                 */
                public function query(): ?array
                {
                    return $this->query !== [] ? $this->query : null;
                }

                public function method(): string
                {
                    return 'GET';
                }
            },
        );
    }

    /**
     * Send a POST request.
     *
     * @param array<string, mixed> $data
     */
    public function post(string $endpoint, array $data = []): Response
    {
        return $this->send(
            new class($endpoint, $data) extends Request
            {
                /**
                 * @param array<string, mixed> $data
                 */
                public function __construct(
                    private readonly string $endpoint,
                    private readonly array $data,
                ) {}

                public function endpoint(): string
                {
                    return $this->endpoint;
                }

                /**
                 * @return null|array<string, mixed>
                 */
                public function body(): ?array
                {
                    return $this->data !== [] ? $this->data : null;
                }

                public function method(): string
                {
                    return 'POST';
                }

                public function contentType(): string
                {
                    return 'application/json';
                }
            },
        );
    }

    /**
     * Send a PUT request.
     *
     * @param array<string, mixed> $data
     */
    public function put(string $endpoint, array $data = []): Response
    {
        return $this->send(
            new class($endpoint, $data) extends Request
            {
                /**
                 * @param array<string, mixed> $data
                 */
                public function __construct(
                    private readonly string $endpoint,
                    private readonly array $data,
                ) {}

                public function endpoint(): string
                {
                    return $this->endpoint;
                }

                /**
                 * @return null|array<string, mixed>
                 */
                public function body(): ?array
                {
                    return $this->data !== [] ? $this->data : null;
                }

                public function method(): string
                {
                    return 'PUT';
                }

                public function contentType(): string
                {
                    return 'application/json';
                }
            },
        );
    }

    /**
     * Send a PATCH request.
     *
     * @param array<string, mixed> $data
     */
    public function patch(string $endpoint, array $data = []): Response
    {
        return $this->send(
            new class($endpoint, $data) extends Request
            {
                /**
                 * @param array<string, mixed> $data
                 */
                public function __construct(
                    private readonly string $endpoint,
                    private readonly array $data,
                ) {}

                public function endpoint(): string
                {
                    return $this->endpoint;
                }

                /**
                 * @return null|array<string, mixed>
                 */
                public function body(): ?array
                {
                    return $this->data !== [] ? $this->data : null;
                }

                public function method(): string
                {
                    return 'PATCH';
                }

                public function contentType(): string
                {
                    return 'application/json';
                }
            },
        );
    }

    /**
     * Send a DELETE request.
     */
    public function delete(string $endpoint): Response
    {
        return $this->send(
            new class($endpoint) extends Request
            {
                public function __construct(
                    private readonly string $endpoint,
                ) {}

                public function endpoint(): string
                {
                    return $this->endpoint;
                }

                public function method(): string
                {
                    return 'DELETE';
                }
            },
        );
    }

    /**
     * Forget a cached request.
     */
    public function forgetCache(Request $request): bool
    {
        $cache = $this->getRequestCache();

        if (!$cache instanceof RequestCache) {
            return false;
        }

        return $cache->forget($this, $request);
    }

    /**
     * Invalidate cache by tags.
     *
     * @param array<string> $tags
     */
    public function invalidateCacheTags(array $tags): void
    {
        $cache = $this->getRequestCache();

        if (!$cache instanceof RequestCache) {
            return;
        }

        $cache->invalidateTags($tags);
    }

    /**
     * Flush the entire cache.
     */
    public function flushCache(): bool
    {
        $cache = $this->getRequestCache();

        if (!$cache instanceof RequestCache) {
            return false;
        }

        return $cache->flush();
    }

    /**
     * Check if this connector has a specific attribute.
     *
     * @template T of object
     *
     * @param class-string<T> $attributeClass
     */
    public function hasAttribute(string $attributeClass): bool
    {
        $reflection = new ReflectionClass($this);
        $attributes = $reflection->getAttributes($attributeClass);

        return $attributes !== [];
    }

    /**
     * Get a specific attribute instance.
     *
     * @template T of object
     *
     * @param  class-string<T> $attributeClass
     * @return null|T
     */
    public function getAttribute(string $attributeClass): ?object
    {
        $reflection = new ReflectionClass($this);
        $attributes = $reflection->getAttributes($attributeClass);

        if ($attributes === []) {
            return null;
        }

        return $attributes[0]->newInstance();
    }

    /**
     * Enable debug mode on this connector.
     */
    public function debug(): static
    {
        // TODO: Implement debug mode
        return $this;
    }

    /**
     * Send a paginated request.
     */
    public function paginate(Request $request, ?Paginator $paginator = null): PaginatedResponse
    {
        // Auto-detect paginator from request attributes if not provided
        $paginator ??= $this->createPaginatorForRequest($request);

        // Send the initial request
        $response = $this->send($request);

        return new PaginatedResponse($this, $request, $paginator, $response);
    }

    /**
     * Create a pool for concurrent requests.
     *
     * @param array<int|string, Request> $requests Array of requests (optionally keyed)
     */
    public function pool(array $requests): Pool
    {
        return new Pool($this, $requests);
    }

    /**
     * Get the base URL for this connector.
     */
    abstract public function baseUrl(): string;

    /**
     * Get the active mock client (local or global).
     */
    protected function getMockClient(): ?MockClient
    {
        // Local mock client takes precedence
        if ($this->mockClient instanceof MockClient) {
            return $this->mockClient;
        }

        // Fall back to global mock client
        return MockClient::getGlobal();
    }

    /**
     * Get the HTTP client instance.
     */
    protected function getHttpClient(): ClientInterface
    {
        if (!$this->httpClient instanceof ClientInterface) {
            $this->httpClient = new GuzzleDriver(
                timeout: $this->timeout(),
                connectTimeout: $this->connectTimeout(),
                config: $this->defaultConfig(),
                handler: $this->middleware(),
            );
        }

        return $this->httpClient;
    }

    /**
     * Get the request cache instance.
     */
    protected function getRequestCache(): ?RequestCache
    {
        $config = $this->cacheConfig();

        if (!$config instanceof CacheConfig) {
            return null;
        }

        if (!$this->requestCache instanceof RequestCache) {
            $this->requestCache = new RequestCache($config);
        }

        return $this->requestCache;
    }

    /**
     * Get the rate limiter instance.
     */
    protected function getRateLimiter(): ?RateLimiter
    {
        $defaultConfig = $this->rateLimit();

        // Only create limiter if we have a default config or request attributes
        if (!$defaultConfig instanceof RateLimitConfig) {
            // We still might have request-level rate limits
            // Return a limiter that will check request attributes
        }

        if (!$this->rateLimiter instanceof RateLimiter) {
            $this->rateLimiter = new RateLimiter(
                store: $this->rateLimitStore(),
                defaultConfig: $defaultConfig,
            );
        }

        return $this->rateLimiter;
    }

    /**
     * Build the full URL for a request.
     */
    protected function buildUrl(Request $request): string
    {
        $baseUrl = mb_rtrim($this->resolveBaseUrl(), '/');
        $endpoint = mb_ltrim($request->endpoint(), '/');
        $url = sprintf('%s/%s', $baseUrl, $endpoint);

        $query = $request->allQuery();

        if ($query !== []) {
            $url .= '?'.http_build_query($query);
        }

        return $url;
    }

    /**
     * Merge connector and request headers.
     *
     * @return array<string, string>
     */
    protected function mergeHeaders(Request $request): array
    {
        $headers = [
            ...$this->defaultHeaders(),
            ...$request->allHeaders(),
        ];

        // Add content type header if set
        $contentType = $request->contentType();

        if ($contentType !== null) {
            $headers['Content-Type'] = $contentType;
        }

        // Add Accept header if not set (default to JSON for JSON requests)
        if (!array_key_exists('Accept', $headers) && $contentType === 'application/json') {
            $headers['Accept'] = 'application/json';
        }

        return $headers;
    }

    /**
     * Build request options for Guzzle.
     *
     * @param  array<string, string> $headers
     * @return array<string, mixed>
     */
    protected function buildOptions(Request $request, array $headers): array
    {
        return [
            'headers' => $headers,
            'timeout' => $this->timeout(),
            'connect_timeout' => $this->connectTimeout(),
        ];
    }

    /**
     * Build the request body.
     */
    protected function buildBody(Request $request): ?string
    {
        // Check for raw body first (used by SOAP requests)
        if (method_exists($request, 'rawBody')) {
            $rawBody = $request->rawBody();

            if ($rawBody !== null && $rawBody !== '') {
                // @phpstan-ignore-next-line - rawBody() is a mixed type from dynamic method check
                return $rawBody;
            }
        }

        $body = $request->body();

        if ($body === null) {
            return null;
        }

        $contentType = $request->contentType();

        return match ($contentType) {
            'application/json' => json_encode($body) ?: null,
            'application/x-www-form-urlencoded' => http_build_query($body),
            default => json_encode($body) ?: null,
        };
    }

    /**
     * Check if we should throw an exception and throw if needed.
     */
    protected function maybeThrow(Request $request, Response $response): void
    {
        // Check connector-level throw on error
        $connectorThrows = $this->hasAttribute(ThrowOnError::class);
        $connectorThrowsAttr = $this->getAttribute(ThrowOnError::class);

        // Check request-level throw on error
        $requestThrows = $request->hasAttribute(ThrowOnError::class);
        $requestThrowsAttr = $request->getAttribute(ThrowOnError::class);

        // Determine if we should throw
        $shouldThrowClient = false;
        $shouldThrowServer = false;

        if ($requestThrows && $requestThrowsAttr instanceof ThrowOnError) {
            $shouldThrowClient = $requestThrowsAttr->clientErrors;
            $shouldThrowServer = $requestThrowsAttr->serverErrors;
        } elseif ($connectorThrows && $connectorThrowsAttr instanceof ThrowOnError) {
            $shouldThrowClient = $connectorThrowsAttr->clientErrors;
            $shouldThrowServer = $connectorThrowsAttr->serverErrors;
        }

        // Throw appropriate exception
        if ($response->clientError() && $shouldThrowClient) {
            throw $this->createClientException($request, $response);
        }

        if ($response->serverError() && $shouldThrowServer) {
            throw $this->createServerException($request, $response);
        }
    }

    /**
     * Create the appropriate client exception for a response.
     */
    protected function createClientException(Request $request, Response $response): ClientException
    {
        return match ($response->status()) {
            401 => UnauthorizedException::fromResponse($request, $response),
            403 => ForbiddenException::fromResponse($request, $response),
            404 => NotFoundException::fromResponse($request, $response),
            422 => ValidationException::fromResponse($request, $response),
            429 => RateLimitException::fromResponse($request, $response),
            default => GenericClientException::fromResponse($request, $response),
        };
    }

    /**
     * Create the appropriate server exception for a response.
     */
    protected function createServerException(Request $request, Response $response): ServerException
    {
        return match ($response->status()) {
            500 => InternalServerException::fromResponse($request, $response),
            503 => ServiceUnavailableException::fromResponse($request, $response),
            default => GenericServerException::fromResponse($request, $response),
        };
    }

    /**
     * Create a paginator based on request attributes.
     */
    protected function createPaginatorForRequest(Request $request): Paginator
    {
        // Check for pagination attributes
        if (($attr = $request->getAttribute(Pagination::class)) instanceof Pagination) {
            return new PagePaginator($attr);
        }

        if (($attr = $request->getAttribute(CursorPagination::class)) instanceof CursorPagination) {
            return new CursorPaginator($attr);
        }

        if (($attr = $request->getAttribute(OffsetPagination::class)) instanceof OffsetPagination) {
            return new OffsetPaginator($attr);
        }

        if (($attr = $request->getAttribute(LinkPagination::class)) instanceof LinkPagination) {
            return new LinkHeaderPaginator($attr);
        }

        if ($request->getAttribute(SimplePagination::class) instanceof SimplePagination) {
            // SimplePagination uses the same paginator as Pagination
            // but without total count
            return new PagePaginator(
                new Pagination(),
            );
        }

        // Default to page-based pagination
        return new PagePaginator(
            new Pagination(),
        );
    }
}

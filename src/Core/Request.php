<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Core;

use Cline\Relay\Support\Attributes\ContentTypes\Form;
use Cline\Relay\Support\Attributes\ContentTypes\Json;
use Cline\Relay\Support\Attributes\ContentTypes\Multipart;
use Cline\Relay\Support\Attributes\ContentTypes\Xml;
use Cline\Relay\Support\Attributes\ContentTypes\Yaml;
use Cline\Relay\Support\Attributes\Idempotent;
use Cline\Relay\Support\Attributes\Methods\Delete;
use Cline\Relay\Support\Attributes\Methods\Get;
use Cline\Relay\Support\Attributes\Methods\Head;
use Cline\Relay\Support\Attributes\Methods\HttpMethod;
use Cline\Relay\Support\Attributes\Methods\Options;
use Cline\Relay\Support\Attributes\Methods\Patch;
use Cline\Relay\Support\Attributes\Methods\Post;
use Cline\Relay\Support\Attributes\Methods\Put;
use Cline\Relay\Support\Contracts\ContentType;
use Cline\Relay\Support\Contracts\IdempotencyKeyGenerator;
use Cline\Relay\Support\Exceptions\AttributeConflictException;
use Cline\Relay\Support\Exceptions\MissingAttributeException;
use Illuminate\Support\Traits\Macroable;
use ReflectionClass;

use function array_map;
use function assert;
use function base64_encode;
use function bin2hex;
use function class_exists;
use function count;
use function dd;
use function dump;
use function is_a;
use function is_string;
use function method_exists;
use function random_bytes;
use function sprintf;

/**
 * Base class for all HTTP requests.
 *
 * @author Brian Faust <brian@cline.sh>
 */
abstract class Request
{
    use Macroable;

    /** @var array<string, string> */
    private array $additionalHeaders = [];

    /** @var array<string, mixed> */
    private array $additionalQuery = [];

    private ?string $idempotencyKey = null;

    private ?Resource $resource = null;

    private ?Connector $connector = null;

    /**
     * Request body (for POST, PUT, PATCH).
     *
     * @return null|array<string, mixed>
     */
    public function body(): ?array
    {
        return null;
    }

    /**
     * Query parameters.
     *
     * @return null|array<string, mixed>
     */
    public function query(): ?array
    {
        return null;
    }

    /**
     * Request headers.
     *
     * @return null|array<string, string>
     */
    public function headers(): ?array
    {
        return null;
    }

    /**
     * Clone this request for modification/retry.
     */
    public function clone(): static
    {
        return clone $this;
    }

    /**
     * Add a header to the request.
     */
    public function withHeader(string $name, string $value): static
    {
        $clone = $this->clone();
        $clone->additionalHeaders[$name] = $value;

        return $clone;
    }

    /**
     * Add multiple headers to the request.
     *
     * @param array<string, string> $headers
     */
    public function withHeaders(array $headers): static
    {
        $clone = $this->clone();
        $clone->additionalHeaders = [...$clone->additionalHeaders, ...$headers];

        return $clone;
    }

    /**
     * Add a query parameter to the request.
     */
    public function withQuery(string $name, mixed $value): static
    {
        $clone = $this->clone();
        $clone->additionalQuery[$name] = $value;

        return $clone;
    }

    /**
     * Add a bearer token header.
     */
    public function withBearerToken(string $token): static
    {
        return $this->withHeader('Authorization', 'Bearer '.$token);
    }

    /**
     * Add basic auth header.
     */
    public function withBasicAuth(string $username, string $password): static
    {
        $credentials = base64_encode(sprintf('%s:%s', $username, $password));

        return $this->withHeader('Authorization', 'Basic '.$credentials);
    }

    /**
     * Set the idempotency key for this request.
     */
    public function withIdempotencyKey(string $key): static
    {
        $clone = $this->clone();
        $clone->idempotencyKey = $key;

        return $clone;
    }

    /**
     * Get the idempotency key.
     */
    public function idempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    /**
     * Set the resource this request belongs to.
     *
     * @internal Called by Resource when sending requests
     */
    public function setResource(Resource $resource): static
    {
        $this->resource = $resource;

        return $this;
    }

    /**
     * Get the resource this request belongs to.
     */
    public function resource(): ?Resource
    {
        return $this->resource;
    }

    /**
     * Set the connector this request is sent through.
     *
     * @internal Called by Connector when sending requests
     */
    public function setConnector(Connector $connector): static
    {
        $this->connector = $connector;

        return $this;
    }

    /**
     * Get the connector this request is sent through.
     *
     * Returns the directly set connector, or falls back to the resource's connector.
     */
    public function connector(): ?Connector
    {
        return $this->connector ?? $this->resource?->connector();
    }

    /**
     * Get all headers including additional ones.
     *
     * @return array<string, string>
     */
    public function allHeaders(): array
    {
        return [...($this->headers() ?? []), ...$this->additionalHeaders];
    }

    /**
     * Get all query parameters including additional ones.
     *
     * @return array<string, mixed>
     */
    public function allQuery(): array
    {
        return [...($this->query() ?? []), ...$this->additionalQuery];
    }

    /**
     * Get the HTTP method from attributes.
     */
    public function method(): string
    {
        $methodAttributes = [
            Get::class,
            Post::class,
            Put::class,
            Patch::class,
            Delete::class,
            Head::class,
            Options::class,
        ];

        $foundMethods = [];

        // Check class hierarchy for HTTP method attributes
        $class = new ReflectionClass($this);

        while ($class !== false) {
            foreach ($methodAttributes as $attributeClass) {
                $attributes = $class->getAttributes($attributeClass);

                if ($attributes !== []) {
                    $foundMethods[] = $attributes[0]->newInstance();
                }
            }

            // Stop once we find method(s) at any level
            if ($foundMethods !== []) {
                break;
            }

            $class = $class->getParentClass();
        }

        if ($foundMethods === []) {
            throw MissingAttributeException::httpMethod(static::class);
        }

        if (count($foundMethods) > 1) {
            throw AttributeConflictException::multipleHttpMethods(
                static::class,
                array_map(fn (HttpMethod $m): string => $m->method(), $foundMethods),
            );
        }

        return $foundMethods[0]->method();
    }

    /**
     * Get the content type from attributes.
     *
     * Resolves content type by walking the inheritance chain:
     * Request (including parent classes) -> Resource -> Connector
     */
    public function contentType(): ?string
    {
        $contentTypeAttributes = [
            Json::class,
            Form::class,
            Multipart::class,
            Xml::class,
            Yaml::class,
        ];

        // Check Request class hierarchy first
        $foundTypes = $this->findContentTypesOnClass(
            new ReflectionClass($this),
            $contentTypeAttributes,
        );

        if ($foundTypes !== []) {
            return $this->validateAndReturnContentType($foundTypes, static::class);
        }

        // Check Resource if available
        if ($this->resource instanceof Resource) {
            $foundTypes = $this->findContentTypesOnClass(
                new ReflectionClass($this->resource),
                $contentTypeAttributes,
            );

            if ($foundTypes !== []) {
                return $this->validateAndReturnContentType($foundTypes, $this->resource::class);
            }
        }

        // Check Connector if available
        $connector = $this->connector();

        if ($connector instanceof Connector) {
            $foundTypes = $this->findContentTypesOnClass(
                new ReflectionClass($connector),
                $contentTypeAttributes,
            );

            if ($foundTypes !== []) {
                return $this->validateAndReturnContentType($foundTypes, $connector::class);
            }
        }

        return null;
    }

    /**
     * Check if this request has a specific attribute.
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
     * Resolve an attribute by walking the inheritance chain: Request -> Resource -> Connector.
     *
     * Returns the first found attribute, allowing requests to override resource settings,
     * and resources to override connector settings.
     *
     * @template T of object
     *
     * @param  class-string<T> $attributeClass
     * @return null|T
     */
    public function resolveAttribute(string $attributeClass): ?object
    {
        // 1. Check Request itself (including parent classes)
        if (($attr = $this->getAttribute($attributeClass)) !== null) {
            return $attr;
        }

        // 2. Check Resource if available
        if (($attr = $this->resource?->getAttribute($attributeClass)) !== null) {
            return $attr;
        }

        // 3. Check Connector if available
        return $this->connector()?->getAttribute($attributeClass);
    }

    /**
     * Check if an attribute exists anywhere in the inheritance chain.
     *
     * @template T of object
     *
     * @param class-string<T> $attributeClass
     */
    public function hasResolvedAttribute(string $attributeClass): bool
    {
        return $this->resolveAttribute($attributeClass) !== null;
    }

    /**
     * Check if this request is marked as idempotent.
     */
    public function isIdempotent(): bool
    {
        $attr = $this->getAttribute(Idempotent::class);

        return $attr instanceof Idempotent && $attr->enabled;
    }

    /**
     * Get the idempotency header name.
     */
    public function idempotencyHeader(): string
    {
        $attr = $this->getAttribute(Idempotent::class);

        return $attr instanceof Idempotent ? $attr->header : 'Idempotency-Key';
    }

    /**
     * Initialize the request (calls boot and validates attributes).
     *
     * @internal
     */
    public function initialize(): void
    {
        $this->boot();
        $this->applyIdempotency();
    }

    /**
     * Dump the request for debugging.
     */
    public function dump(): static
    {
        dump($this->toDebugArray());

        return $this;
    }

    /**
     * Dump the request and die.
     *
     * @codeCoverageIgnore
     */
    public function dd(): never
    {
        dd($this->toDebugArray());
    }

    /**
     * Transform the response after receiving.
     */
    public function transformResponse(Response $response): Response
    {
        return $response;
    }

    /**
     * Create a DTO from the response.
     *
     * Override this method to define how the response should be mapped to a DTO.
     */
    public function createDtoFromResponse(Response $response): mixed
    {
        return null;
    }

    /**
     * The endpoint for this request (without base URL).
     */
    abstract public function endpoint(): string;

    /**
     * Lifecycle hook called before the request is sent.
     */
    protected function boot(): void
    {
        // Override in subclass
    }

    /**
     * Find content type attributes on a class and its parents.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>               $class
     * @param  array<class-string<ContentType>> $attributeClasses
     * @return array<ContentType>
     */
    private function findContentTypesOnClass(ReflectionClass $class, array $attributeClasses): array
    {
        $foundTypes = [];

        while ($class !== false) {
            foreach ($attributeClasses as $attributeClass) {
                $attributes = $class->getAttributes($attributeClass);

                if ($attributes !== []) {
                    $foundTypes[] = $attributes[0]->newInstance();
                }
            }

            // Stop once we find type(s) at any level
            if ($foundTypes !== []) {
                break;
            }

            $class = $class->getParentClass();
        }

        return $foundTypes;
    }

    /**
     * Validate only one content type is defined and return it.
     *
     * @param array<ContentType> $foundTypes
     * @param class-string       $sourceClass
     */
    private function validateAndReturnContentType(array $foundTypes, string $sourceClass): string
    {
        if (count($foundTypes) > 1) {
            throw AttributeConflictException::multipleContentTypes(
                $sourceClass,
                array_map(fn (ContentType $t): string => $t->contentType(), $foundTypes),
            );
        }

        return $foundTypes[0]->contentType();
    }

    /**
     * Apply idempotency key if the request is marked as idempotent.
     */
    private function applyIdempotency(): void
    {
        if (!$this->isIdempotent()) {
            return;
        }

        // Skip if already has a key
        if ($this->idempotencyKey !== null) {
            $this->additionalHeaders[$this->idempotencyHeader()] = $this->idempotencyKey;

            return;
        }

        $attr = $this->getAttribute(Idempotent::class);

        // Check for IdempotencyKeyGenerator class
        if ($attr instanceof Idempotent && $attr->keyMethod !== null) {
            if (class_exists($attr->keyMethod) && is_a($attr->keyMethod, IdempotencyKeyGenerator::class, true)) {
                $generator = new ($attr->keyMethod)();
                $key = $generator->generate($this);
            } elseif (method_exists($this, $attr->keyMethod)) {
                // Check for custom key method
                $key = $this->{$attr->keyMethod}();
            } else {
                // Generate a random key
                $key = bin2hex(random_bytes(16));
            }
        } else {
            // Generate a random key
            $key = bin2hex(random_bytes(16));
        }

        assert(is_string($key));

        $this->idempotencyKey = $key;
        $this->additionalHeaders[$this->idempotencyHeader()] = $key;
    }

    /**
     * Convert to array for debugging.
     *
     * @return array<string, mixed>
     */
    private function toDebugArray(): array
    {
        return [
            'class' => static::class,
            'method' => $this->method(),
            'endpoint' => $this->endpoint(),
            'content_type' => $this->contentType(),
            'headers' => $this->allHeaders(),
            'query' => $this->allQuery(),
            'body' => $this->body(),
        ];
    }
}

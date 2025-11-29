<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Features\Caching;

use Cline\Relay\Core\Connector;
use Cline\Relay\Core\Request;
use Cline\Relay\Support\Attributes\Caching\Cache;
use Cline\Relay\Support\Contracts\CacheKeyResolver;
use Cline\Relay\Support\Exceptions\CacheKeyException;
use ReflectionClass;

use function class_exists;
use function hash;
use function implode;
use function is_a;
use function is_scalar;
use function is_string;
use function mb_strlen;
use function mb_substr;
use function method_exists;
use function preg_replace_callback;
use function serialize;
use function throw_if;
use function throw_unless;

/**
 * Generates cache keys for requests.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class CacheKeyGenerator
{
    public function __construct(
        private CacheConfig $config,
    ) {}

    /**
     * Generate a cache key for a request.
     */
    public function generate(Connector $connector, Request $request): string
    {
        $cacheAttribute = $this->getCacheAttribute($request);

        // Check for custom key resolver in attribute
        if ($cacheAttribute?->keyResolver !== null) {
            $key = $this->resolveCustomKey($request, $cacheAttribute->keyResolver);
        } else {
            $key = $this->generateDefaultKey($connector, $request);
        }

        // Apply prefix
        if ($this->config->prefix !== '') {
            $key = $this->config->prefix.$key;
        }

        // Truncate if needed
        if ($this->config->maxKeyLength !== null && mb_strlen($key) > $this->config->maxKeyLength) {
            return mb_substr($key, 0, $this->config->maxKeyLength - 33).'_'.$this->hash($key);
        }

        return $key;
    }

    /**
     * Get tags for a request.
     *
     * @return array<string>
     */
    public function getTags(Request $request): array
    {
        $cacheAttribute = $this->getCacheAttribute($request);

        return $cacheAttribute instanceof Cache ? $cacheAttribute->tags : [];
    }

    /**
     * Get TTL for a request.
     */
    public function getTtl(Request $request): int
    {
        $cacheAttribute = $this->getCacheAttribute($request);

        return $cacheAttribute instanceof Cache ? $cacheAttribute->ttl : $this->config->defaultTtl;
    }

    /**
     * Resolve a custom key with placeholder substitution.
     */
    private function resolveCustomKey(Request $request, string $keyTemplate): string
    {
        // Check if it's a CacheKeyResolver class
        if (class_exists($keyTemplate) && is_a($keyTemplate, CacheKeyResolver::class, true)) {
            $resolver = new $keyTemplate();

            return $resolver->resolve($request);
        }

        // Check if the key references a method
        if (method_exists($request, $keyTemplate)) {
            $result = $request->{$keyTemplate}();

            throw_unless(is_string($result), CacheKeyException::methodMustReturnString());

            return $result;
        }

        // Replace placeholders with property values
        $resolved = preg_replace_callback('/\{(\w+)\}/', function (array $matches) use ($request): string {
            $property = $matches[1];

            // Try to get property value via reflection
            $reflection = new ReflectionClass($request);

            if ($reflection->hasProperty($property)) {
                $prop = $reflection->getProperty($property);
                $value = $prop->getValue($request);

                throw_if(!is_scalar($value) && $value !== null, CacheKeyException::propertyMustBeScalar($property));

                return (string) $value;
            }

            return $matches[0]; // Keep placeholder if property not found
        }, $keyTemplate);

        if (!is_string($resolved)) {
            return $keyTemplate;
        }

        return $resolved;
    }

    /**
     * Generate the default cache key.
     */
    private function generateDefaultKey(Connector $connector, Request $request): string
    {
        $parts = [
            $connector::class,
            $request->method(),
            $request->endpoint(),
            $this->hash(serialize($request->query() ?? [])),
            $this->hash(serialize($request->body() ?? [])),
        ];

        if ($this->config->includeHeaders) {
            $parts[] = $this->hash(serialize($request->headers()));
        }

        return implode(':', $parts);
    }

    /**
     * Hash a value using the configured algorithm.
     */
    private function hash(string $value): string
    {
        return hash($this->config->hashAlgorithm, $value);
    }

    /**
     * Get the Cache attribute from a request.
     */
    private function getCacheAttribute(Request $request): ?Cache
    {
        $reflection = new ReflectionClass($request);
        $attributes = $reflection->getAttributes(Cache::class);

        if ($attributes === []) {
            return null;
        }

        return $attributes[0]->newInstance();
    }
}

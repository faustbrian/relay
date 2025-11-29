<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Core;

use ReflectionClass;

/**
 * Base class for API resources.
 *
 * Resources group related requests and provide a fluent interface
 * for interacting with specific API endpoints.
 *
 * @author Brian Faust <brian@cline.sh>
 */
abstract class Resource
{
    public function __construct(
        protected readonly Connector $connector,
    ) {}

    /**
     * Get the underlying connector.
     */
    public function connector(): Connector
    {
        return $this->connector;
    }

    /**
     * Check if this resource has a specific attribute.
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
     * Send a request through the connector.
     */
    protected function send(Request $request): Response
    {
        $request->setResource($this);

        return $this->connector->send($request);
    }
}

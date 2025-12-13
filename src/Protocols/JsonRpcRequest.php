<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Protocols;

use Cline\Relay\Core\Request;
use Cline\Relay\Protocols\JsonRpc\IdGenerator;
use Cline\Relay\Protocols\JsonRpc\UlidIdGenerator;
use Cline\Relay\Support\Attributes\ContentTypes\Json;
use Cline\Relay\Support\Attributes\Methods\Post;
use Illuminate\Support\Str;

use function class_basename;

/**
 * Base class for JSON-RPC 2.0 requests.
 *
 * Automatically builds the JSON-RPC body structure with method name
 * derived from the class name or explicitly specified.
 *
 * @author Brian Faust <brian@cline.sh>
 */
#[Post()]
#[Json()]
abstract class JsonRpcRequest extends Request
{
    protected static ?IdGenerator $idGenerator = null;

    protected string $jsonRpcVersion = '2.0';

    protected ?string $jsonRpcMethod = null;

    protected ?string $jsonRpcId = null;

    protected ?string $methodPrefix = null;

    private ?string $resolvedId = null;

    /**
     * Set the ID generator for all JSON-RPC requests.
     */
    public static function useIdGenerator(IdGenerator $generator): void
    {
        static::$idGenerator = $generator;
    }

    /**
     * Get the current ID generator.
     */
    public static function getIdGenerator(): IdGenerator
    {
        return static::$idGenerator ??= new UlidIdGenerator();
    }

    /**
     * Reset to the default ID generator (ULID).
     */
    public static function useDefaultIdGenerator(): void
    {
        static::$idGenerator = null;
    }

    /**
     * Get the JSON-RPC endpoint (usually /rpc or /jsonrpc).
     */
    public function endpoint(): string
    {
        return '/rpc';
    }

    /**
     * Get the JSON-RPC parameters.
     *
     * Override this method to provide custom parameters.
     *
     * @return array<string, mixed>
     */
    public function params(): array
    {
        return [];
    }

    /**
     * Build the JSON-RPC request body.
     *
     * @return array<string, mixed>
     */
    public function body(): array
    {
        $body = [
            'jsonrpc' => $this->jsonRpcVersion,
            'id' => $this->resolveId(),
            'method' => $this->resolveMethod(),
        ];

        $params = $this->params();

        if ($params !== []) {
            $body['params'] = $params;
        }

        return $body;
    }

    /**
     * Set the JSON-RPC method name.
     */
    public function withMethod(string $method): static
    {
        $clone = clone $this;
        $clone->jsonRpcMethod = $method;

        return $clone;
    }

    /**
     * Set the JSON-RPC request ID.
     */
    public function withId(string $id): static
    {
        $clone = clone $this;
        $clone->jsonRpcId = $id;

        return $clone;
    }

    /**
     * Resolve the JSON-RPC request ID.
     */
    protected function resolveId(): string
    {
        if ($this->resolvedId !== null) {
            return $this->resolvedId;
        }

        if ($this->jsonRpcId !== null) {
            return $this->resolvedId = $this->jsonRpcId;
        }

        return $this->resolvedId = static::getIdGenerator()->generate();
    }

    /**
     * Resolve the JSON-RPC method name.
     *
     * By default, derives from class name: GetUsersRequest -> get_users
     */
    protected function resolveMethod(): string
    {
        if ($this->jsonRpcMethod !== null) {
            return $this->jsonRpcMethod;
        }

        $method = Str::of(class_basename(static::class))
            ->before('Request')
            ->snake()
            ->toString();

        if ($this->methodPrefix !== null) {
            return $this->methodPrefix.'.'.$method;
        }

        return $method;
    }
}

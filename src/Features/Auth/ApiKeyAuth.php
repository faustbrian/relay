<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Features\Auth;

use Cline\Relay\Core\Request;
use Cline\Relay\Support\Contracts\Authenticator;

/**
 * API key authentication.
 *
 * Supports placing the API key in either a header or query parameter.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class ApiKeyAuth implements Authenticator
{
    public const string IN_HEADER = 'header';

    public const string IN_QUERY = 'query';

    /**
     * @param string $key  The API key value
     * @param string $name The header or query parameter name
     * @param string $in   Where to place the key: 'header' or 'query'
     */
    public function __construct(
        private string $key,
        private string $name = 'X-API-Key',
        private string $in = self::IN_HEADER,
    ) {}

    /**
     * Create an API key authenticator for header placement.
     */
    public static function inHeader(string $key, string $headerName = 'X-API-Key'): self
    {
        return new self($key, $headerName, self::IN_HEADER);
    }

    /**
     * Create an API key authenticator for query parameter placement.
     */
    public static function inQuery(string $key, string $paramName = 'api_key'): self
    {
        return new self($key, $paramName, self::IN_QUERY);
    }

    public function authenticate(Request $request): Request
    {
        return match ($this->in) {
            self::IN_QUERY => $request->withQuery($this->name, $this->key),
            default => $request->withHeader($this->name, $this->key),
        };
    }

    /**
     * Get the API key.
     */
    public function key(): string
    {
        return $this->key;
    }

    /**
     * Get the header/parameter name.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Get the placement location.
     */
    public function in(): string
    {
        return $this->in;
    }
}

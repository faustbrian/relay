<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Features\Auth;

use Carbon\CarbonImmutable;
use Cline\Relay\Core\Request;
use Cline\Relay\Support\Contracts\Authenticator;
use Closure;
use DateTimeImmutable;

/**
 * JWT (JSON Web Token) authentication.
 *
 * Supports static tokens or dynamic token providers with optional expiry checking.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class JwtAuth implements Authenticator
{
    /** @var null|Closure(): string */
    private ?Closure $tokenProvider = null;

    public function __construct(
        private string $token,
        private ?DateTimeImmutable $expiresAt = null,
    ) {}

    /**
     * Create a JWT authenticator with a static token.
     */
    public static function token(string $token, ?DateTimeImmutable $expiresAt = null): self
    {
        return new self($token, $expiresAt);
    }

    /**
     * Create a JWT authenticator with a dynamic token provider.
     *
     * The provider will be called each time a token is needed, allowing
     * for automatic token refresh.
     *
     * @param Closure(): string $provider
     */
    public static function withProvider(Closure $provider): self
    {
        $instance = new self('');
        $instance->tokenProvider = $provider;

        return $instance;
    }

    public function authenticate(Request $request): Request
    {
        return $request->withBearerToken($this->getToken());
    }

    /**
     * Get the current token.
     */
    public function getToken(): string
    {
        if ($this->tokenProvider instanceof Closure) {
            return ($this->tokenProvider)();
        }

        return $this->token;
    }

    /**
     * Get the token expiry time.
     */
    public function getExpiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    /**
     * Check if the token has expired.
     */
    public function hasExpired(): bool
    {
        if (!$this->expiresAt instanceof DateTimeImmutable) {
            return false;
        }

        return $this->expiresAt <= CarbonImmutable::now();
    }

    /**
     * Check if the token is still valid.
     */
    public function isValid(): bool
    {
        return !$this->hasExpired();
    }

    /**
     * Update the token.
     */
    public function setToken(string $token, ?DateTimeImmutable $expiresAt = null): self
    {
        $this->token = $token;
        $this->expiresAt = $expiresAt;

        return $this;
    }
}

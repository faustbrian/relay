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
use Cline\Relay\Support\Contracts\OAuthAuthenticator;
use Cline\Relay\Support\Exceptions\AuthenticatorSerializationException;
use DateTimeImmutable;

use function array_key_exists;
use function is_array;
use function is_string;
use function serialize;
use function throw_if;
use function throw_unless;
use function unserialize;

/**
 * OAuth access token authenticator.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class AccessTokenAuthenticator implements OAuthAuthenticator
{
    public function __construct(
        private string $accessToken,
        private ?string $refreshToken = null,
        private ?DateTimeImmutable $expiresAt = null,
    ) {}

    public static function unserialize(string $serialized): static
    {
        $data = unserialize($serialized, ['allowed_classes' => false]);

        throw_unless(is_array($data), AuthenticatorSerializationException::invalidSerializedData());

        throw_if(!array_key_exists('accessToken', $data) || !is_string($data['accessToken']), AuthenticatorSerializationException::missingOrInvalidAccessToken());

        $refreshToken = $data['refreshToken'] ?? null;
        throw_if($refreshToken !== null && !is_string($refreshToken), AuthenticatorSerializationException::invalidRefreshToken());

        $expiresAt = null;

        if (array_key_exists('expiresAt', $data) && $data['expiresAt'] !== null) {
            throw_unless(is_string($data['expiresAt']), AuthenticatorSerializationException::invalidExpiresAt());

            $expiresAt = new DateTimeImmutable($data['expiresAt']);
        }

        return new self(
            accessToken: $data['accessToken'],
            refreshToken: $refreshToken,
            expiresAt: $expiresAt,
        );
    }

    public function authenticate(Request $request): Request
    {
        return $request->withBearerToken($this->accessToken);
    }

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    public function getExpiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function hasExpired(): bool
    {
        if (!$this->expiresAt instanceof DateTimeImmutable) {
            return false;
        }

        return $this->expiresAt->getTimestamp() <= CarbonImmutable::now()->getTimestamp();
    }

    public function hasNotExpired(): bool
    {
        return !$this->hasExpired();
    }

    public function isRefreshable(): bool
    {
        return $this->refreshToken !== null;
    }

    public function isNotRefreshable(): bool
    {
        return !$this->isRefreshable();
    }

    public function serialize(): string
    {
        return serialize([
            'accessToken' => $this->accessToken,
            'refreshToken' => $this->refreshToken,
            'expiresAt' => $this->expiresAt?->format('c'),
        ]);
    }
}

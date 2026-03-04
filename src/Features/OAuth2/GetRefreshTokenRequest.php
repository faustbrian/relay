<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Features\OAuth2;

use Cline\Relay\Core\Request;
use Cline\Relay\Support\Attributes\ContentTypes\Form;
use Cline\Relay\Support\Attributes\Methods\Post;

/**
 * Request to refresh an access token.
 *
 * @author Brian Faust <brian@cline.sh>
 */
#[Post(), Form()]
final class GetRefreshTokenRequest extends Request
{
    public function __construct(
        private readonly OAuthConfig $config,
        private readonly string $refreshToken,
    ) {}

    public function endpoint(): string
    {
        return $this->config->getTokenEndpoint();
    }

    /**
     * @return array{grant_type: string, refresh_token: string, client_id: string, client_secret: string}
     */
    public function body(): array
    {
        return [
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->refreshToken,
            'client_id' => $this->config->getClientId(),
            'client_secret' => $this->config->getClientSecret(),
        ];
    }
}

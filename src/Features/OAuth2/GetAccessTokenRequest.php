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

use function array_filter;

/**
 * Request to exchange authorization code for access token.
 *
 * @author Brian Faust <brian@cline.sh>
 */
#[Post(), Form()]
final class GetAccessTokenRequest extends Request
{
    public function __construct(
        private readonly string $code,
        private readonly OAuthConfig $config,
        private readonly ?string $codeVerifier = null,
    ) {}

    public function endpoint(): string
    {
        return $this->config->getTokenEndpoint();
    }

    /**
     * @return array<string, string>
     */
    public function body(): array
    {
        return array_filter([
            'grant_type' => 'authorization_code',
            'code' => $this->code,
            'client_id' => $this->config->getClientId(),
            'client_secret' => $this->config->getClientSecret(),
            'redirect_uri' => $this->config->getRedirectUri(),
            'code_verifier' => $this->codeVerifier,
        ]);
    }
}

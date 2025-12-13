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
use function array_merge;
use function implode;

/**
 * Request for client credentials grant.
 *
 * @author Brian Faust <brian@cline.sh>
 */
#[Post(), Form()]
final class GetClientCredentialsTokenRequest extends Request
{
    /**
     * @param array<string> $scopes
     */
    public function __construct(
        private readonly OAuthConfig $config,
        private readonly array $scopes = [],
        private readonly string $scopeSeparator = ' ',
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
        $body = [
            'grant_type' => 'client_credentials',
            'client_id' => $this->config->getClientId(),
            'client_secret' => $this->config->getClientSecret(),
        ];

        $scopes = array_filter(array_merge($this->config->getDefaultScopes(), $this->scopes));

        if ($scopes !== []) {
            $body['scope'] = implode($this->scopeSeparator, $scopes);
        }

        return $body;
    }
}

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Features\OAuth2;

use Cline\Relay\Core\Request;
use Cline\Relay\Support\Attributes\ContentTypes\Json;
use Cline\Relay\Support\Attributes\Methods\Get;

/**
 * Request to get the authenticated user.
 *
 * @author Brian Faust <brian@cline.sh>
 */
#[Get(), Json()]
final class GetUserRequest extends Request
{
    public function __construct(
        private readonly OAuthConfig $config,
    ) {}

    public function endpoint(): string
    {
        return $this->config->getUserEndpoint();
    }
}

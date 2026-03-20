<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures;

use Cline\Relay\Core\AbstractConnector;
use Cline\Relay\Core\Response;
use Override;

/**
 * AbstractConnector that checks for error key in response body.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class CustomFailureConnector extends AbstractConnector
{
    public function baseUrl(): string
    {
        return 'https://api.example.com';
    }

    #[Override()]
    public function hasRequestFailed(Response $response): bool
    {
        // Custom logic: check for error key in JSON body
        if ($response->json('error') !== null) {
            return true;
        }

        return parent::hasRequestFailed($response);
    }
}

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures;

use Cline\Relay\Core\Connector;
use Cline\Relay\Support\Attributes\ContentTypes\Json;

/**
 * @author Brian Faust <brian@cline.sh>
 */
#[Json()]
final class JsonConnector extends Connector
{
    public function baseUrl(): string
    {
        return 'https://api.example.com';
    }
}

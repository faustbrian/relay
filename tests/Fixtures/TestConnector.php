<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures;

use Cline\Relay\Core\Connector;
use Override;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class TestConnector extends Connector
{
    public function __construct(
        private readonly string $baseUrl = 'https://api.example.com',
    ) {}

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    #[Override()]
    public function defaultHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'User-Agent' => 'Relay/1.0',
        ];
    }
}

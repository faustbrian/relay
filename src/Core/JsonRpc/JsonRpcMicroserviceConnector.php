<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Core\JsonRpc;

use Cline\Relay\Core\Connector;
use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;
use Cline\Relay\Features\Auth\BearerToken;
use Cline\Relay\Support\Attributes\ContentTypes\Json;
use Illuminate\Support\Str;
use Override;

use function config;
use function is_string;
use function sprintf;

/**
 * Base connector for internal microservices.
 *
 * Provides common functionality for microservice communication:
 * - Bearer token authentication from config
 * - Base URL from config
 * - JSON-RPC error detection
 * - Automatic service name derivation
 *
 * @author Brian Faust <brian@cline.sh>
 */
#[Json()]
abstract class JsonRpcMicroserviceConnector extends Connector
{
    protected int $tries = 3;

    protected int $retryInterval = 500;

    public function baseUrl(): string
    {
        /** @var string */
        return $this->configByKey('base_url');
    }

    #[Override()]
    public function defaultConfig(): array
    {
        return [
            'timeout' => 30,
        ];
    }

    #[Override()]
    public function authenticate(Request $request): Request
    {
        $token = $this->configByKey('token');

        if (!is_string($token)) {
            return $request;
        }

        return new BearerToken($token)->authenticate($request);
    }

    #[Override()]
    public function hasRequestFailed(Response $response): bool
    {
        if ($response->clientError() || $response->serverError()) {
            return true;
        }

        return $response->json('error') !== null;
    }

    protected function getServiceName(): string
    {
        return Str::of(static::class)
            ->before('\\Connectors')
            ->afterLast('\\')
            ->snake()
            ->toString();
    }

    protected function configByKey(string $key): mixed
    {
        return config(
            sprintf(
                'services.microservices.%s.%s',
                $this->getServiceName(),
                $key,
            ),
        );
    }
}

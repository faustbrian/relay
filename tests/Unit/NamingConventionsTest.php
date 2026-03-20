<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Relay\Core\AbstractConnector;
use Cline\Relay\Core\AbstractRequest;
use Cline\Relay\Core\AbstractResource;
use Cline\Relay\Core\JsonRpc\AbstractJsonRpcMicroserviceConnector;
use Cline\Relay\Core\JsonRpc\AbstractJsonRpcMicroserviceRequest;
use Cline\Relay\Features\OAuth2\AuthorizationCodeGrantTrait;
use Cline\Relay\Features\OAuth2\ClientCredentialsGrantTrait;
use Cline\Relay\Observability\Debugging\HasDebuggingTrait;
use Cline\Relay\Protocols\AbstractJsonRpcRequest;
use Cline\Relay\Protocols\AbstractSoapRequest;
use Cline\Relay\Protocols\GraphQL\AbstractGraphQLRequest;
use Cline\Relay\Protocols\JsonRpc\IdGeneratorInterface;
use Cline\Relay\Support\Attributes\Methods\HttpMethodInterface;
use Cline\Relay\Support\Contracts\AuthenticatorInterface;
use Cline\Relay\Support\Contracts\BackoffStrategyInterface;
use Cline\Relay\Support\Contracts\CacheKeyResolverInterface;
use Cline\Relay\Support\Contracts\CircuitBreakerPolicyInterface;
use Cline\Relay\Support\Contracts\CircuitBreakerStoreInterface;
use Cline\Relay\Support\Contracts\ContentTypeInterface;
use Cline\Relay\Support\Contracts\DataTransferObjectInterface;
use Cline\Relay\Support\Contracts\IdempotencyKeyGeneratorInterface;
use Cline\Relay\Support\Contracts\MiddlewareInterface;
use Cline\Relay\Support\Contracts\OAuthAuthenticatorInterface;
use Cline\Relay\Support\Contracts\PaginatorInterface;
use Cline\Relay\Support\Contracts\ProtocolInterface;
use Cline\Relay\Support\Contracts\RateLimitStoreInterface;
use Cline\Relay\Support\Contracts\RetryDeciderInterface;
use Cline\Relay\Support\Contracts\RetryPolicyInterface;
use Cline\Relay\Support\Exceptions\AbstractClientException;
use Cline\Relay\Support\Exceptions\AbstractRequestException;
use Cline\Relay\Support\Exceptions\AbstractServerException;

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
it('uses canonical abstract class names', function (string $legacy, string $canonical): void {
    expect(class_exists($canonical))->toBeTrue();
    expect(class_exists($legacy))->toBeFalse();
})->with('abstract-class-mappings');

it('uses canonical interface names', function (string $legacy, string $canonical): void {
    expect(interface_exists($canonical))->toBeTrue();
    expect(interface_exists($legacy))->toBeFalse();
})->with('interface-mappings');

it('uses canonical trait names', function (string $legacy, string $canonical): void {
    expect(trait_exists($canonical))->toBeTrue();
    expect(trait_exists($legacy))->toBeFalse();
})->with('trait-mappings');

dataset('abstract-class-mappings', [
    ['Cline\\Relay\\Core\\Connector', AbstractConnector::class],
    ['Cline\\Relay\\Core\\Request', AbstractRequest::class],
    ['Cline\\Relay\\Core\\Resource', AbstractResource::class],
    ['Cline\\Relay\\Core\\JsonRpc\\JsonRpcMicroserviceConnector', AbstractJsonRpcMicroserviceConnector::class],
    ['Cline\\Relay\\Core\\JsonRpc\\JsonRpcMicroserviceRequest', AbstractJsonRpcMicroserviceRequest::class],
    ['Cline\\Relay\\Protocols\\GraphQL\\GraphQLRequest', AbstractGraphQLRequest::class],
    ['Cline\\Relay\\Protocols\\JsonRpcRequest', AbstractJsonRpcRequest::class],
    ['Cline\\Relay\\Protocols\\SoapRequest', AbstractSoapRequest::class],
    ['Cline\\Relay\\Support\\Exceptions\\ClientException', AbstractClientException::class],
    ['Cline\\Relay\\Support\\Exceptions\\RequestException', AbstractRequestException::class],
    ['Cline\\Relay\\Support\\Exceptions\\ServerException', AbstractServerException::class],
]);

dataset('interface-mappings', [
    ['Cline\\Relay\\Protocols\\JsonRpc\\IdGenerator', IdGeneratorInterface::class],
    ['Cline\\Relay\\Support\\Attributes\\Methods\\HttpMethod', HttpMethodInterface::class],
    ['Cline\\Relay\\Support\\Contracts\\Authenticator', AuthenticatorInterface::class],
    ['Cline\\Relay\\Support\\Contracts\\BackoffStrategy', BackoffStrategyInterface::class],
    ['Cline\\Relay\\Support\\Contracts\\CacheKeyResolver', CacheKeyResolverInterface::class],
    ['Cline\\Relay\\Support\\Contracts\\CircuitBreakerPolicy', CircuitBreakerPolicyInterface::class],
    ['Cline\\Relay\\Support\\Contracts\\CircuitBreakerStore', CircuitBreakerStoreInterface::class],
    ['Cline\\Relay\\Support\\Contracts\\ContentType', ContentTypeInterface::class],
    ['Cline\\Relay\\Support\\Contracts\\DataTransferObject', DataTransferObjectInterface::class],
    ['Cline\\Relay\\Support\\Contracts\\IdempotencyKeyGenerator', IdempotencyKeyGeneratorInterface::class],
    ['Cline\\Relay\\Support\\Contracts\\Middleware', MiddlewareInterface::class],
    ['Cline\\Relay\\Support\\Contracts\\OAuthAuthenticator', OAuthAuthenticatorInterface::class],
    ['Cline\\Relay\\Support\\Contracts\\Paginator', PaginatorInterface::class],
    ['Cline\\Relay\\Support\\Contracts\\Protocol', ProtocolInterface::class],
    ['Cline\\Relay\\Support\\Contracts\\RateLimitStore', RateLimitStoreInterface::class],
    ['Cline\\Relay\\Support\\Contracts\\RetryDecider', RetryDeciderInterface::class],
    ['Cline\\Relay\\Support\\Contracts\\RetryPolicy', RetryPolicyInterface::class],
]);

dataset('trait-mappings', [
    ['Cline\\Relay\\Features\\OAuth2\\AuthorizationCodeGrant', AuthorizationCodeGrantTrait::class],
    ['Cline\\Relay\\Features\\OAuth2\\ClientCredentialsGrant', ClientCredentialsGrantTrait::class],
    ['Cline\\Relay\\Observability\\Debugging\\HasDebugging', HasDebuggingTrait::class],
]);

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Protocols\GraphQL;

use Cline\Relay\Core\Request;
use Cline\Relay\Support\Attributes\ContentTypes\Json;
use Cline\Relay\Support\Attributes\Methods\Post;

/**
 * Base class for GraphQL requests.
 *
 * @author Brian Faust <brian@cline.sh>
 */
#[Post(), Json()]
abstract class GraphQLRequest extends Request
{
    protected string $graphqlEndpoint = '/graphql';

    /**
     * Query variables.
     *
     * @return null|array<string, mixed>
     */
    public function variables(): ?array
    {
        return null;
    }

    /**
     * The operation name (for documents with multiple operations).
     */
    public function operationName(): ?string
    {
        return null;
    }

    /**
     * Get the endpoint.
     */
    public function endpoint(): string
    {
        return $this->graphqlEndpoint;
    }

    /**
     * Build the request body.
     *
     * @return array<string, mixed>
     */
    public function body(): array
    {
        $body = [
            'query' => $this->graphqlQuery(),
        ];

        $variables = $this->variables();

        if ($variables !== null) {
            $body['variables'] = $variables;
        }

        $operationName = $this->operationName();

        if ($operationName !== null) {
            $body['operationName'] = $operationName;
        }

        return $body;
    }

    /**
     * Set a custom GraphQL endpoint.
     */
    public function withEndpoint(string $endpoint): static
    {
        $clone = $this->clone();
        $clone->graphqlEndpoint = $endpoint;

        return $clone;
    }

    /**
     * The GraphQL query string.
     */
    abstract public function graphqlQuery(): string;
}

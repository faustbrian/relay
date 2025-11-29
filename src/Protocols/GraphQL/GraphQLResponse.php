<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Protocols\GraphQL;

use Cline\Relay\Core\Response;

use function array_key_exists;
use function array_map;
use function data_get;
use function is_array;
use function is_string;

/**
 * Wrapper for GraphQL responses with specialized accessors.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class GraphQLResponse
{
    public function __construct(
        private Response $response,
    ) {}

    /**
     * Get the underlying HTTP response.
     */
    public function response(): Response
    {
        return $this->response;
    }

    /**
     * Get the data from the response.
     *
     * @return ($key is null ? null|array<string, mixed> : mixed)
     */
    public function data(?string $key = null): mixed
    {
        $data = $this->response->json('data');

        if ($key === null) {
            return $data;
        }

        return data_get($data, $key);
    }

    /**
     * Get the errors from the response.
     *
     * @return array<int, array<string, mixed>>
     */
    /**
     * @return array<int, array<string, mixed>>
     */
    public function errors(): array
    {
        $errors = $this->response->json('errors');

        if (!is_array($errors)) {
            return [];
        }

        /** @var array<int, array<string, mixed>> */
        return $errors;
    }

    /**
     * Check if the response has errors.
     */
    public function hasErrors(): bool
    {
        return $this->errors() !== [];
    }

    /**
     * Get the first error message.
     */
    public function firstError(): ?string
    {
        $errors = $this->errors();

        if (!array_key_exists(0, $errors) || !array_key_exists('message', $errors[0])) {
            return null;
        }

        $message = $errors[0]['message'];

        return is_string($message) ? $message : null;
    }

    /**
     * Get all error messages.
     *
     * @return array<string>
     */
    public function errorMessages(): array
    {
        $errors = $this->errors();

        return array_map(
            /** @param array<string, mixed> $error */
            static fn (array $error): string => array_key_exists('message', $error) && is_string($error['message'])
                ? $error['message']
                : 'Unknown error',
            $errors,
        );
    }

    /**
     * Get extensions from the response.
     *
     * @return array<string, mixed>
     */
    public function extensions(): array
    {
        $extensions = $this->response->json('extensions');

        // @phpstan-ignore-next-line - Response::json() PHPDoc claims array but can return null
        return is_array($extensions) ? $extensions : [];
    }

    /**
     * Check if the request was successful (has data and no errors).
     */
    public function successful(): bool
    {
        return $this->response->ok() && !$this->hasErrors();
    }

    /**
     * Check if the request failed.
     */
    public function failed(): bool
    {
        return !$this->successful();
    }

    /**
     * Get the HTTP status code.
     */
    public function status(): int
    {
        return $this->response->status();
    }

    /**
     * Get the raw JSON response.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        /** @var array<string, mixed> */
        return $this->response->json();
    }
}

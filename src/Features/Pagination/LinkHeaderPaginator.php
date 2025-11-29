<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Features\Pagination;

use Cline\Relay\Core\Response;
use Cline\Relay\Support\Attributes\Pagination\LinkPagination;
use Cline\Relay\Support\Contracts\Paginator;

use const PHP_URL_QUERY;

use function array_values;
use function count;
use function explode;
use function is_array;
use function mb_trim;
use function parse_str;
use function parse_url;
use function preg_match;

/**
 * Link header pagination strategy (GitHub/REST style).
 *
 * Parses the Link header for pagination URLs.
 * Example: <https://api.github.com/users?page=2>; rel="next", <https://api.github.com/users?page=5>; rel="last"
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class LinkHeaderPaginator implements Paginator
{
    public function __construct(
        private LinkPagination $config,
    ) {}

    public function getNextPage(Response $response): ?array
    {
        $nextUrl = $this->parseLinkHeader($response, 'next');

        if ($nextUrl === null) {
            return null;
        }

        // Parse the query string from the next URL
        $queryString = parse_url($nextUrl, PHP_URL_QUERY);

        if ($queryString === null || $queryString === false) {
            return null;
        }

        parse_str($queryString, $params);

        // PHPStan incorrectly infers parse_str output as array<int|string, mixed>
        // but query string params always produce string keys
        /** @var array<string, mixed> */ // @phpstan-ignore varTag.nativeType
        return $params;
    }

    public function getItems(Response $response): array
    {
        $items = $response->json($this->config->dataKey);

        // If dataKey is empty, the response might be the array itself
        if (!is_array($items)) {
            $items = $response->json();
        }

        // Ensure we return an array, defaulting to empty if not an array
        if (!is_array($items)) {
            return [];
        }

        // API responses typically return sequential arrays of items
        /** @var array<int, mixed> */
        return array_values($items);
    }

    public function hasMorePages(Response $response): bool
    {
        return $this->parseLinkHeader($response, 'next') !== null;
    }

    /**
     * Parse the Link header and extract the URL for a specific relation.
     */
    private function parseLinkHeader(Response $response, string $rel): ?string
    {
        $linkHeader = $response->header('Link');

        if ($linkHeader === null) {
            return null;
        }

        // Parse Link header format: <url>; rel="next", <url>; rel="last"
        $links = explode(',', $linkHeader);

        foreach ($links as $link) {
            $parts = explode(';', mb_trim($link));

            if (count($parts) < 2) {
                continue;
            }

            $url = mb_trim($parts[0], ' <>');
            $relPart = mb_trim($parts[1]);

            // Check if this is the relation we're looking for
            if (preg_match('/rel\s*=\s*["\']?(\w+)["\']?/', $relPart, $matches) && $matches[1] === $rel) {
                return $url;
            }
        }

        return null;
    }
}

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Contracts;

use Cline\Relay\Core\Response;

/**
 * Interface for custom pagination strategies.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface Paginator
{
    /**
     * Get the parameters for the next page, or null if no more pages.
     *
     * @return null|array<string, mixed>
     */
    public function getNextPage(Response $response): ?array;

    /**
     * Extract items from the response.
     *
     * @return array<int, mixed>
     */
    public function getItems(Response $response): array;

    /**
     * Check if there are more pages.
     */
    public function hasMorePages(Response $response): bool;
}

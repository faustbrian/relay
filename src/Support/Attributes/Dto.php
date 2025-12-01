<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Attributes;

use Attribute;

/**
 * Define the DTO class to map responses to.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Dto
{
    /**
     * @param class-string $class   The DTO class to map to
     * @param null|string  $dataKey The key in the response JSON to extract (e.g., 'data')
     */
    public function __construct(
        public string $class,
        public ?string $dataKey = null,
    ) {}
}

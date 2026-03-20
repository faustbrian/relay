<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\Protocols\Microservice;

use Cline\Struct\AbstractData;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class CreateUserData extends AbstractData
{
    public function __construct(
        public string $name,
        public ?string $email,
    ) {}
}

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures;

use Cline\Relay\Core\Request;
use Cline\Relay\Support\Attributes\ContentTypes\Json;
use Cline\Relay\Support\Attributes\Methods\Post;
use Cline\Relay\Support\Attributes\ThrowOnError;

/**
 * @author Brian Faust <brian@cline.sh>
 */
#[Post(), Json(), ThrowOnError()]
final class CreateUserRequest extends Request
{
    public function __construct(
        private readonly string $name,
        private readonly string $email,
    ) {}

    public function endpoint(): string
    {
        return '/users';
    }

    public function body(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
        ];
    }
}

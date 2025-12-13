<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures;

use Cline\Relay\Core\Request;
use Cline\Relay\Support\Attributes\ContentTypes\Form;
use Cline\Relay\Support\Attributes\ContentTypes\Json;
use Cline\Relay\Support\Attributes\Methods\Get;

/**
 * @author Brian Faust <brian@cline.sh>
 */
#[Get()]
#[Json()]
#[Form()]
final class MultipleContentTypesRequest extends Request
{
    public function endpoint(): string
    {
        return '/test';
    }
}

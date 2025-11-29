<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures;

use Cline\Relay\Core\Resource;
use Cline\Relay\Support\Attributes\ContentTypes\Xml;

/**
 * @author Brian Faust <brian@cline.sh>
 */
#[Xml()]
final class XmlResource extends Resource {}

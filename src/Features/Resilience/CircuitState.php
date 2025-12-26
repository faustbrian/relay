<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Features\Resilience;

/**
 * Circuit breaker states.
 *
 * @author Brian Faust <brian@cline.sh>
 */
enum CircuitState: string
{
    case Closed = 'closed';

    case Open = 'open';

    case HalfOpen = 'half-open';
}

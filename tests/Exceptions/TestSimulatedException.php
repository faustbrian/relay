<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Exceptions;

use RuntimeException;

/**
 * Exception for simulated test failures.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class TestSimulatedException extends RuntimeException
{
    public static function somethingWentWrong(): self
    {
        return new self('Something went wrong');
    }
}

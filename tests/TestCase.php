<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use Cline\Relay\RelayServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

/**
 * @internal
 *
 * @author Brian Faust <brian@cline.sh>
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * @param  mixed                    $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            RelayServiceProvider::class,
        ];
    }
}

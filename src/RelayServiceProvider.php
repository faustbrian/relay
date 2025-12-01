<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay;

use Cline\Relay\Console\Commands\MakeConnectorCommand;
use Cline\Relay\Console\Commands\MakeIntegrationCommand;
use Cline\Relay\Console\Commands\MakeRequestCommand;
use Cline\Relay\Console\Commands\MakeResourceCommand;
use Override;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Service provider for the Relay package.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class RelayServiceProvider extends PackageServiceProvider
{
    #[Override()]
    public function configurePackage(Package $package): void
    {
        $package
            ->name('relay')
            ->hasConfigFile()
            ->hasCommands([
                MakeConnectorCommand::class,
                MakeRequestCommand::class,
                MakeResourceCommand::class,
                MakeIntegrationCommand::class,
            ]);
    }
}

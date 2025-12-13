<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Console\Commands;

use Illuminate\Console\Command;

use function explode;
use function is_dir;
use function mb_trim;
use function mkdir;
use function sprintf;

/**
 * Artisan command to scaffold a complete API integration.
 *
 * Creates a full integration structure with connector, resources,
 * requests, and DTOs following best practices.
 *
 * ```bash
 * # Create a basic integration
 * php artisan relay:integration GitHub
 *
 * # Create an integration with OAuth2
 * php artisan relay:integration GitHub --oauth
 *
 * # Create an integration with specific resources
 * php artisan relay:integration GitHub --resources=Users,Repositories,Issues
 *
 * # Create a full-featured integration
 * php artisan relay:integration Stripe --oauth --cache --rate-limit
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class MakeIntegrationCommand extends Command
{
    /**
     * The console command signature with options.
     */
    protected $signature = 'relay:integration
                            {name : The name of the integration}
                            {--oauth : Include OAuth2 authentication}
                            {--bearer : Include Bearer token authentication}
                            {--basic : Include Basic authentication}
                            {--api-key : Include API key authentication}
                            {--cache : Include caching support}
                            {--rate-limit : Include rate limiting}
                            {--resilience : Include circuit breaker and retry}
                            {--middleware : Include middleware pipeline}
                            {--resources= : Comma-separated list of resources to create}
                            {--graphql : Create a GraphQL-based integration}
                            {--jsonrpc : Create a JSON-RPC integration}
                            {--soap : Create a SOAP-based integration}';

    /**
     * The console command description.
     */
    protected $description = 'Scaffold a complete API integration with connector, resources, and requests';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = $this->argument('name');

        $this->info('Creating integration: '.$name);
        $this->newLine();

        // Create directory structure
        $this->createDirectoryStructure($name);

        // Create connector
        $this->createConnector($name);

        // Create base resource
        $this->createBaseResource($name);

        // Create resources if specified
        if ($this->option('resources')) {
            $this->createResources($name);
        }

        // Create example request
        $this->createExampleRequest($name);

        $this->newLine();
        $this->info(sprintf('Integration [%s] created successfully!', $name));
        $this->newLine();

        $this->displayStructure($name);

        return self::SUCCESS;
    }

    /**
     * Create the directory structure for the integration.
     */
    private function createDirectoryStructure(string $name): void
    {
        $basePath = $this->laravel->basePath('app/Http/Integrations/'.$name);

        $directories = [
            $basePath,
            $basePath.'/Requests',
            $basePath.'/Resources',
            $basePath.'/Dto',
        ];

        foreach ($directories as $directory) {
            if (is_dir($directory)) {
                continue;
            }

            mkdir($directory, 0o755, true);
        }

        $this->components->task('Creating directory structure');
    }

    /**
     * Create the connector class.
     */
    private function createConnector(string $name): void
    {
        $options = ['name' => $name];

        if ($this->option('oauth')) {
            $options['--oauth'] = true;
        } elseif ($this->option('bearer')) {
            $options['--bearer'] = true;
        } elseif ($this->option('basic')) {
            $options['--basic'] = true;
        } elseif ($this->option('api-key')) {
            $options['--api-key'] = true;
        }

        if ($this->option('cache')) {
            $options['--cache'] = true;
        }

        if ($this->option('rate-limit')) {
            $options['--rate-limit'] = true;
        }

        if ($this->option('resilience')) {
            $options['--resilience'] = true;
        }

        if ($this->option('middleware')) {
            $options['--middleware'] = true;
        }

        $this->callSilently('relay:connector', $options);
        $this->components->task(sprintf('Creating %sConnector', $name));
    }

    /**
     * Create the base resource class.
     */
    private function createBaseResource(string $name): void
    {
        $this->callSilently('relay:resource', [
            'name' => 'Base',
            'connector' => $name,
        ]);
        $this->components->task('Creating BaseResource');
    }

    /**
     * Create specified resources.
     */
    private function createResources(string $name): void
    {
        $resources = explode(',', $this->option('resources'));

        foreach ($resources as $resource) {
            $resource = mb_trim($resource);

            if ($resource === '') {
                continue;
            }

            $this->callSilently('relay:resource', [
                'name' => $resource,
                'connector' => $name,
                '--crud' => true,
                '--requests' => true,
            ]);

            $this->components->task(sprintf('Creating %sResource with CRUD requests', $resource));
        }
    }

    /**
     * Create an example request.
     */
    private function createExampleRequest(string $name): void
    {
        $options = [
            'name' => 'Example',
            'connector' => $name,
        ];

        if ($this->option('graphql')) {
            $options['--graphql'] = true;
        } elseif ($this->option('jsonrpc')) {
            $options['--jsonrpc'] = true;
        } elseif ($this->option('soap')) {
            $options['--soap'] = true;
        }

        $this->callSilently('relay:request', $options);
        $this->components->task('Creating ExampleRequest');
    }

    /**
     * Display the created structure.
     */
    private function displayStructure(string $name): void
    {
        $this->line(sprintf('  <fg=gray>app/Http/Integrations/%s/</>', $name));
        $this->line(sprintf('  <fg=gray>├──</> %sConnector.php', $name));
        $this->line("  <fg=gray>\u{251C}\u{2500}\u{2500}</> Requests/");
        $this->line("  <fg=gray>\u{2502}   \u{2514}\u{2500}\u{2500}</> ExampleRequest.php");
        $this->line("  <fg=gray>\u{251C}\u{2500}\u{2500}</> Resources/");
        $this->line("  <fg=gray>\u{2502}   \u{2514}\u{2500}\u{2500}</> BaseResource.php");
        $this->line("  <fg=gray>\u{2514}\u{2500}\u{2500}</> Dto/");

        if (!$this->option('resources')) {
            return;
        }

        $resources = explode(',', $this->option('resources'));
        $this->newLine();
        $this->line('  <fg=yellow>Resources created:</>');

        foreach ($resources as $resource) {
            $resource = mb_trim($resource);

            if ($resource === '') {
                continue;
            }

            $this->line(sprintf('  <fg=gray>  -</> %sResource', $resource));
        }
    }
}

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Override;

use function is_dir;
use function mb_strtolower;
use function mb_substr;
use function sprintf;
use function str_ends_with;
use function str_replace;

/**
 * Artisan command to generate new Relay resource files.
 *
 * Creates resource classes that group related requests for a connector.
 *
 * ```bash
 * # Create a basic resource
 * php artisan relay:resource Users GitHub
 *
 * # Create a resource with CRUD methods
 * php artisan relay:resource Users GitHub --crud
 *
 * # Create a resource with pagination support
 * php artisan relay:resource Users GitHub --paginate
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class MakeResourceCommand extends GeneratorCommand
{
    /**
     * The console command name.
     */
    protected $name = 'relay:resource';

    /**
     * The console command signature with options.
     */
    protected $signature = 'relay:resource
                            {name : The name of the resource}
                            {connector : The connector this resource belongs to}
                            {--crud : Create a resource with CRUD methods}
                            {--paginate : Create a resource with pagination support}
                            {--requests : Also create associated request classes}';

    /**
     * The console command description.
     */
    protected $description = 'Create a new Relay resource class';

    /**
     * The type of class being generated.
     */
    protected $type = 'Resource';

    /**
     * Execute the console command.
     */
    #[Override()]
    public function handle(): bool
    {
        // Ensure the directory exists
        $this->makeDirectory($this->getPath($this->qualifyClass($this->getNameInput())));

        $result = parent::handle() ?? true;

        if ($result && $this->option('requests') && $this->option('crud')) {
            $connector = $this->argument('connector');
            $resource = $this->getResourceName();

            // Create CRUD requests
            $this->call('relay:request', [
                'name' => 'Get'.$resource,
                'connector' => $connector,
                '--method' => 'get',
            ]);

            $this->call('relay:request', [
                'name' => sprintf('List%ss', $resource),
                'connector' => $connector,
                '--method' => 'get',
                '--paginate' => true,
            ]);

            $this->call('relay:request', [
                'name' => 'Create'.$resource,
                'connector' => $connector,
                '--method' => 'post',
                '--json' => true,
            ]);

            $this->call('relay:request', [
                'name' => 'Update'.$resource,
                'connector' => $connector,
                '--method' => 'put',
                '--json' => true,
            ]);

            $this->call('relay:request', [
                'name' => 'Delete'.$resource,
                'connector' => $connector,
                '--method' => 'delete',
            ]);
        }

        return $result;
    }

    /**
     * Get the stub file for the generator.
     */
    #[Override()]
    protected function getStub(): string
    {
        $stubsPath = $this->resolveStubPath();

        if ($this->option('crud')) {
            return $stubsPath.'/resource.crud.stub';
        }

        if ($this->option('paginate')) {
            return $stubsPath.'/resource.paginated.stub';
        }

        return $stubsPath.'/resource.stub';
    }

    /**
     * Get the default namespace for the class.
     */
    #[Override()]
    protected function getDefaultNamespace($rootNamespace): string
    {
        $connector = $this->argument('connector');

        return $rootNamespace.'\\Http\\Integrations\\'.$connector.'\\Resources';
    }

    /**
     * Get the destination class path.
     */
    #[Override()]
    protected function getPath($name): string
    {
        $connector = $this->argument('connector');
        $resourceName = $this->getNameInput();

        return $this->laravel->basePath(sprintf('app/Http/Integrations/%s/Resources/%s.php', $connector, $resourceName));
    }

    /**
     * Get the desired class name from the input.
     */
    #[Override()]
    protected function getNameInput(): string
    {
        $name = parent::getNameInput();

        // Remove 'Resource' suffix if provided
        if (str_ends_with($name, 'Resource')) {
            $name = mb_substr($name, 0, -8);
        }

        return $name.'Resource';
    }

    /**
     * Get the base resource name (without Resource suffix).
     */
    protected function getResourceName(): string
    {
        $name = parent::getNameInput();

        if (str_ends_with($name, 'Resource')) {
            return mb_substr($name, 0, -8);
        }

        return $name;
    }

    /**
     * Build the class with the given name.
     */
    #[Override()]
    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);

        return $this->replaceResourcePlaceholders($stub);
    }

    /**
     * Resolve the fully-qualified path to the stub.
     */
    private function resolveStubPath(): string
    {
        $customPath = $this->laravel->basePath('stubs/relay');

        return is_dir($customPath) ? $customPath : __DIR__.'/../../../stubs';
    }

    /**
     * Replace resource-specific placeholders.
     */
    private function replaceResourcePlaceholders(string $stub): string
    {
        $connector = $this->argument('connector');
        $resourceName = $this->getResourceName();
        $resourceNameLower = mb_strtolower($resourceName);
        $resourceNamePlural = $resourceNameLower.'s';

        return str_replace(
            [
                '{{ connector }}',
                '{{connector}}',
                '{{ resourceName }}',
                '{{resourceName}}',
                '{{ resourceNameLower }}',
                '{{resourceNameLower}}',
                '{{ resourceNamePlural }}',
                '{{resourceNamePlural}}',
            ],
            [
                $connector,
                $connector,
                $resourceName,
                $resourceName,
                $resourceNameLower,
                $resourceNameLower,
                $resourceNamePlural,
                $resourceNamePlural,
            ],
            $stub,
        );
    }
}

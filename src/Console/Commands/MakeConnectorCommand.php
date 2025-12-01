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
use function mb_substr;
use function sprintf;
use function str_ends_with;
use function str_replace;

/**
 * Artisan command to generate new Relay connector files.
 *
 * Creates connector classes with configurable authentication, caching,
 * rate limiting, and other features via command options.
 *
 * ```bash
 * # Create a basic connector
 * php artisan relay:connector GitHub
 *
 * # Create a connector with OAuth2 support
 * php artisan relay:connector Stripe --oauth
 *
 * # Create a connector with caching
 * php artisan relay:connector Weather --cache
 *
 * # Create a connector with rate limiting
 * php artisan relay:connector Twitter --rate-limit
 *
 * # Create a connector with all features
 * php artisan relay:connector API --oauth --cache --rate-limit
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class MakeConnectorCommand extends GeneratorCommand
{
    /**
     * The console command name.
     */
    protected $name = 'relay:connector';

    /**
     * The console command signature with options.
     */
    protected $signature = 'relay:connector
                            {name : The name of the connector}
                            {--oauth : Create a connector with OAuth2 authentication}
                            {--bearer : Create a connector with Bearer token authentication}
                            {--basic : Create a connector with Basic authentication}
                            {--api-key : Create a connector with API key authentication}
                            {--cache : Create a connector with caching support}
                            {--rate-limit : Create a connector with rate limiting}
                            {--resilience : Create a connector with circuit breaker and retry}
                            {--middleware : Create a connector with middleware pipeline}
                            {--resource : Also create a base resource class}';

    /**
     * The console command description.
     */
    protected $description = 'Create a new Relay connector class';

    /**
     * The type of class being generated.
     */
    protected $type = 'Connector';

    /**
     * Execute the console command.
     */
    #[Override()]
    public function handle(): bool
    {
        // Ensure the directory exists
        $this->makeDirectory($this->getPath($this->qualifyClass($this->getNameInput())));

        $result = parent::handle() ?? true;

        if ($result && $this->option('resource')) {
            $this->call('relay:resource', [
                'name' => $this->getNameInput(),
                'connector' => $this->getNameInput(),
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

        if ($this->option('oauth')) {
            return $stubsPath.'/connector.oauth.stub';
        }

        if ($this->option('bearer')) {
            return $stubsPath.'/connector.bearer.stub';
        }

        if ($this->option('basic')) {
            return $stubsPath.'/connector.basic.stub';
        }

        if ($this->option('api-key')) {
            return $stubsPath.'/connector.api-key.stub';
        }

        if ($this->option('cache')) {
            return $stubsPath.'/connector.cache.stub';
        }

        if ($this->option('rate-limit')) {
            return $stubsPath.'/connector.rate-limit.stub';
        }

        if ($this->option('resilience')) {
            return $stubsPath.'/connector.resilience.stub';
        }

        if ($this->option('middleware')) {
            return $stubsPath.'/connector.middleware.stub';
        }

        return $stubsPath.'/connector.stub';
    }

    /**
     * Get the default namespace for the class.
     */
    #[Override()]
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\Http\\Integrations\\'.$this->getNameInput();
    }

    /**
     * Get the destination class path.
     */
    #[Override()]
    protected function getPath($name): string
    {
        $name = $this->getNameInput();

        return $this->laravel->basePath(sprintf('app/Http/Integrations/%s/%sConnector.php', $name, $name));
    }

    /**
     * Get the desired class name from the input.
     */
    #[Override()]
    protected function getNameInput(): string
    {
        $name = parent::getNameInput();

        // Remove 'Connector' suffix if provided
        if (str_ends_with($name, 'Connector')) {
            return mb_substr($name, 0, -9);
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

        return $this->replaceConnectorName($stub);
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
     * Replace the connector name placeholder.
     */
    private function replaceConnectorName(string $stub): string
    {
        $name = $this->getNameInput();

        return str_replace(
            ['{{ connectorName }}', '{{connectorName}}'],
            $name,
            $stub,
        );
    }
}

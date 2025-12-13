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

use function implode;
use function is_dir;
use function mb_strtolower;
use function mb_strtoupper;
use function mb_substr;
use function sprintf;
use function str_ends_with;
use function str_replace;
use function ucfirst;

/**
 * Artisan command to generate new Relay request files.
 *
 * Creates request classes with configurable HTTP methods, content types,
 * pagination, caching, resilience, and protocol support.
 *
 * ```bash
 * # Create a basic GET request
 * php artisan relay:request GetUser GitHub
 *
 * # Create a POST request with JSON body
 * php artisan relay:request CreateUser GitHub --method=post --json
 *
 * # Create a request with pagination
 * php artisan relay:request ListUsers GitHub --paginate
 *
 * # Create a GraphQL request
 * php artisan relay:request GetUserQuery GitHub --graphql
 *
 * # Create a JSON-RPC request
 * php artisan relay:request GetUsers GitHub --jsonrpc
 *
 * # Create a SOAP request
 * php artisan relay:request GetWeather Weather --soap
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class MakeRequestCommand extends GeneratorCommand
{
    /**
     * The console command name.
     */
    protected $name = 'relay:request';

    /**
     * The console command signature with options.
     */
    protected $signature = 'relay:request
                            {name : The name of the request}
                            {connector : The connector this request belongs to}
                            {--method=get : The HTTP method (get, post, put, patch, delete)}
                            {--json : Use JSON content type}
                            {--form : Use form-urlencoded content type}
                            {--multipart : Use multipart/form-data content type}
                            {--xml : Use XML content type}
                            {--graphql : Create a GraphQL request}
                            {--jsonrpc : Create a JSON-RPC request}
                            {--soap : Create a SOAP request}
                            {--paginate : Add pagination support}
                            {--cursor : Add cursor pagination support}
                            {--offset : Add offset pagination support}
                            {--cache : Add caching attributes}
                            {--retry : Add retry attributes}
                            {--circuit : Add circuit breaker attributes}
                            {--idempotent : Add idempotency support}
                            {--dto : Add DTO mapping}
                            {--throw : Add throw on error attribute}
                            {--stream : Create a streaming request}';

    /**
     * The console command description.
     */
    protected $description = 'Create a new Relay request class';

    /**
     * The type of class being generated.
     */
    protected $type = 'Request';

    /**
     * Execute the console command.
     */
    #[Override()]
    public function handle(): bool
    {
        // Ensure the directory exists
        $this->makeDirectory($this->getPath($this->qualifyClass($this->getNameInput())));

        return parent::handle() ?? true;
    }

    /**
     * Get the stub file for the generator.
     */
    #[Override()]
    protected function getStub(): string
    {
        $stubsPath = $this->resolveStubPath();

        // Protocol-specific stubs take precedence
        if ($this->option('graphql')) {
            return $stubsPath.'/request.graphql.stub';
        }

        if ($this->option('jsonrpc')) {
            return $stubsPath.'/request.jsonrpc.stub';
        }

        if ($this->option('soap')) {
            return $stubsPath.'/request.soap.stub';
        }

        // Feature-specific stubs
        if ($this->option('paginate')) {
            return $stubsPath.'/request.paginated.stub';
        }

        if ($this->option('cursor')) {
            return $stubsPath.'/request.cursor.stub';
        }

        if ($this->option('offset')) {
            return $stubsPath.'/request.offset.stub';
        }

        if ($this->option('dto')) {
            return $stubsPath.'/request.dto.stub';
        }

        if ($this->option('stream')) {
            return $stubsPath.'/request.stream.stub';
        }

        // Method-based stubs
        $method = mb_strtolower($this->option('method'));

        return match ($method) {
            'post', 'put', 'patch' => $stubsPath.'/request.body.stub',
            'delete' => $stubsPath.'/request.delete.stub',
            default => $stubsPath.'/request.stub',
        };
    }

    /**
     * Get the default namespace for the class.
     */
    #[Override()]
    protected function getDefaultNamespace($rootNamespace): string
    {
        $connector = $this->argument('connector');

        return $rootNamespace.'\\Http\\Integrations\\'.$connector.'\\Requests';
    }

    /**
     * Get the destination class path.
     */
    #[Override()]
    protected function getPath($name): string
    {
        $connector = $this->argument('connector');
        $requestName = $this->getNameInput();

        return $this->laravel->basePath(sprintf('app/Http/Integrations/%s/Requests/%s.php', $connector, $requestName));
    }

    /**
     * Build the class with the given name.
     */
    #[Override()]
    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);

        $stub = $this->replaceMethod($stub);
        $stub = $this->replaceContentType($stub);
        $stub = $this->replaceAttributes($stub);

        return $this->replaceConnector($stub);
    }

    /**
     * Get the desired class name from the input.
     */
    #[Override()]
    protected function getNameInput(): string
    {
        $name = parent::getNameInput();

        // Remove 'Request' suffix if provided
        if (str_ends_with($name, 'Request')) {
            $name = mb_substr($name, 0, -7);
        }

        return $name.'Request';
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
     * Replace the HTTP method placeholder.
     */
    private function replaceMethod(string $stub): string
    {
        $method = mb_strtoupper($this->option('method'));
        $methodClass = ucfirst(mb_strtolower($this->option('method')));

        return str_replace(
            ['{{ method }}', '{{method}}', '{{ methodClass }}', '{{methodClass}}'],
            [$method, $method, $methodClass, $methodClass],
            $stub,
        );
    }

    /**
     * Replace the content type placeholder.
     */
    private function replaceContentType(string $stub): string
    {
        $contentType = 'Json';

        if ($this->option('form')) {
            $contentType = 'Form';
        } elseif ($this->option('multipart')) {
            $contentType = 'Multipart';
        } elseif ($this->option('xml')) {
            $contentType = 'Xml';
        }

        return str_replace(
            ['{{ contentType }}', '{{contentType}}'],
            $contentType,
            $stub,
        );
    }

    /**
     * Replace the attributes placeholder.
     */
    private function replaceAttributes(string $stub): string
    {
        $attributes = [];

        if ($this->option('cache')) {
            $attributes[] = 'Cache(ttl: 300)';
        }

        if ($this->option('retry')) {
            $attributes[] = 'Retry(times: 3, delay: 100)';
        }

        if ($this->option('circuit')) {
            $attributes[] = 'CircuitBreaker(threshold: 5, timeout: 30)';
        }

        if ($this->option('idempotent')) {
            $attributes[] = 'Idempotent()';
        }

        if ($this->option('throw')) {
            $attributes[] = 'ThrowOnError()';
        }

        $attributeString = '';

        if ($attributes !== []) {
            $attributeString = '#['.implode(', ', $attributes).']';
        }

        return str_replace(
            ['{{ attributes }}', '{{attributes}}'],
            $attributeString,
            $stub,
        );
    }

    /**
     * Replace the connector name placeholder.
     */
    private function replaceConnector(string $stub): string
    {
        $connector = $this->argument('connector');

        return str_replace(
            ['{{ connector }}', '{{connector}}'],
            $connector,
            $stub,
        );
    }
}

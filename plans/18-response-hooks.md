# Response Hooks

Chain of transformers that process every response (not just per-request).

```php
class ApiConnector extends Connector
{
    public function responseHooks(): array
    {
        return [
            // Log all responses
            function (Response $response, Request $request): Response {
                Log::info('API Response', [
                    'status' => $response->status(),
                    'duration' => $response->duration(),
                ]);
                return $response;
            },

            // Transform error responses
            function (Response $response, Request $request): Response {
                if ($response->failed()) {
                    throw ApiException::fromResponse($response);
                }
                return $response;
            },

            // Unwrap nested data
            new UnwrapDataHook('data'),

            // Custom hook class
            new CustomResponseHook(),
        ];
    }
}

// Hook class
class UnwrapDataHook
{
    public function __construct(
        private readonly string $key,
    ) {}

    public function __invoke(Response $response, Request $request): Response
    {
        return $response->withJson(
            $response->json()[$this->key] ?? $response->json()
        );
    }
}
```

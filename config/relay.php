<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default Timeout
    |--------------------------------------------------------------------------
    |
    | The default timeout for HTTP requests in seconds. This can be overridden
    | on individual connectors or requests.
    |
    */
    'timeout' => env('RELAY_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Default Connection Timeout
    |--------------------------------------------------------------------------
    |
    | The default connection timeout in seconds. This is the maximum time to
    | wait for a connection to be established.
    |
    */
    'connect_timeout' => env('RELAY_CONNECT_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Throw on Error
    |--------------------------------------------------------------------------
    |
    | When enabled, requests will throw exceptions on 4xx/5xx responses by
    | default. This can be overridden using the #[ThrowOnError] attribute.
    |
    */
    'throw_on_error' => env('RELAY_THROW_ON_ERROR', false),

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Configuration for response caching.
    |
    */
    'cache' => [
        'enabled' => env('RELAY_CACHE_ENABLED', true),
        'store' => env('RELAY_CACHE_STORE'),
        'ttl' => env('RELAY_CACHE_TTL', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configuration for rate limiting.
    |
    */
    'rate_limiting' => [
        'enabled' => env('RELAY_RATE_LIMITING_ENABLED', true),
        'store' => env('RELAY_RATE_LIMITING_STORE', 'cache'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Configuration for request/response logging.
    |
    */
    'logging' => [
        'enabled' => env('RELAY_LOGGING_ENABLED', false),
        'channel' => env('RELAY_LOGGING_CHANNEL'),
        'level' => env('RELAY_LOGGING_LEVEL', 'debug'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tracing
    |--------------------------------------------------------------------------
    |
    | Configuration for request tracing and OpenTelemetry integration.
    |
    */
    'tracing' => [
        'enabled' => env('RELAY_TRACING_ENABLED', false),
        'header' => env('RELAY_TRACING_HEADER', 'X-Request-Id'),
    ],
];

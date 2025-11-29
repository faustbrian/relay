[![GitHub Workflow Status][ico-tests]][link-tests]
[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Total Downloads][ico-downloads]][link-downloads]

------

Relay is a modern, attribute-driven HTTP client for PHP 8.4+. It provides an elegant, type-safe API for building API integrations with support for authentication, caching, rate limiting, retries, and more.

## Requirements

> **Requires [PHP 8.4+](https://php.net/releases/)**

## Installation

```bash
composer require cline/relay
```

## Documentation

- **[Getting Started](cookbook/getting-started.md)** - Installation and basic concepts
- **[Connectors](cookbook/connectors.md)** - Creating and configuring API connectors
- **[Requests](cookbook/requests.md)** - Building typed request objects
- **[Responses](cookbook/responses.md)** - Working with API responses
- **[Attributes](cookbook/attributes.md)** - HTTP methods, content types, and behavior attributes
- **[Authentication](cookbook/authentication.md)** - Bearer tokens, Basic auth, and custom strategies
- **[Middleware](cookbook/middleware.md)** - Request/response middleware
- **[Caching](cookbook/caching.md)** - Response caching strategies
- **[Rate Limiting](cookbook/rate-limiting.md)** - Client-side rate limiting
- **[Resilience](cookbook/resilience.md)** - Retries and circuit breakers
- **[Pagination](cookbook/pagination.md)** - Handling paginated APIs
- **[Pooling](cookbook/pooling.md)** - Concurrent request execution
- **[Testing](cookbook/testing.md)** - Mocking and fixtures
- **[Debugging](cookbook/debugging.md)** - Request/response inspection
- **[Advanced Usage](cookbook/advanced-usage.md)** - DTOs, macros, and more

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CODE_OF_CONDUCT](CODE_OF_CONDUCT.md) for details.

## Security

If you discover any security related issues, please use the [GitHub security reporting form][link-security] rather than the issue queue.

## Credits

- [Brian Faust][link-maintainer]
- [All Contributors][link-contributors]

## License

The MIT License. Please see [License File](LICENSE.md) for more information.

[ico-tests]: https://github.com/faustbrian/relay/actions/workflows/quality-assurance.yaml/badge.svg
[ico-version]: https://img.shields.io/packagist/v/cline/relay.svg
[ico-license]: https://img.shields.io/badge/License-MIT-green.svg
[ico-downloads]: https://img.shields.io/packagist/dt/cline/relay.svg

[link-tests]: https://github.com/faustbrian/relay/actions
[link-packagist]: https://packagist.org/packages/cline/relay
[link-downloads]: https://packagist.org/packages/cline/relay
[link-security]: https://github.com/faustbrian/relay/security
[link-maintainer]: https://github.com/faustbrian
[link-contributors]: ../../contributors

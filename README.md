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

Full documentation is available at **[docs.cline.sh/relay](https://docs.cline.sh/relay/getting-started/)**

### Guides

- **[Getting Started](https://docs.cline.sh/relay/getting-started/)** - Installation and basic concepts
- **[Connectors](https://docs.cline.sh/relay/connectors/)** - Creating and configuring API connectors
- **[Requests](https://docs.cline.sh/relay/requests/)** - Building typed request objects
- **[Responses](https://docs.cline.sh/relay/responses/)** - Working with API responses
- **[Attributes](https://docs.cline.sh/relay/attributes/)** - HTTP methods, content types, and behavior attributes
- **[Authentication](https://docs.cline.sh/relay/authentication/)** - Bearer tokens, Basic auth, and custom strategies
- **[Middleware](https://docs.cline.sh/relay/middleware/)** - Request/response middleware
- **[Caching](https://docs.cline.sh/relay/caching/)** - Response caching strategies
- **[Rate Limiting](https://docs.cline.sh/relay/rate-limiting/)** - Client-side rate limiting
- **[Resilience](https://docs.cline.sh/relay/resilience/)** - Retries and circuit breakers
- **[Pagination](https://docs.cline.sh/relay/pagination/)** - Handling paginated APIs
- **[Pooling](https://docs.cline.sh/relay/pooling/)** - Concurrent request execution
- **[Testing](https://docs.cline.sh/relay/testing/)** - Mocking and fixtures
- **[Debugging](https://docs.cline.sh/relay/debugging/)** - Request/response inspection
- **[Generators](https://docs.cline.sh/relay/generators/)** - Artisan scaffolding commands
- **[Advanced Usage](https://docs.cline.sh/relay/advanced-usage/)** - DTOs, GraphQL, streaming, and more

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

[ico-tests]: https://git.cline.sh/faustbrian/relay/actions/workflows/quality-assurance.yaml/badge.svg
[ico-version]: https://img.shields.io/packagist/v/cline/relay.svg
[ico-license]: https://img.shields.io/badge/License-MIT-green.svg
[ico-downloads]: https://img.shields.io/packagist/dt/cline/relay.svg

[link-tests]: https://git.cline.sh/faustbrian/relay/actions
[link-packagist]: https://packagist.org/packages/cline/relay
[link-downloads]: https://packagist.org/packages/cline/relay
[link-security]: https://git.cline.sh/faustbrian/relay/security
[link-maintainer]: https://git.cline.sh/faustbrian
[link-contributors]: ../../contributors

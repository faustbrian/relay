# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Added repository-level maintainer guidance in `AGENTS.md`.
- Initial release

### Changed
- Renamed abstract base classes to use the `Abstract*` prefix across `src/`.
- Renamed interfaces to use the `*Interface` suffix across `src/`.
- Renamed traits to use the `*Trait` suffix across `src/`.
- Updated tests, documentation, and generator stubs to use the renamed symbols.

### Breaking
- Removed the legacy abstract, interface, and trait names. Migrate to the new
  canonical symbols such as `AbstractConnector`, `AbstractRequest`,
  `AbstractResource`, `AuthenticatorInterface`, `MiddlewareInterface`,
  `AuthorizationCodeGrantTrait`, and `HasDebuggingTrait`.

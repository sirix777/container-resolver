# Changelog

All notable changes to this project will be documented in this file.

The format is based on Keep a Changelog,
and this project adheres to Semantic Versioning.

## [0.1.0] - 2026-05-26

### Added
- Initial release.
- `ContainerResolver` for strict PSR-11 service resolution.
- `ConfigReader` for strict typed configuration reading.
- List-specific config readers for list, string list, and non-empty string list values.
- PHP enum config readers for optional and required enum values.
- Package exceptions for missing and invalid container services.
- Package exceptions for missing and invalid configuration values.

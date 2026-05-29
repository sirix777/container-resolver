# Changelog

All notable changes to this project will be documented in this file.

The format is based on Keep a Changelog.

This package is pre-stable. Until the first stable release, public contracts and behavior may change between releases, and Semantic Versioning guarantees are not applied.

## [0.1.2] - 2026-05-29

### Changed
- Documented public method failure modes with `@throws` annotations.
- Documented that non-`NotFoundExceptionInterface` container resolution failures are propagated unchanged.

## [0.1.1] - 2026-05-28

### Added
- `optionalNonEmptyString()` for optional strings where an empty string should be treated as not configured.
- List-specific config readers for list, string list, and non-empty string list values.
- PHP enum config readers for optional and required enum values.

### Changed
- String config readers now trim leading and trailing whitespace from configured string values.
- Documented the pre-stable package status and lack of Semantic Versioning guarantees before the first stable release.

## [0.1.0] - 2026-05-28

### Added
- Initial release.
- `ContainerResolver` for strict PSR-11 service resolution.
- `ConfigReader` for strict typed configuration reading.
- Package exceptions for missing and invalid container services.
- Package exceptions for missing and invalid configuration values.

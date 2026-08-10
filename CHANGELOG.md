# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to Semantic Versioning.

## [0.2.0] - 2026-08-10

### Added
- Strict optional service APIs: `optional()`, `optionalAs()`, and `optionalExisting()`.
- Distinct `optionalArray()` and `optionalMap()` APIs for arbitrary arrays and string-keyed configuration maps.
- Validation of reader defaults and enum schemas, ensuring default values always satisfy the declared return contract.
- Stable dot-path validation and documented `null` semantics.
- `UPGRADE.md` and `SECURITY.md`.
- CI validation across PHP 8.2–8.5 and `psr/container` 1.x and 2.x.
- A CI-enforced 100% statement-coverage threshold for `src`.

### Changed
- This pre-1.0 release supports PHP `^8.2` with `psr/container` 1.x or 2.x.
- PHPStan runs at its maximum level.
- Configured string values, string-list items, string enum values, and string-backed enum values are trimmed before validation.
- PHP enum case names are resolved case-insensitively after trimming; backed values take precedence over case names.

### Breaking changes
- `ContainerResolver::optional(string $serviceId, mixed $default = null)` has been replaced. `optional()` is now typed and returns `null` for a missing service; use `optionalAs()` for a custom id or `optionalExisting()` for raw values and a fallback.
- `optionalArray()` now represents any PHP array. Use `optionalMap()` for a string-keyed configuration map; `ConfigReader::fromContainer()` requires that map shape.
- Invalid defaults and invalid enum allowed-value lists now fail immediately instead of being returned when the configuration path is absent.
- Empty paths and paths with empty segments are rejected. A configured `null` is no longer treated as absent by typed readers.

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

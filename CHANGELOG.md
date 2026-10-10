# Changelog

All notable changes to this project will be documented in this file.

## [3.0.0] - 2026-10-10

This release follows 2.2.2 and uses a new major version because it changes
public interfaces and upgrades the database dependency across a major version.

### Added

- Verified support for PHP 8.5 while retaining PHP 8.3 as the minimum supported version.
- PHPUnit 12.5 coverage for entities, repositories, caching, transactions, and weight operations.
- PHPStan 2.x analysis at level `max` and aggregate Composer development commands.
- Runtime validation for entity classes, database metadata, cache values, IDs, and dynamic constants.

### Changed

- Updated `websk/php-cache`, `websk/php-db`, and `websk/php-utils` to 3.0.
- Scoped PSR-4 autoloading to the `WebSK\` namespace.
- Added explicit parameter and return types to repository, service, weight, and magic-method contracts.
- Normalized database IDs to integers and sanitized update field names.
- Documented installation, entity definitions, services, caching, missing fields, and development checks.

### Breaking changes

- Repository, service, save, and weight interfaces now declare explicit return types; downstream implementations must use compatible signatures.
- `WeightService` operations require entities that implement both `InterfaceEntity` and `InterfaceWeight`.
- Repository constructors validate the configured entity class and reject unrelated classes.

### Fixed

- Made `ignore_missing_properties_on_save` effective by checking property existence before reflection access.
- Rolled back owned transactions for failures in lifecycle hooks, repository calls, and delete policies.
- Cleared the recursive `afterSave` guard even when a lifecycle hook throws.
- Rejected invalid cached values instead of returning them through typed APIs.
- Required weighted service entities to implement both entity and weight contracts.

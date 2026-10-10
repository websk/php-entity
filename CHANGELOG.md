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

## [2.2.2] - 2025-03-25

### Changed

- Updated `websk/php-db` from 2.0 to 2.1.
- Updated `websk/php-utils` from 2.0 to 2.1.
- Refreshed the locked dependency set.

## [2.2.1] - 2025-03-15

### Changed

- Initialized entity IDs as `null` and declared them as nullable integers.
- Added scalar return types to entity, configuration, repository, service, delete, and weight APIs.
- Added explicit types to entity field-name constants.

## [2.2.0] - 2024-09-01

### Changed

- Updated `websk/php-cache` from 2.1 to 2.2.

## [2.1.0] - 2024-09-01

### Changed

- Updated `websk/php-cache` from 2.0 to 2.1.

## [2.0.0] - 2024-08-22

### Changed

- Raised the minimum supported PHP version from 7.4 to 8.3.
- Updated `websk/php-cache`, `websk/php-db`, and `websk/php-utils` to 2.0.
- Added typed properties and typed class constants to the core entity classes.
- Changed created-at timestamps to use integer values.

### Breaking changes

- Dropped support for PHP 7.4, PHP 8.0, PHP 8.1, and PHP 8.2.

## [1.2.1] - 2022-02-13

### Fixed

- Invalidated the cached current entity before saving an existing record.

## [1.2.0] - 2022-02-13

### Changed

- Updated `websk/php-cache` from 1.1 to 1.2.

## [1.1.1] - 2021-08-04

### Changed

- Initialized entity weights to zero.
- Preserved an explicitly assigned weight when preparing a new entity for saving.
- Avoided assigning a new weight to an entity that had already been persisted.
- Made weight lookups return `null` when there is no adjacent entity to swap with.

## [1.1.0] - 2021-04-14

### Changed

- Updated `websk/php-db` from 1.2 to 1.3.
- Added the Composer package description.

## [1.0.5] - 2021-02-04

### Added

- Declared PHP 7.4 as the minimum supported version.
- Added default repository and service container IDs derived from entity class names.
- Added helpers for loading multiple entities and all persisted entities.

### Changed

- Added property and return types across repository and service implementations.
- Made the explicit `ENTITY_REPOSITORY_CONTAINER_ID` constant optional.

## [1.0.4] - 2019-07-12

### Changed

- Allowed `EntityService::getById()` to accept `null` and return `null` without querying the repository.

## [1.0.3] - 2019-07-12

### Changed

- Renamed the repository lookup method from `find()` to `findById()`.
- Allowed nullable entity IDs when generating object cache keys.

## [1.0.2] - 2019-02-16

### Changed

- Renamed `BaseEntity`, `BaseEntityRepository`, `BaseEntityService`, `BaseWeightRepository`, and `BaseWeightService` by removing the `Base` prefix.
- Moved cache TTL customization to the entity service through `getCacheTtlSeconds()`.
- Removed `InterfaceCacheTtlSeconds`.

## [1.0.1] - 2018-12-12

### Changed

- Replaced development dependency constraints with stable 1.x constraints.
- Corrected `WebSK\\Utils` namespace capitalization.

## [1.0.0] - 2018-11-27

### Added

- Initial entity, repository, and service abstractions.
- Database persistence, cache integration, lifecycle hooks, and configurable field mapping.
- Weight-aware repository and service helpers.

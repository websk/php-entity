# php-entity

`websk/php-entity` provides base entity, repository, service, caching, and weight-ordering primitives for WebSK applications.

## Requirements

- PHP 8.3, 8.4, or 8.5
- PDO
- `websk/php-cache` 3.0
- `websk/php-db` 2.2
- `websk/php-utils` 3.0

## Installation

```bash
composer require websk/php-entity
```

## Entity definition

An entity implements `InterfaceEntity`, declares its database table, and defines one protected property for each persisted field. `Entity` supplies the nullable integer ID and creation timestamp.

```php
<?php

use WebSK\Entity\Entity;

final class Article extends Entity
{
    public const string DB_TABLE_NAME = 'articles';

    protected string $title = '';

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }
}
```

Create a repository by extending `EntityRepository` and passing the entity class and a configured `DBService`:

```php
<?php

use WebSK\DB\DBService;
use WebSK\Entity\EntityRepository;

final class ArticleRepository extends EntityRepository
{
}

$repository = new ArticleRepository(Article::class, $db_service);
$article = $repository->findById(1);
```

`EntityRepository` expects the table to contain the entity ID field (`id` by default) and matching columns for persisted properties. Define `DB_ID_FIELD_NAME` on the entity to use another ID column.

## Services and caching

Extend `EntityService` to add cache-backed reads and transactional save/delete operations. The service requires the entity class, its repository, and a configured `CacheService`.

```php
<?php

use WebSK\Entity\EntityService;

final class ArticleService extends EntityService
{
}

$service = new ArticleService(Article::class, $repository, $cache_service);
$article = $service->getById(1);
```

The service validates cached values before returning them. Save and delete operations roll back transactions on any throwable failure in repository or lifecycle hooks.

## Missing database fields

By default, loading or saving a database field without a matching entity property raises an exception. Legacy schemas can opt into ignoring such fields:

```php
use WebSK\Entity\EntityConfig;

EntityConfig::setIgnoreMissingPropertiesOnLoad(true);
EntityConfig::setIgnoreMissingPropertiesOnSave(true);
```

## Weight ordering

Entities that implement `InterfaceWeight` can use `WeightTrait`, `WeightRepository`, and `WeightService` to initialize weights and swap adjacent items within an optional field context.

## Development

```bash
composer install
composer check
composer validate --strict
composer audit --locked
```

`composer check` runs syntax checks, PHPUnit, and PHPStan at level `max`.

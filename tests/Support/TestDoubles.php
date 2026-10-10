<?php

namespace WebSK\Entity\Tests\Support;

use PDOStatement;
use WebSK\Cache\CacheService;
use WebSK\Cache\Engines\CacheEngineInterface;
use WebSK\DB\DBService;
use WebSK\Entity\Entity;
use WebSK\Entity\EntityRepository;
use WebSK\Entity\EntityService;
use WebSK\Entity\InterfaceEntity;
use WebSK\Entity\InterfaceWeight;
use WebSK\Entity\WeightRepository;
use WebSK\Entity\WeightService;
use WebSK\Entity\WeightTrait;

final class TestEntity extends Entity implements InterfaceWeight
{
    use WeightTrait;

    public const string DB_TABLE_NAME = 'test_entities';
    public const string ENTITY_REPOSITORY_CONTAINER_ID = 'test.repository';
    public const string ENTITY_SERVICE_CONTAINER_ID = 'test.service';

    protected string $name = '';
    protected ?string $note = null;

    public function setIdForTest(?int $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }
}

final class OtherEntity extends Entity
{
    public const string DB_TABLE_NAME = 'other_entities';
}

final class InvalidTableEntity extends Entity
{
    public const int DB_TABLE_NAME = 123;
}

final class TestStatement extends PDOStatement
{
}

class FakeDbService extends DBService
{
    /** @var list<mixed> */
    public array $column_result = [];

    /** @var list<object> */
    public array $objects_result = [];

    public object|false $object_result = false;
    public mixed $field_result = false;
    public string $last_insert_id = '1';
    public bool $transaction = false;
    public int $begin_count = 0;
    public int $commit_count = 0;
    public int $rollback_count = 0;
    public bool $throw_on_query = false;

    /** @var list<array{sql: string, params: array<int|string, mixed>}> */
    public array $queries = [];

    public function __construct()
    {
    }

    public function query(string $query, array $params_arr = []): PDOStatement
    {
        if ($this->throw_on_query) {
            throw new \RuntimeException('query failed');
        }

        $this->queries[] = ['sql' => $query, 'params' => $params_arr];

        return new TestStatement();
    }

    public function readObjects(string $query, array $params_arr = [], string $field_name_for_keys = ''): array
    {
        return $this->objects_result;
    }

    public function readObject(string $query, array $params_arr = []): mixed
    {
        return $this->object_result;
    }

    public function readColumn(string $query, array $params_arr = []): array
    {
        return $this->column_result;
    }

    public function readField(string $query, array $params_arr = []): mixed
    {
        $this->queries[] = ['sql' => $query, 'params' => $params_arr];

        return $this->field_result;
    }

    public function lastInsertId(?string $db_sequence_name = null): string
    {
        return $this->last_insert_id;
    }

    public function beginTransaction(): bool
    {
        ++$this->begin_count;
        $this->transaction = true;

        return true;
    }

    public function inTransaction(): bool
    {
        return $this->transaction;
    }

    public function commitTransaction(): bool
    {
        ++$this->commit_count;
        $this->transaction = false;

        return true;
    }

    public function rollBackTransaction(): bool
    {
        ++$this->rollback_count;
        $this->transaction = false;

        return true;
    }

    /** @return array{sql: string, params: array<int|string, mixed>} */
    public function lastQuery(): array
    {
        $query = end($this->queries);
        if ($query === false) {
            throw new \LogicException('No query was recorded');
        }

        return $query;
    }
}

final class TestRepository extends EntityRepository
{
}

final class TestWeightRepository extends WeightRepository
{
    public ?int $swap_id = null;
    public int $max_weight = 0;

    /** @var array<int, InterfaceEntity> */
    public array $entities = [];

    /** @var list<InterfaceEntity> */
    public array $saved = [];

    public function getObjectToSwapWeightsId(int $current_item_weight, array $extra_fields_arr = []): ?int
    {
        return $this->swap_id;
    }

    public function getMaxWeightForContext(array $extra_fields_arr = []): int
    {
        return $this->max_weight;
    }

    public function findById(int $id): ?InterfaceEntity
    {
        return $this->entities[$id] ?? null;
    }

    public function save(InterfaceEntity $entity_obj): void
    {
        $this->saved[] = $entity_obj;
    }
}

class StubRepository extends EntityRepository
{
    /** @var array<int, InterfaceEntity> */
    public array $entities = [];

    /** @var list<int> */
    public array $ids = [];

    /** @var list<InterfaceEntity> */
    public array $saved = [];

    public bool $transaction = false;
    public int $begin_count = 0;
    public int $commit_count = 0;
    public int $rollback_count = 0;
    public int $delete_count = 0;
    public bool $throw_on_save = false;
    public bool $throw_on_delete = false;

    public function findById(int $id): ?InterfaceEntity
    {
        return $this->entities[$id] ?? null;
    }

    public function getAllIdsArrByIdAsc(): array
    {
        return $this->ids;
    }

    public function save(InterfaceEntity $entity_obj): void
    {
        if ($this->throw_on_save) {
            throw new \RuntimeException('save failed');
        }

        if ($entity_obj instanceof TestEntity && $entity_obj->getId() === null) {
            $entity_obj->setIdForTest(10);
        }
        $this->saved[] = $entity_obj;
        $entity_id = $entity_obj->getId();
        if ($entity_id !== null) {
            $this->entities[$entity_id] = $entity_obj;
        }
    }

    public function delete(InterfaceEntity $entity_obj): PDOStatement
    {
        if ($this->throw_on_delete) {
            throw new \RuntimeException('delete failed');
        }

        ++$this->delete_count;

        return new TestStatement();
    }

    public function inTransaction(): bool
    {
        return $this->transaction;
    }

    public function beginTransaction(): bool
    {
        ++$this->begin_count;
        $this->transaction = true;

        return true;
    }

    public function commitTransaction(): bool
    {
        ++$this->commit_count;
        $this->transaction = false;

        return true;
    }

    public function rollbackTransaction(): bool
    {
        ++$this->rollback_count;
        $this->transaction = false;

        return true;
    }
}

class TestService extends EntityService
{
    public bool $throw_before_save = false;
    public bool $throw_after_save = false;
    public bool $throw_after_delete = false;
    public bool $allow_delete = true;
    public int $after_save_count = 0;

    public function beforeSave(InterfaceEntity $entity_obj): void
    {
        if ($this->throw_before_save) {
            throw new \RuntimeException('beforeSave failed');
        }

        parent::beforeSave($entity_obj);
    }

    public function afterSave(InterfaceEntity $entity_obj): void
    {
        ++$this->after_save_count;
        if ($this->throw_after_save) {
            $this->throw_after_save = false;
            throw new \RuntimeException('afterSave failed');
        }

        parent::afterSave($entity_obj);
    }

    public function canDelete(InterfaceEntity $entity_obj, string &$message): bool
    {
        $message = 'delete denied';

        return $this->allow_delete;
    }

    public function afterDelete(InterfaceEntity $entity_obj): void
    {
        if ($this->throw_after_delete) {
            throw new \RuntimeException('afterDelete failed');
        }

        parent::afterDelete($entity_obj);
    }
}

final class TestWeightService extends WeightService
{
}

final class ArrayCacheEngine implements CacheEngineInterface
{
    /** @var array<string, mixed> */
    public array $values = [];

    public function set(string $key, mixed $value, int $ttl_sec = 0): bool
    {
        $this->values[$key] = $value;

        return true;
    }

    public function get(string $key): mixed
    {
        return $this->values[$key] ?? false;
    }

    public function delete(string $key): bool
    {
        unset($this->values[$key]);

        return true;
    }
}

/** @param-out ArrayCacheEngine $engine */
function createCacheService(mixed &$engine): CacheService
{
    $engine = new ArrayCacheEngine();

    return new CacheService($engine);
}

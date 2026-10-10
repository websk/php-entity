<?php

namespace WebSK\Entity;

use WebSK\DB\DBService;
use WebSK\Utils\Sanitize;

/** Base repository for entities stored in a relational database table. */
abstract class EntityRepository implements InterfaceEntityRepository
{
    public const string DEFAULT_ID_FIELD_NAME = 'id';

    /** @var class-string<InterfaceEntity> */
    protected string $entity_class_name;

    protected DBService $db_service;

    public function __construct(string $entity_class_name, DBService $db_service)
    {
        $this->entity_class_name = self::validateEntityClassName($entity_class_name);
        $this->db_service = $db_service;
    }

    public function getContainerId(): string
    {
        return self::getContainerIdByClassName($this->entity_class_name);
    }

    /** @param class-string $entity_class_name */
    public static function getContainerIdByClassName(string $entity_class_name): string
    {
        $constant_name = $entity_class_name . '::ENTITY_REPOSITORY_CONTAINER_ID';
        if (!defined($constant_name)) {
            return $entity_class_name . 'Repository';
        }

        return self::getStringConstant($constant_name);
    }

    protected function exceptionIfClassIsIncompatibleWithEntityRepository(): void
    {
        $constant_name = $this->entity_class_name . '::DB_TABLE_NAME';
        if (!defined($constant_name)) {
            throw new \UnexpectedValueException(
                'Class must provide DB_TABLE_NAME constant to use EntityRepository'
            );
        }

        self::getStringConstant($constant_name);
    }

    public function getDbService(): DBService
    {
        return $this->db_service;
    }

    public function getTableName(): string
    {
        $this->exceptionIfClassIsIncompatibleWithEntityRepository();

        return self::getStringConstant($this->entity_class_name . '::DB_TABLE_NAME');
    }

    public function getIdFieldName(): string
    {
        $constant_name = $this->entity_class_name . '::DB_ID_FIELD_NAME';
        if (!defined($constant_name)) {
            return self::DEFAULT_ID_FIELD_NAME;
        }

        return self::getStringConstant($constant_name);
    }

    /** @return list<int> */
    public function getAllIdsArrByCreatedAtDesc(int $offset = 0, int $page_size = 30): array
    {
        $db_table_name = $this->getTableName();
        $db_id_field_name = $this->getIdFieldName();

        $ids = $this->db_service->readColumn(
            'SELECT ' . Sanitize::sanitizeSqlColumnName($db_id_field_name)
            . ' FROM ' . Sanitize::sanitizeSqlColumnName($db_table_name)
            . ' ORDER BY created_at_ts DESC'
            . ' LIMIT ' . $page_size . ' OFFSET ' . $offset
        );

        return self::normalizeIds($ids);
    }

    /** @return list<int> */
    public function getAllIdsArrByIdAsc(): array
    {
        $db_table_name = $this->getTableName();
        $db_id_field_name = $this->getIdFieldName();

        $ids = $this->db_service->readColumn(
            'SELECT ' . Sanitize::sanitizeSqlColumnName($db_id_field_name)
            . ' FROM ' . Sanitize::sanitizeSqlColumnName($db_table_name)
            . ' ORDER BY ' . Sanitize::sanitizeSqlColumnName($db_id_field_name) . ' ASC'
        );

        return self::normalizeIds($ids);
    }

    public function findById(int $id): ?InterfaceEntity
    {
        $db_table_name = $this->getTableName();
        $db_id_field_name = $this->getIdFieldName();

        $data_obj = $this->db_service->readObject(
            'SELECT /* LMO */ * FROM ' . Sanitize::sanitizeSqlColumnName($db_table_name)
            . ' WHERE ' . Sanitize::sanitizeSqlColumnName($db_id_field_name) . ' = ?',
            [$id]
        );

        if ($data_obj === false) {
            return null;
        }
        if (!$data_obj instanceof \stdClass) {
            throw new \UnexpectedValueException('DBService::readObject() must return stdClass or false');
        }

        $entity_class_name = $this->entity_class_name;
        $entity_obj = new $entity_class_name();
        $reflect = new \ReflectionClass($entity_obj);

        foreach (get_object_vars($data_obj) as $field_name => $field_value) {
            if (!$reflect->hasProperty($field_name)) {
                if (EntityConfig::isIgnoreMissingPropertiesOnLoad()) {
                    continue;
                }

                throw new \UnexpectedValueException(
                    'Missing "' . $field_name . '" property in class "' . $this->entity_class_name
                    . '" while property is present in DB table "' . $db_table_name . '"'
                );
            }

            $reflect->getProperty($field_name)->setValue($entity_obj, $field_value);
        }

        return $entity_obj;
    }

    public function inTransaction(): bool
    {
        return $this->db_service->inTransaction();
    }

    public function beginTransaction(): bool
    {
        return $this->db_service->beginTransaction();
    }

    public function commitTransaction(): bool
    {
        return $this->db_service->commitTransaction();
    }

    public function rollbackTransaction(): bool
    {
        return $this->db_service->rollBackTransaction();
    }

    public function save(InterfaceEntity $entity_obj): void
    {
        if (!$entity_obj instanceof $this->entity_class_name) {
            throw new \InvalidArgumentException(
                'Repository for ' . $this->entity_class_name . ' cannot save ' . $entity_obj::class
            );
        }

        $db_table_name = $this->getTableName();
        $db_table_fields_arr = $this->db_service->readObjects(
            'EXPLAIN ' . Sanitize::sanitizeSqlColumnName($db_table_name)
        );
        $db_id_field_name = $this->getIdFieldName();
        $reflect = new \ReflectionClass($entity_obj);

        /** @var array<string, mixed> $fields_to_save_arr */
        $fields_to_save_arr = [];

        foreach ($db_table_fields_arr as $field_obj) {
            if (!property_exists($field_obj, 'Field') || !is_string($field_obj->Field)) {
                throw new \UnexpectedValueException('EXPLAIN result must contain a string Field property');
            }

            $field_name = $field_obj->Field;
            if (!$reflect->hasProperty($field_name)) {
                if (EntityConfig::isIgnoreMissingPropertiesOnSave()) {
                    continue;
                }

                throw new \UnexpectedValueException(
                    'Missing property when saving entity: field "' . $field_name
                    . '" exists in DB table and is not present in entity class. '
                    . 'You can disable this check using EntityConfig::setIgnoreMissingPropertiesOnSave()'
                );
            }

            $fields_to_save_arr[$field_name] = $reflect->getProperty($field_name)->getValue($entity_obj);
        }

        if (!$reflect->hasProperty($db_id_field_name)) {
            throw new \UnexpectedValueException(
                'Entity class must provide the ID property "' . $db_id_field_name . '"'
            );
        }

        unset($fields_to_save_arr[$db_id_field_name]);

        $id_property = $reflect->getProperty($db_id_field_name);
        $entity_id_value = $id_property->getValue($entity_obj);
        if ($entity_id_value === null) {
            $id_property->setValue($entity_obj, $this->insertRecord($fields_to_save_arr));
            return;
        }

        $this->updateRecord($fields_to_save_arr, self::normalizeId($entity_id_value));
    }

    public function delete(InterfaceEntity $entity_obj): \PDOStatement
    {
        if (!$entity_obj instanceof $this->entity_class_name) {
            throw new \InvalidArgumentException(
                'Repository for ' . $this->entity_class_name . ' cannot delete ' . $entity_obj::class
            );
        }

        $db_table_name = $this->getTableName();
        $db_id_field_name = $this->getIdFieldName();
        $reflect = new \ReflectionClass($entity_obj);

        if (!$reflect->hasProperty($db_id_field_name)) {
            throw new \UnexpectedValueException(
                'Entity class must provide the ID property "' . $db_id_field_name . '"'
            );
        }

        $entity_id_value = $reflect->getProperty($db_id_field_name)->getValue($entity_obj);
        if ($entity_id_value === null) {
            throw new \LogicException('Deleting not saved object');
        }

        return $this->db_service->query(
            'DELETE FROM ' . Sanitize::sanitizeSqlColumnName($db_table_name)
            . ' WHERE ' . Sanitize::sanitizeSqlColumnName($db_id_field_name) . ' = ?',
            [self::normalizeId($entity_id_value)]
        );
    }

    /** @param array<string, mixed> $fields_to_save_arr */
    protected function insertRecord(array $fields_to_save_arr): int
    {
        $db_table_name = $this->getTableName();
        $db_id_field_name = $this->getIdFieldName();
        $placeholders_arr = array_fill(0, count($fields_to_save_arr), '?');

        $quoted_fields_to_save_arr = [];
        foreach (array_keys($fields_to_save_arr) as $field_name_to_save) {
            $quoted_fields_to_save_arr[] = Sanitize::sanitizeSqlColumnName($field_name_to_save);
        }

        $this->db_service->query(
            'INSERT INTO ' . Sanitize::sanitizeSqlColumnName($db_table_name)
            . ' (' . implode(',', $quoted_fields_to_save_arr) . ')'
            . ' VALUES (' . implode(',', $placeholders_arr) . ')',
            array_values($fields_to_save_arr)
        );

        $db_sequence_name = $db_table_name . '_' . $db_id_field_name . '_seq';

        return self::normalizeId($this->db_service->lastInsertId($db_sequence_name));
    }

    /** @param array<string, mixed> $fields_to_save_arr */
    protected function updateRecord(array $fields_to_save_arr, int $entity_id_value): void
    {
        $db_table_name = $this->getTableName();
        $db_id_field_name = $this->getIdFieldName();
        $placeholders_arr = [];

        foreach (array_keys($fields_to_save_arr) as $field_name) {
            $placeholders_arr[] = Sanitize::sanitizeSqlColumnName($field_name) . '=?';
        }

        $values_arr = array_values($fields_to_save_arr);
        $values_arr[] = $entity_id_value;

        $query = 'UPDATE ' . Sanitize::sanitizeSqlColumnName($db_table_name)
            . ' SET ' . implode(',', $placeholders_arr)
            . ' WHERE ' . Sanitize::sanitizeSqlColumnName($db_id_field_name) . ' = ?';
        $this->db_service->query($query, $values_arr);
    }

    private static function getStringConstant(string $constant_name): string
    {
        $value = constant($constant_name);
        if (!is_string($value) || $value === '') {
            throw new \UnexpectedValueException($constant_name . ' must be a non-empty string');
        }

        return $value;
    }

    /** @return class-string<InterfaceEntity> */
    private static function validateEntityClassName(string $entity_class_name): string
    {
        if (!class_exists($entity_class_name) || !is_a($entity_class_name, InterfaceEntity::class, true)) {
            throw new \InvalidArgumentException(
                'Entity class must exist and implement ' . InterfaceEntity::class
            );
        }

        return $entity_class_name;
    }

    /**
     * @param list<mixed> $ids
     * @return list<int>
     */
    private static function normalizeIds(array $ids): array
    {
        return array_map(self::normalizeId(...), $ids);
    }

    private static function normalizeId(mixed $id): int
    {
        if (is_int($id)) {
            return $id;
        }
        if (is_string($id) && preg_match('/^-?\d+$/D', $id) === 1) {
            return (int)$id;
        }

        throw new \UnexpectedValueException('Entity ID must be an integer or an integer string');
    }
}

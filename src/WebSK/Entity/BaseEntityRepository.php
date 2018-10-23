<?php

namespace WebSK\Entity;

use Websk\Utils\Sanitize;
use WebSK\DB\DBService;

/**
 * Для работы с BaseEntityRepository необходимо:
 *
 * 1. создаем таблицу в БД с полем "id" (auto increment) и прочими нужными полями
 * 2. создаем класс для сущности:
 *      - для каждого поля в таблице у класса должно быть свое свойство
 *      - значения по-умолчанию должны соответствовать полям таблицы
 *      - указываем две константы:
 *          - const DB_TABLE_NAME   - имя таблицы в которой хранятся данные модели
 *      - подключаем трейты:
 *          - ProtectPropertiesTrait
 *      - пишем необходимые геттеры и сеттеры
 * 3. Создаем репозиторий для сущности, наследуем его от BaseEntityRepository:
 *      - при необходимости, переопределяем методы
 *      - в классе сущности указываем константу ENTITY_REPOSITORY_CONTAINER_ID - идентификатор зарегистрированного контейнера данного репозитория
 */
abstract class BaseEntityRepository implements
    InterfaceEntityRepository
{

    public const DEFAULT_ID_FIELD_NAME = 'id';

    /** @var string */
    protected $entity_class_name;
    /** @var DBService */
    protected $db_service;

    public function __construct($entity_class_name, DBService $db_service)
    {
        $this->entity_class_name = $entity_class_name;
        $this->db_service = $db_service;
    }

    /**
     * Проверяет, что объект (класс его) предоставляет нужные константы и т.п.
     * Если что-то не так - выбрасывает исключение.
     * @throws \Exception
     */
    protected function exceptionIfClassIsIncompatibleWithBaseEntityRepository()
    {
        if (!defined($this->entity_class_name . '::DB_TABLE_NAME')) {
            throw new \Exception('class must provide DB_TABLE_NAME constant to use BaseEntityRepository');
        }

        if (!defined($this->entity_class_name . '::ENTITY_REPOSITORY_CONTAINER_ID')) {
            throw new \Exception('class must provide ENTITY_REPOSITORY_CONTAINER_ID constant to use BaseEntityRepository');
        }
    }

    /**
     * @return DBService
     */
    public function getDbService()
    {
        return $this->db_service;
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    protected function getTableName()
    {
        $this->exceptionIfClassIsIncompatibleWithBaseEntityRepository();

        return $this->entity_class_name::DB_TABLE_NAME;
    }

    /**
     * @return string
     */
    protected function getIdFieldName()
    {
        if (defined($this->entity_class_name . '::DB_ID_FIELD_NAME')) {
            return $this->entity_class_name::DB_ID_FIELD_NAME;
        }

        return self::DEFAULT_ID_FIELD_NAME;
    }

    /**
     * @param int $offset
     * @param int $page_size
     * @return array
     * @throws \Exception
     */
    public function getAllIdsArrByCreatedAtDesc($offset = 0, $page_size = 30): array
    {
        $db_table_name = $this->getTableName();
        $db_id_field_name = $this->getIdFieldName();

        $ids_arr = $this->db_service->readColumn(
            'SELECT ' . Sanitize::sanitizeSqlColumnName($db_id_field_name)
            . ' FROM ' . Sanitize::sanitizeSqlColumnName($db_table_name)
            . ' ORDER BY created_at_ts DESC
                    LIMIT ' . intval($page_size) . ' OFFSET ' . intval($offset)
        );

        return $ids_arr;
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function getAllIdsArrByIdAsc(): array
    {
        $db_table_name = $this->getTableName();
        $db_id_field_name = $this->getIdFieldName();

        $ids_arr = $this->db_service->readColumn(
            'SELECT ' . Sanitize::sanitizeSqlColumnName($db_id_field_name) .
            ' FROM ' . Sanitize::sanitizeSqlColumnName($db_table_name) .
            ' ORDER BY ' . Sanitize::sanitizeSqlColumnName($db_id_field_name) . ' ASC'
        );

        return $ids_arr;
    }

    /**
     * @param $id
     * @return null|object
     * @throws \Exception
     */
    public function find($id)
    {
        $this->exceptionIfClassIsIncompatibleWithBaseEntityRepository();

        $db_table_name = $this->getTableName();
        $db_id_field_name = $this->getIdFieldName();

        $data_obj = $this->db_service->readObject(
            'SELECT /* LMO */ * FROM ' . Sanitize::sanitizeSqlColumnName($db_table_name)
            . ' WHERE ' . Sanitize::sanitizeSqlColumnName($db_id_field_name) . ' = ?',
            [$id]
        );

        if (!$data_obj) {
            return null;
        }

        $entity_obj = new $this->entity_class_name;

        $reflect = new \ReflectionClass($this->entity_class_name);

        foreach ($data_obj as $field_name => $field_value) {
            if (property_exists($this->entity_class_name, $field_name)) {
                $property = $reflect->getProperty($field_name);
                $property->setAccessible(true);
                $property->setValue($entity_obj, $field_value);
            } else {
                if (EntityConfig::isIgnoreMissingPropertiesOnLoad()) {
                    // ignore missing property
                } else {
                    throw new \Exception(
                        'Missing "' . $field_name . '" property in class "' . $this->entity_class_name . '" while 
                        property is present in DB table "' . $db_table_name . '"'
                    );
                }
            }
        }

        return $entity_obj;
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function inTransaction()
    {
        return $this->db_service->inTransaction();
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function beginTransaction()
    {
        return $this->db_service->beginTransaction();
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function commitTransaction()
    {
        return $this->db_service->commitTransaction();
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function rollbackTransaction()
    {
        return $this->db_service->rollBackTransaction();
    }

    /**
     * @param InterfaceEntity $entity_obj
     * @throws \ReflectionException
     */
    public function save(InterfaceEntity $entity_obj)
    {
        $this->exceptionIfClassIsIncompatibleWithBaseEntityRepository();

        $db_table_name = $this->getTableName();

        $db_table_fields_arr = $this->db_service->readObjects(
            'EXPLAIN ' . Sanitize::sanitizeSqlColumnName($db_table_name)
        );

        $db_id_field_name = $this->getIdFieldName();

        $reflect = new \ReflectionClass($entity_obj);

        $fields_to_save_arr = [];

        foreach ($db_table_fields_arr as $field_index => $field_obj) {
            $field_name = $field_obj->Field;

            $property_obj = $reflect->getProperty($field_name);

            if (property_exists($this->entity_class_name, $field_name)) {
                $property_obj->setAccessible(true);
                $fields_to_save_arr[$property_obj->getName()] = $property_obj->getValue($entity_obj);
            } else {
                if (EntityConfig::isIgnoreMissingPropertiesOnSave()) {
                    // ignore
                } else {
                    throw new \Exception(
                        'Missing property when saving entity: field "' . $field_name . '" exists in DB table 
                        and not present in entity class. You can disable this check using 
                        EntityConfig::setIgnoreMissingPropertiesOnSave()'
                    );
                }
            }
        }

        unset($fields_to_save_arr[$db_id_field_name]);

        $property_obj = $reflect->getProperty($db_id_field_name);
        $property_obj->setAccessible(true);
        $entity_id_value = $property_obj->getValue($entity_obj);

        if ($entity_id_value == '') {
            $last_insert_id = $this->insertRecord($fields_to_save_arr);
            $property_obj->setValue($entity_obj, $last_insert_id);
        } else {
            $this->updateRecord($fields_to_save_arr, $entity_id_value);
        }
    }

    /**
     * @param $entity_obj
     * @return \PDOStatement
     * @throws \ReflectionException
     */
    public function delete(InterfaceEntity $entity_obj)
    {
        $this->exceptionIfClassIsIncompatibleWithBaseEntityRepository();

        $db_table_name = $this->getTableName();
        $db_id_field_name = $this->getIdFieldName();

        $reflect = new \ReflectionClass($entity_obj);
        $property_obj = $reflect->getProperty($db_id_field_name);
        $property_obj->setAccessible(true);
        $entity_id_value = $property_obj->getValue($entity_obj);

        if ($entity_id_value == '') {
            throw new \Exception('Deleting not saved object');
        }

        $result = $this->db_service->query(
            'DELETE FROM ' . Sanitize::sanitizeSqlColumnName($db_table_name)
            . ' WHERE ' . Sanitize::sanitizeSqlColumnName($db_id_field_name) . ' = ?',
            [$entity_id_value]
        );

        return $result;
    }

    /**
     * @param $fields_to_save_arr
     * @return string
     * @throws \Exception
     */
    protected function insertRecord($fields_to_save_arr)
    {
        $db_table_name = $this->getTableName();
        $db_id_field_name = $this->getIdFieldName();

        $placeholders_arr = array_fill(0, count($fields_to_save_arr), '?');

        $quoted_fields_to_save_arr = array();
        foreach (array_keys($fields_to_save_arr) as $field_name_to_save) {
            $quoted_fields_to_save_arr[] = Sanitize::sanitizeSqlColumnName($field_name_to_save);
        }

        $this->db_service->query(
            'INSERT INTO ' . Sanitize::sanitizeSqlColumnName($db_table_name)
            . ' (' . implode(',', $quoted_fields_to_save_arr) . ') 
                    VALUES (' . implode(',', $placeholders_arr) . ')',
            array_values($fields_to_save_arr)
        );

        $db_sequence_name = $db_table_name . '_' . $db_id_field_name . '_seq';
        $last_insert_id = $this->db_service->lastInsertId($db_sequence_name);

        return $last_insert_id;
    }

    /**
     * @param $fields_to_save_arr
     * @param $entity_id_value
     * @throws \Exception
     */
    protected function updateRecord($fields_to_save_arr, $entity_id_value)
    {
        $db_table_name = $this->getTableName();
        $db_id_field_name = $this->getIdFieldName();

        $placeholders_arr = [];

        foreach ($fields_to_save_arr as $field_name => $field_value) {
            $placeholders_arr[] = $field_name . '=?';
        }

        $values_arr = array_values($fields_to_save_arr);
        array_push($values_arr, $entity_id_value);

        $query = 'UPDATE ' . Sanitize::sanitizeSqlColumnName($db_table_name)
            . ' SET ' . implode(',', $placeholders_arr)
            . ' WHERE ' . Sanitize::sanitizeSqlColumnName($db_id_field_name) . ' = ?';
        $this->db_service->query($query, $values_arr);
    }
}

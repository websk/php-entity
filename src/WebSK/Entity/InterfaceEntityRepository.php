<?php

namespace WebSK\Entity;

/**
 * Interface InterfaceEntityRepository
 * @package WebSK\Entity
 */
interface InterfaceEntityRepository
{
    /**
     * - принимает один параметр: идентификатор объекта
     * - заполняет поля объекта
     * - возвращает объект если все нормально, null - если не получилось загрузить объект (нет в БД и т.п.)
     * @param int $id
     */
    public function findById(int $id);

    /**
     * @param InterfaceEntity $entity_obj
     */
    public function save(InterfaceEntity $entity_obj);

    /**
     * @param InterfaceEntity $entity_obj
     */
    public function delete(InterfaceEntity $entity_obj);
}

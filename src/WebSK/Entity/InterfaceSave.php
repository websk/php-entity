<?php

namespace WebSK\Entity;

/**
 * Interface InterfaceSave
 * @package WebSK\Entity
 * Если класс сервиса реализует этот интерфейс, то он должен иметь:
 * Метод save(), который сохраняет данные объекта.
 * Если объекта нет в базе - он должен создавать и его id должен заполняться
 * правильным значением.
 */
interface InterfaceSave
{
    /**
     * @param InterfaceEntity $entity_obj
     */
    public function beforeSave(InterfaceEntity  $entity_obj);

    /**
     * @param InterfaceEntity $entity_obj
     */
    public function save(InterfaceEntity $entity_obj);

    /**
     * @param InterfaceEntity $entity_obj
     */
    public function afterSave(InterfaceEntity $entity_obj);
}

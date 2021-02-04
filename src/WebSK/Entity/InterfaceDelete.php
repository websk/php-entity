<?php

namespace WebSK\Entity;

/**
 * Interface InterfaceDelete
 * @package WebSK\Entity
 * * Если класс сервиса реализует этот интерфейс, то он должен иметь:
 * - Метод delete(), который удаляет данные объекта в базе. Поведение метода при наличии зависимых объектов пока не регламентировано.
 */
interface InterfaceDelete
{
    /**
     * @param InterfaceEntity $entity_obj
     * @param string $message
     * @return bool
     */
    public function canDelete(InterfaceEntity $entity_obj, string &$message): bool;

    /**
     * @param InterfaceEntity $entity_obj
     */
    public function delete(InterfaceEntity $entity_obj);

    /**
     * @param InterfaceEntity $entity_obj
     */
    public function afterDelete(InterfaceEntity $entity_obj);
}

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
    public function canDelete($entity_obj, &$message);

    public function delete($entity_obj);

    public function afterDelete($entity_obj);
}

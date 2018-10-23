<?php

namespace WebSK\Entity;

/**
 * Interface InterfaceEntityService
 * @package WebSK\Entity
 * Поддержка классом этого интерфейса означает, что сервис умеет создавать экземпляры сущности по ID,
 * кэшировать их и сбрасывать кэш при изменениях.
 * Базовая реализация есть в BaseEntityService.
 */
interface InterfaceEntityService
{
    public function getById($entity_id, $exception_if_not_loaded = true);

    public function removeObjFromCacheById($entity_id);

    public function removeFromCache($entity_obj);
}

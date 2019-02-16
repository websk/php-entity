<?php

namespace WebSK\Entity;

/**
 * Interface InterfaceEntityService
 * @package WebSK\Entity
 * Поддержка классом этого интерфейса означает, что сервис умеет создавать экземпляры сущности по ID,
 * кэшировать их и сбрасывать кэш при изменениях.
 * Базовая реализация есть в EntityService.
 */
interface InterfaceEntityService
{
    /**
     * @param int|null $entity_id
     * @param bool $exception_if_not_loaded
     * @return InterfaceEntity|null
     */
    public function getById(?int $entity_id, bool $exception_if_not_loaded = true);

    /**
     * @param int $entity_id
     */
    public function removeObjFromCacheById(int $entity_id);

    /**
     * @param InterfaceEntity $entity_obj
     */
    public function removeFromCache(InterfaceEntity $entity_obj);
}

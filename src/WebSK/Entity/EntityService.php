<?php

namespace WebSK\Entity;

use WebSK\Utils\Assert;
use WebSK\Utils\FullObjectId;
use WebSK\Cache\CacheService;

/**
 * Class EntityService
 * @package WebSK\Entity
 */
abstract class EntityService implements
    InterfaceEntityService,
    InterfaceSave,
    InterfaceDelete
{
    const DEFAULT_CACHE_TTL_SEC = 60;
    const ALL_IDS_ARR_CACHE_KEY_PREFIX = 'all_ids_arr_';

    /** @var string */
    protected $entity_class_name;
    /** @var EntityRepository */
    protected $repository;
    /** @var CacheService */
    protected $cache_service;

    /**
     * EntityService constructor.
     * @param string $entity_class_name
     * @param EntityRepository $repository
     * @param CacheService $cache_service
     */
    public function __construct(
        string $entity_class_name,
        EntityRepository $repository,
        CacheService $cache_service
    ) {
        $this->entity_class_name = $entity_class_name;
        $this->repository = $repository;
        $this->cache_service = $cache_service;
    }

    /**
     * @return int
     */
    protected function getCacheTtlSeconds()
    {
        return self::DEFAULT_CACHE_TTL_SEC;
    }

    /**
     * @param string $entity_class_name
     * @param int $entity_id
     * @return string
     */
    protected static function getEntityObjectCacheId(string $entity_class_name, int $entity_id)
    {
        return $entity_class_name . '::' . $entity_id;
    }

    /**
     * @return array
     */
    public function getAllIdsArrByIdAsc()
    {
        $cache_key = $this->getAllIdsArrByIdAscCacheKey();

        $cached_obj = $this->cache_service->get($cache_key);

        if ($cached_obj !== false) {
            return $cached_obj;
        }

        $ids_arr = $this->repository->getAllIdsArrByIdAsc();

        $cache_ttl_seconds = $this->getCacheTtlSeconds();

        $this->cache_service->set($cache_key, $ids_arr, $cache_ttl_seconds);

        return $ids_arr;
    }

    /**
     * @return string
     */
    protected function getAllIdsArrByIdAscCacheKey()
    {
        return self::ALL_IDS_ARR_CACHE_KEY_PREFIX . $this->entity_class_name;
    }

    /**
     * @param int|null $entity_id
     * @param bool $exception_if_not_loaded
     * @return InterfaceEntity
     * @throws \Exception
     */
    public function getById(?int $entity_id, bool $exception_if_not_loaded = true)
    {
        $cache_key = self::getEntityObjectCacheId($this->entity_class_name, $entity_id);

        $cached_obj = $this->cache_service->get($cache_key);

        if ($cached_obj !== false) {
            return $cached_obj;
        }

        $entity_obj = $this->repository->find($entity_id);

        if (!$entity_obj) {
            if ($exception_if_not_loaded) {
                Assert::assert($entity_obj);
            }
            return null;
        }

        // store to cache
        $cache_ttl_seconds = $this->getCacheTtlSeconds();

        $this->cache_service->set($cache_key, $entity_obj, $cache_ttl_seconds);

        return $entity_obj;
    }

    /**
     * @param int $entity_id
     */
    public function removeObjFromCacheById(int $entity_id)
    {
        $cache_key = self::getEntityObjectCacheId($this->entity_class_name, $entity_id);

        $this->cache_service->delete($cache_key);
    }

    /**
     * Сбрасывает кэш объекта, созданный при его загрузке в getById.
     * Нужно вызывать после изменения или удаления объекта.
     * @param InterfaceEntity $entity_obj
     * @throws \Exception
     */
    public function removeFromCache(InterfaceEntity $entity_obj)
    {
        if (!($entity_obj instanceof InterfaceEntity)) {
            throw new \Exception('Entity class must provide method getId');
        }

        $this->removeObjFromCacheById($entity_obj->getId());
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function inTransaction()
    {
        return $this->repository->inTransaction();
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function beginTransaction()
    {
        return $this->repository->beginTransaction();
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function commitTransaction()
    {
        return $this->repository->commitTransaction();
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function rollbackTransaction()
    {
        return $this->repository->rollBackTransaction();
    }

    /**
     * Базовая реализация beforeSave - ничего не делает.
     * При необходимости можно переопределить этот метод и сделать в нем дополнительную обработку или проверки перед
     * сохранением.
     * @param InterfaceEntity $entity_obj
     */
    public function beforeSave(InterfaceEntity $entity_obj)
    {
    }

    /**
     * все сохранение делается внутри транзакции (включая beforeSave и afterSave),
     * если будет исключение - транзакция будет откачена PDO
     * @param InterfaceEntity $entity_obj
     * @throws \Exception
     */
    public function save(InterfaceEntity $entity_obj)
    {
        $transaction_is_my = false;
        if (!$this->repository->inTransaction()) {
            $this->repository->beginTransaction();
            $transaction_is_my = true;
        }

        $this->beforeSave($entity_obj);

        try {
            $this->repository->save($entity_obj);
        } catch (\Exception $e) {
            // if any exception while saving - rollback transaction and rethrow exception
            if ($transaction_is_my) {
                $this->repository->rollbackTransaction();
            }

            throw $e;
        }

        // не вызываем afterSave если это вызов save для этого объекта изнутри aftersave этого же объекта
        // (для предотвращения бесконечного рекурсивного вызова afterSave)
        static $__inprogress = [];
        $inprogress_key = FullObjectId::getFullObjectId($entity_obj);
        if (!array_key_exists($inprogress_key, $__inprogress)) {
            $__inprogress[$inprogress_key] = 1;

            $this->afterSave($entity_obj);

            unset($__inprogress[$inprogress_key]);
        }

        // комитим только если мы же и стартовали транзакцию (на случай вложенных вызовов)
        if ($transaction_is_my) {
            $this->commitTransaction();
        }
    }

    /**
     * Базовая обработка изменения.
     * Если на это событие есть подписчики - нужно переопределить обработчик в самом классе и там уже подписать
     * остальных подписчиков.
     * Не забыть в переопределенном методе сбросить кэш!
     * @param InterfaceEntity $entity_obj
     */
    public function afterSave(InterfaceEntity $entity_obj)
    {
        $this->removeFromCache($entity_obj);

        $cache_key = $this->getAllIdsArrByIdAscCacheKey();
        $this->cache_service->delete($cache_key);
    }

    /**
     * Метод проверки возможности удаления объекта.
     * Если объект удалять нельзя - нужно вернуть false.
     * В переменную, переданную по ссылке, можно записать текст сообщения для вывода пользователю.
     * @param InterfaceEntity $entity_obj
     * @param $message
     * @return bool
     */
    public function canDelete(InterfaceEntity $entity_obj, string &$message)
    {
        return true;
    }

    /**
     * Все удаление делается внутри транзакции (включая canDelete и afterDelete),
     * если будет исключение - транзакция будет откачена PDO
     * @param InterfaceEntity $entity_obj
     * @throws \Exception
     */
    public function delete(InterfaceEntity $entity_obj)
    {
        $transaction_is_my = false;
        if (!$this->inTransaction()) {
            $this->beginTransaction();
            $transaction_is_my = true;
        }

        $can_delete_message = '';
        if (!$this->canDelete($entity_obj, $can_delete_message)) {
            if ($transaction_is_my) {
                $this->rollBackTransaction();
            }
            throw new \Exception($can_delete_message);
        }

        $this->repository->delete($entity_obj);

        try {
            $this->afterDelete($entity_obj);
        } catch (\Exception $e) {
            // in the case of any exception - rollback transaction and rethrow exception
            // thus actual db record will not be deleted
            if ($transaction_is_my) {
                $this->rollbackTransaction();
            }

            throw $e;
        }

        if ($transaction_is_my) {
            $this->commitTransaction();
        }
    }

    /**
     * @param int $entity_id
     * @throws \Exception
     */
    public function deleteById(int $entity_id)
    {
        $entity_obj = $this->getById($entity_id);
        $this->delete($entity_obj);
    }

    /**
     * Метод чистки после удаления объекта.
     * Поскольку сущности уже нет в базе, этот метод должен использовать только данные объекта в памяти:
     * - не использовать геттеры (они могут обращаться к базе)
     * - не быть статическим: работает в контексте конкретного объекта
     * @param InterfaceEntity $entity_obj
     */
    public function afterDelete(InterfaceEntity $entity_obj)
    {
        $this->removeFromCache($entity_obj);

        $cache_key = $this->getAllIdsArrByIdAscCacheKey();
        $this->cache_service->delete($cache_key);
    }
}

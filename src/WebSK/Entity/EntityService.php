<?php

namespace WebSK\Entity;

use WebSK\Cache\CacheService;
use WebSK\Utils\FullObjectId;

/** Base service for entity persistence and caching. */
abstract class EntityService implements InterfaceEntityService, InterfaceSave, InterfaceDelete
{
    public const int DEFAULT_CACHE_TTL_SEC = 60;
    public const string ALL_IDS_ARR_CACHE_KEY_PREFIX = 'all_ids_arr_';

    /** @var class-string<InterfaceEntity> */
    protected string $entity_class_name;

    protected EntityRepository $repository;
    protected CacheService $cache_service;

    public function __construct(
        string $entity_class_name,
        EntityRepository $repository,
        CacheService $cache_service
    ) {
        $this->entity_class_name = self::validateEntityClassName($entity_class_name);
        $this->repository = $repository;
        $this->cache_service = $cache_service;
    }

    /** @param class-string $entity_class_name */
    public static function getContainerIdByClassName(string $entity_class_name): string
    {
        $constant_name = $entity_class_name . '::ENTITY_SERVICE_CONTAINER_ID';
        if (!defined($constant_name)) {
            return $entity_class_name . 'Service';
        }

        $value = constant($constant_name);
        if (!is_string($value) || $value === '') {
            throw new \UnexpectedValueException($constant_name . ' must be a non-empty string');
        }

        return $value;
    }

    protected function getCacheTtlSeconds(): int
    {
        return self::DEFAULT_CACHE_TTL_SEC;
    }

    /** @param class-string<InterfaceEntity> $entity_class_name */
    protected static function getEntityObjectCacheId(string $entity_class_name, int $entity_id): string
    {
        return $entity_class_name . '::' . $entity_id;
    }

    /** @return list<int> */
    public function getAllIdsArrByIdAsc(): array
    {
        $cache_key = $this->getAllIdsArrByIdAscCacheKey();
        $cached_value = $this->cache_service->get($cache_key);

        if ($cached_value !== false) {
            $cached_ids = self::normalizeIdList($cached_value);
            if ($cached_ids !== null) {
                return $cached_ids;
            }

            $this->cache_service->delete($cache_key);
        }

        $ids_arr = $this->repository->getAllIdsArrByIdAsc();
        $this->cache_service->set($cache_key, $ids_arr, $this->getCacheTtlSeconds());

        return $ids_arr;
    }

    protected function getAllIdsArrByIdAscCacheKey(): string
    {
        return self::ALL_IDS_ARR_CACHE_KEY_PREFIX . $this->entity_class_name;
    }

    /** @phpstan-impure */
    public function getById(?int $entity_id, bool $exception_if_not_loaded = true): ?InterfaceEntity
    {
        if ($entity_id === null) {
            return null;
        }

        $cache_key = self::getEntityObjectCacheId($this->entity_class_name, $entity_id);
        $cached_value = $this->cache_service->get($cache_key);

        if ($cached_value instanceof $this->entity_class_name) {
            return $cached_value;
        }
        if ($cached_value !== false) {
            $this->cache_service->delete($cache_key);
        }

        $entity_obj = $this->repository->findById($entity_id);
        if ($entity_obj === null) {
            if ($exception_if_not_loaded) {
                throw new \UnexpectedValueException(
                    'Entity ' . $this->entity_class_name . ' with ID ' . $entity_id . ' was not found'
                );
            }

            return null;
        }
        if (!$entity_obj instanceof $this->entity_class_name) {
            throw new \UnexpectedValueException(
                'Repository returned ' . $entity_obj::class . ' instead of ' . $this->entity_class_name
            );
        }

        $this->cache_service->set($cache_key, $entity_obj, $this->getCacheTtlSeconds());

        return $entity_obj;
    }

    public function removeObjFromCacheById(int $entity_id): void
    {
        $cache_key = self::getEntityObjectCacheId($this->entity_class_name, $entity_id);
        $this->cache_service->delete($cache_key);
    }

    public function removeFromCache(InterfaceEntity $entity_obj): void
    {
        $entity_id = $entity_obj->getId();
        if ($entity_id === null) {
            throw new \LogicException('Cannot remove an unsaved entity from cache');
        }

        $this->removeObjFromCacheById($entity_id);
    }

    public function inTransaction(): bool
    {
        return $this->repository->inTransaction();
    }

    public function beginTransaction(): bool
    {
        return $this->repository->beginTransaction();
    }

    public function commitTransaction(): bool
    {
        return $this->repository->commitTransaction();
    }

    public function rollbackTransaction(): bool
    {
        return $this->repository->rollbackTransaction();
    }

    public function beforeSave(InterfaceEntity $entity_obj): void
    {
        $entity_id = $entity_obj->getId();
        if ($entity_id === null) {
            return;
        }

        $current_entity_obj = $this->getById($entity_id);
        if ($current_entity_obj === null) {
            throw new \LogicException('Existing entity could not be loaded before save');
        }

        $this->removeFromCache($current_entity_obj);
    }

    public function save(InterfaceEntity $entity_obj): void
    {
        $transaction_is_mine = false;

        try {
            if (!$this->repository->inTransaction()) {
                $this->repository->beginTransaction();
                $transaction_is_mine = true;
            }

            $this->beforeSave($entity_obj);
            $this->repository->save($entity_obj);
            $this->callAfterSaveOnce($entity_obj);

            if ($transaction_is_mine) {
                $this->repository->commitTransaction();
            }
        } catch (\Throwable $throwable) {
            if ($transaction_is_mine && $this->repository->inTransaction()) {
                $this->repository->rollbackTransaction();
            }

            throw $throwable;
        }
    }

    public function afterSave(InterfaceEntity $entity_obj): void
    {
        $this->removeFromCache($entity_obj);
        $this->cache_service->delete($this->getAllIdsArrByIdAscCacheKey());
    }

    public function canDelete(InterfaceEntity $entity_obj, string &$message): bool
    {
        return true;
    }

    public function delete(InterfaceEntity $entity_obj): void
    {
        $transaction_is_mine = false;

        try {
            if (!$this->repository->inTransaction()) {
                $this->repository->beginTransaction();
                $transaction_is_mine = true;
            }

            $can_delete_message = '';
            if (!$this->canDelete($entity_obj, $can_delete_message)) {
                throw new \LogicException($can_delete_message);
            }

            $this->repository->delete($entity_obj);
            $this->afterDelete($entity_obj);

            if ($transaction_is_mine) {
                $this->repository->commitTransaction();
            }
        } catch (\Throwable $throwable) {
            if ($transaction_is_mine && $this->repository->inTransaction()) {
                $this->repository->rollbackTransaction();
            }

            throw $throwable;
        }
    }

    public function deleteById(int $entity_id): void
    {
        $entity_obj = $this->getById($entity_id);
        if ($entity_obj === null) {
            throw new \LogicException('Existing entity could not be loaded before delete');
        }

        $this->delete($entity_obj);
    }

    public function afterDelete(InterfaceEntity $entity_obj): void
    {
        $this->removeFromCache($entity_obj);
        $this->cache_service->delete($this->getAllIdsArrByIdAscCacheKey());
    }

    /**
     * @param list<int> $ids_arr
     * @return list<InterfaceEntity>
     */
    protected function getByIds(array $ids_arr): array
    {
        $entity_objs_arr = [];

        foreach ($ids_arr as $id) {
            $entity_obj = $this->getById($id, false);
            if ($entity_obj !== null) {
                $entity_objs_arr[] = $entity_obj;
            }
        }

        return $entity_objs_arr;
    }

    /** @return list<InterfaceEntity> */
    public function getAllObjsArr(): array
    {
        return $this->getByIds($this->getAllIdsArrByIdAsc());
    }

    private function callAfterSaveOnce(InterfaceEntity $entity_obj): void
    {
        /** @var array<string, true> $in_progress */
        static $in_progress = [];

        $in_progress_key = FullObjectId::getFullObjectId($entity_obj);
        if ($in_progress_key === null) {
            throw new \LogicException('Could not create an entity recursion guard key');
        }
        if (isset($in_progress[$in_progress_key])) {
            return;
        }

        $in_progress[$in_progress_key] = true;
        try {
            $this->afterSave($entity_obj);
        } finally {
            unset($in_progress[$in_progress_key]);
        }
    }

    /** @return list<int>|null */
    private static function normalizeIdList(mixed $value): ?array
    {
        if (!is_array($value) || !array_is_list($value)) {
            return null;
        }

        $ids = [];
        foreach ($value as $id) {
            if (is_int($id)) {
                $ids[] = $id;
                continue;
            }
            if (is_string($id) && preg_match('/^-?\d+$/D', $id) === 1) {
                $ids[] = (int)$id;
                continue;
            }

            return null;
        }

        return $ids;
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
}

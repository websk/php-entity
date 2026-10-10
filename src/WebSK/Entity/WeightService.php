<?php

namespace WebSK\Entity;

use WebSK\Cache\CacheService;

/**
 * Class WeightService
 * @package WebSK\Entity
 */
class WeightService extends EntityService
{
    /** @param class-string<InterfaceEntity> $entity_class_name */
    public function __construct(
        string $entity_class_name,
        WeightRepository $repository,
        CacheService $cache_service
    ) {
        parent::__construct($entity_class_name, $repository, $cache_service);
    }

    /**
     * находит в указанном контексте (т.е. для набора пар поле - значение) объект с максимальным весом,
     * меньшим чем у текущего, и меняет текущий объект с ним весами
     * т.е. объект поднимается на одну позицию вверх если сортировать по возрастанию веса
     * @param array<string, mixed> $extra_fields_arr
     */
    public function swapWeights(InterfaceEntity&InterfaceWeight $entity_obj, array $extra_fields_arr = []): void
    {
        $current_item_weight = $entity_obj->getWeight();

        $object_to_swap_weights_id = $this->getWeightRepository()
            ->getObjectToSwapWeightsId($current_item_weight, $extra_fields_arr);

        if ($object_to_swap_weights_id === null) {
            return;
        }

        $object_to_swap_weights_obj = $this->getById($object_to_swap_weights_id);
        if (!$object_to_swap_weights_obj instanceof InterfaceWeight) {
            throw new \UnexpectedValueException('Loaded entity must implement ' . InterfaceWeight::class);
        }

        $object_to_swap_weights_weight = $object_to_swap_weights_obj->getWeight();

        $entity_obj->setWeight($object_to_swap_weights_weight);
        $this->save($entity_obj);

        $object_to_swap_weights_obj->setWeight($current_item_weight);
        $this->save($object_to_swap_weights_obj);
    }

    /**
     * @param array<string, mixed> $context_fields_arr
     * @throws \Exception
     */
    public function initWeight(InterfaceEntity&InterfaceWeight $entity_obj, array $context_fields_arr): void
    {
        if (!is_null($entity_obj->getId())) {
            return;
        }

        // Если передаем вес явно для нового объекта
        if ($entity_obj->getWeight()) {
            return;
        }

        $entity_obj->setWeight(
            $this->getWeightRepository()->getMaxWeightForContext($context_fields_arr) + 1
        );
    }

    private function getWeightRepository(): WeightRepository
    {
        if (!$this->repository instanceof WeightRepository) {
            throw new \LogicException('WeightService requires a WeightRepository');
        }

        return $this->repository;
    }
}

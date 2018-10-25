<?php

namespace WebSK\Entity;

/**
 * Class BaseWeightService
 * @package WebSK\Entity
 */
class BaseWeightService extends BaseEntityService
{
    /** @var BaseWeightRepository */
    protected $repository;

    /**
     * находит в указанном контексте (т.е. для набора пар поле - значение) объект с максимальным весом,
     * меньшим чем у текущего, и меняет текущий объект с ним весами
     * т.е. объект поднимается на одну позицию вверх если сортировать по возрастанию веса
     * @param InterfaceWeight $entity_obj
     * @param array $extra_fields_arr
     */
    public function swapWeights(InterfaceWeight $entity_obj, array $extra_fields_arr = array())
    {
        if (!($entity_obj instanceof InterfaceWeight)) {
            throw new \Exception('Entity class must provide method getWeight');
        }

        $current_item_weight = $entity_obj->getWeight();

        $object_to_swap_weights_id = $this->repository->getObjectToSwapWeightsId($current_item_weight, $extra_fields_arr);

        if (!$object_to_swap_weights_id) {
            return;
        }

        $object_to_swap_weights_obj = $this->getById($object_to_swap_weights_id);

        $object_to_swap_weights_weight = $object_to_swap_weights_obj->getWeight();

        $entity_obj->setWeight($object_to_swap_weights_weight);
        $this->save($entity_obj);

        $object_to_swap_weights_obj->setWeight($current_item_weight);
        $this->save($object_to_swap_weights_obj);
    }

    /**
     * @param InterfaceWeight $entity_obj
     * @param array $context_fields_arr
     * @throws \Exception
     */
    public function initWeight(InterfaceWeight $entity_obj, array $context_fields_arr)
    {
        if (!($entity_obj instanceof InterfaceEntity)) {
            throw new \Exception('Entity class must provide method getId');
        }

        if (is_null($entity_obj->getId())) {
            $entity_obj->setWeight(
                $this->repository->getMaxWeightForContext($context_fields_arr) + 1
            );
        }
    }
}

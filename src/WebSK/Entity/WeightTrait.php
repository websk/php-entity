<?php

namespace WebSK\Entity;

/**
 * Trait WeightTrait
 * @package WebSK\Entity
 */
trait WeightTrait
{
    /** @var int */
    protected $weight;

    /**
     * @param int $weight
     */
    public function setWeight(int $weight)
    {
        $this->weight = $weight;
    }

    /**
     * @return int
     */
    public function getWeight(): int
    {
        return $this->weight;
    }
}

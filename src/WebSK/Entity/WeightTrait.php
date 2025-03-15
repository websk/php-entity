<?php

namespace WebSK\Entity;

/**
 * Trait WeightTrait
 * @package WebSK\Entity
 */
trait WeightTrait
{
    protected int $weight = 0;

    /**
     * @param int $weight
     */
    public function setWeight(int $weight): void
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

<?php

namespace WebSK\Entity;

/**
 * Interface InterfaceWeight
 * @package WebSK\Entity
 */
interface InterfaceWeight
{
    public function getWeight(): int;

    /**
     * @param int $weight
     */
    public function setWeight(int $weight);
}

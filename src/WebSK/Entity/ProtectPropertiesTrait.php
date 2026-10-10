<?php

namespace WebSK\Entity;

/**
 * Trait ProtectPropertiesTrait
 * @package WebSK\Entity
 */
trait ProtectPropertiesTrait
{
    public function __set(string $name, mixed $value): void
    {
        throw new \Exception('Accessing invalid property ' . $name);
    }

    public function __get(string $name): mixed
    {
        throw new \Exception('Accessing invalid property ' . $name);
    }
}

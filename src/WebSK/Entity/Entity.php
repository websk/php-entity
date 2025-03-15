<?php

namespace WebSK\Entity;

/**
 * Class Entity
 * @package WebSK\Entity
 */
abstract class Entity implements
    InterfaceEntity
{
    use ProtectPropertiesTrait;

    const string _CREATED_AT_TS = 'created_at_ts';
    protected int $created_at_ts;

    const string _ID = 'id';
    protected ?int $id = null;

    public function __construct()
    {
        $this->created_at_ts = time();
    }

    /**
     * @return int
     */
    public function getId(): ?int
    {
        return is_null($this->id) ? null : (int)$this->id;
    }

    /**
     * @return int
     */
    public function getCreatedAtTs(): int
    {
        return $this->created_at_ts;
    }

    /**
     * @param int $timestamp
     */
    public function setCreatedAtTs(int $timestamp): void
    {
        $this->created_at_ts = $timestamp;
    }
}

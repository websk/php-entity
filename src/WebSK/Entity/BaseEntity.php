<?php

namespace WebSK\Entity;

/**
 * Class BaseEntity
 * @package WebSK\Entity
 */
abstract class BaseEntity implements
    InterfaceEntity
{
    use ProtectPropertiesTrait;

    const _CREATED_AT_TS = 'created_at_ts';
    /** @var int */
    protected $created_at_ts; // initialized by constructor

    const _ID = 'id';
    /** @var int */
    protected $id;

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
     * @return string
     */
    public function getCreatedAtTs()
    {
        return $this->created_at_ts;
    }

    /**
     * @param string $timestamp
     */
    public function setCreatedAtTs(string $timestamp)
    {
        $this->created_at_ts = $timestamp;
    }
}

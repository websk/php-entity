<?php

namespace WebSK\Entity;

/**
 * Class EntityConfig
 * @package WebSK\Entity
 */
class EntityConfig
{
    protected static array $after_save_subscribers_arr = [];
    protected static array $before_save_subscribers_arr = [];
    protected static bool $ignore_missing_properties_on_load = false;
    protected static bool $ignore_missing_properties_on_save = false;

    /**
     * @return bool
     */
    public static function isIgnoreMissingPropertiesOnSave(): bool
    {
        return self::$ignore_missing_properties_on_save;
    }

    /**
     * @param bool $ignore_missing_properties_on_save
     */
    public static function setIgnoreMissingPropertiesOnSave(bool $ignore_missing_properties_on_save): void
    {
        self::$ignore_missing_properties_on_save = $ignore_missing_properties_on_save;
    }

    /**
     * @return bool
     */
    public static function isIgnoreMissingPropertiesOnLoad(): bool
    {
        return self::$ignore_missing_properties_on_load;
    }

    /**
     * @param bool $ignore_missing_properties_on_load
     */
    public static function setIgnoreMissingPropertiesOnLoad(bool $ignore_missing_properties_on_load): void
    {
        self::$ignore_missing_properties_on_load = $ignore_missing_properties_on_load;
    }
}

<?php

namespace WebSK\Entity;

/**
 * Class EntityConfig
 * @package WebSK\Entity
 */
class EntityConfig
{
    protected static $after_save_subscribers_arr = [];
    protected static $before_save_subscribers_arr = [];
    protected static $ignore_missing_properties_on_load = false;
    protected static $ignore_missing_properties_on_save = false;

    /**
     * @return bool
     */
    public static function isIgnoreMissingPropertiesOnSave()
    {
        return self::$ignore_missing_properties_on_save;
    }

    /**
     * @param bool $ignore_missing_properties_on_save
     */
    public static function setIgnoreMissingPropertiesOnSave($ignore_missing_properties_on_save)
    {
        self::$ignore_missing_properties_on_save = $ignore_missing_properties_on_save;
    }

    /**
     * @return bool
     */
    public static function isIgnoreMissingPropertiesOnLoad()
    {
        return self::$ignore_missing_properties_on_load;
    }

    /**
     * @param bool $ignore_missing_properties_on_load
     */
    public static function setIgnoreMissingPropertiesOnLoad($ignore_missing_properties_on_load)
    {
        self::$ignore_missing_properties_on_load = $ignore_missing_properties_on_load;
    }
}

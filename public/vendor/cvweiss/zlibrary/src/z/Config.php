<?php
/*
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */


namespace z;

/**
 * Class Config
 * @package z
 *
 *  Simple code for defining application wide configuration values.  Also prevents setting a single configuration twice.
 */
class Config
{
    protected static $settings = array();

    /**
     * No.
     */
    private function __construct()
    {
    }

    /**
     * Sets a configuration value.
     * If the configuration value is already set, an exception is thrown.
     *
     * @param $key   string   Configuration key.
     * @param $value mixed    Configuration value.
     * @throws ErrorException If a value has already been set.
     */
    public static function set($key, $value)
    {
        if (isset(static::$settings[$key])) {
            throw new ErrorException("Cannot set a configuration value twice: $key");
        }
        static::$settings[$key] = $value;
    }

    /**
     * Retrieves a configuration value.  If it has not been defined, returns null by default.
     *
     * @param  $key     string Configuration key.
     * @param  $default mixed  Default configuration value.
     * @return mixed           Configuration Value.
     */
    public static function get($key, $default = null)
    {
        return isset(static::$settings[$key]) ? static::$settings[$key] : $default;
    }

    /**
     * Returns true if a configuration value has been set, false otherwise.
     *
     * @param $key  string Configuration key.
     * @return bool        True if key has been set, false otherwise.
     */
    public static function hasValue($key)
    {
        return isset(static::$settings[$key]);
    }

    /**
     * Set multiple configuration values from an array.
     *
     * @param $array array An array of key/value pairs for configuration.
     */
    public static function setConfig($array)
    {
        foreach ($array as $key => $value) {
            static::set($key, $value);
        }
    }
}

<?php
/* zLibrary
 *
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

/**
 * Extremely temporary storage.
 */
class Bin
{
	private static $bin = array();

	/**
	 * get something from storage
	 *
	 * @static
	 * @param string $name
	 * @param mixed $default
	 * @return mixed
	 */
	public static function get($name, $default = null)
	{
		if (isset(self::$bin[$name])) return self::$bin[$name];
		return $default;
	}

	/**
	 * Store something
	 *
	 * @static
	 * @param string $name
	 * @param mixed $value
	 */
	public static function set($name, $value)
	{
		self::$bin[$name] = $value;
	}
}

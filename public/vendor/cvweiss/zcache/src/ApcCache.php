<?php
/* zCache
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
 * APC Cache Class
 */
class ApcCache extends AbstractCache
{
	public function get($key)
	{
		return apc_fetch($key);
	}

	/**
	 * @param string $timeout
	 */
	public function set($key, $value, $timeout)
	{
		return apc_store($key, $value, $timeout);
	}

	/**
	 * @param string $timeout
	 */
	public function replace($key, $value, $timeout)
	{
		if(!apc_exists($key))
			return false;
		apc_store($key, $value, $timeout);
	}

	/**
	 * @param string $key
	 */
	public function delete($key)
	{
		return apc_delete($key);
	}

	public function increment($key, $step = 1, $timeout = 0)
	{
		apc_add($key, 0, $timeout);
		return apc_inc($key, $step);
	}

	public function decrement($key, $step = 1, $timeout = 0)
	{
		apc_add($key, 0, $timeout);
		return apc_dec($key, $step);
	}

	public function flush()
	{
		return apc_clear_cache();
	}
 }

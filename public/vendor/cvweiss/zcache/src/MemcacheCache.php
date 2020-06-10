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
 * Abstract Cache Class
 */
class MemcacheCache extends AbstractCache
{
	private $mc;

	public function __construct()
	{
		global $memcacheServer, $memcachePort;

		$this->mc = new Memcache();
		$this->mc->connect($memcacheServer, $memcachePort);
	}

	public function get($key)
	{
		return $this->mc->get($key);
	}

	/**
	 * @param string $timeout
	 */
	public function set($key, $value, $timeout)
	{
		return $this->mc->set($key, $value, 0, $timeout);
	}

	/**
	 * @param string $timeout
	 */
	public function replace($key, $value, $timeout)
	{
		return $this->mc->replace($key, $value, 0, $timeout);
	}

	/**
	 * @param string $key
	 */
	public function delete($key)
	{
		return $this->mc->delete($key);
	}

	public function increment($key, $step = 1, $timeout = 0)
	{
		$this->mc->add($key, 0, 0, $timeout);
		return $this->mc->increment($key, $step);
	}

	public function decrement($key, $step = 1, $timeout = 0)
	{
		$this->mc->add($key, 0, 0, $timeout);
		return $this->mc->decrement($key, $step);
	}

	public function flush()
	{
		return $this->mc->flush();
	}
}

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
 * FileCache for zKillboard (Basically copies what Memcached does)
 */
class FileCache extends AbstractCache
{
	/**
	 * @param $cacheDir the default cache dir
	 * @param $cacheTime the default cache time (5 minutes)
	 */

	protected $cacheDir = "/tmp/zkb/";
	protected $cacheTime = 300;

	/**
	 * @param string $cd a dir to use instead of default
	 */
	public function __construct($cd = null)
	{
		global $cacheDir;
		if (isset($cacheDir) && $cd === null) {
			$cd = $cacheDir;
		}

		if (!is_null($cd)) $this->cacheDir = $cd;
		if (!is_dir($this->cacheDir)) mkdir($this->cacheDir);
	}

	/**
	 * Gets the data
	 *
	 * @param string $key
	 * @return array|boolean
	 */
	public function get($key)
	{
		if(file_exists($this->cacheDir.sha1($key)))
		{
			$time = time();
			$data = self::getData($key);
			$age = $data["age"];
			$data = json_decode($data["data"], true);
			if($age <= $time)
			{
				@unlink($this->cacheDir.sha1($key));
				return false;
			}
			return $data;
		}
		else
			return false;
	}

	/**
	 * Sets data
	 *
	 * @param string $key
	 * @param string|array $value
	 * @param string $timeout
	 *
	 * return bool
	 */
	public function set($key, $value, $timeout)
	{
		return self::setData($key, $value, $timeout) !== false;
	}

	/**
	 * Replaces data
	 *
	 * @param string $key
	 * @param string|array $value
	 * @param string $timeout
	 * @return boolean
	 */
	public function replace($key, $value, $timeout)
	{
		if(file_exists($this->cacheDir.sha1($key)))
		{
			@unlink($this->cacheDir.sha1($key));
			if(self::setData($key, $value, $timeout) !== false)
				return true;
		}

		return false;
	}

	/**
	 * Deletes a key
	 *
	 * @param string $key
	 * @return bool
	 */
	public function delete($key)
	{
		try
		{
			@unlink($this->cacheDir.sha1($key));
		}
		catch (Exception $e)
		{
			return false;
		}
		return true;
	}

	/**
	 * Increments value
	 *
	 * @param string $key
	 * @param int $step
	 * @param int $timeout
	 * @return bool
	 */
	public function increment($key, $step = 1, $timeout = 0)
	{
		$data = self::getData($key);
		$data = json_decode($data["data"], true);

		try
		{
			@unlink($this->cacheDir.sha1($key));
			return self::setData($key, $data+$step);
		}
		catch (Exception $e)
		{
			return false;
		}
	}

	/**
	 * Decrements value
	 *
	 * @param string $key
	 * @param int $step
	 * @param int $timeout
	 * @return bool
	 */
	public function decrement($key, $step = 1, $timeout = 0)
	{
		$data = self::getData($key);
		$data = json_decode($data["data"], true);

		try
		{
			@unlink($this->cacheDir.sha1($key));
			return self::setData($key, $data-$step);
		}
		catch (Exception $e)
		{
			return false;
		}
	}

	/**
	 * Flushes the cache
	 */
	public function flush()
	{
		$dir = opendir($this->cacheDir);
		while($file = readdir($dir))
		{
			@unlink($this->cacheDir.$file);
		}
	}

	/**
	 * Sets data to cache file
	 *
	 * @param string $key
	 * @param string|array $value
	 * @param int $timeout
	 *
	 * return bool
	 */
	private function setData($key, $value, $timeout = NULL)
	{
		if(!$timeout)
			$timeout = $this->cacheTime;

		try
		{
			// fix, so timeout will be timestamp based
			$timeout = time() + $timeout;

			$data = $timeout."%".json_encode($value);
			file_put_contents($this->cacheDir.sha1($key), $data);
		}
		catch (Exception $e)
		{
			return false;
		}
		return $value;
	}

	/**
	 * Gets the data from the cache
	 *
	 * @param string $key
	 * @param bool $sha
	 * @return array
	 */
	private function getData($key, $sha = true)
	{
		// @todo real error handling, not just surpression.
		if($sha == true)
			$data = @file_get_contents($this->cacheDir.sha1($key));
		else
			$data = @file_get_contents($this->cacheDir.$key);
		$f = explode("%", $data, 2); // We only want the first occurance of % exploded, not everything else aswell.
		$age = array_shift($f);
		$data = implode($f);
		return array("age" => (int) $age, "data" => $data);
	}

	/**
	 * Cleans up old and unused query cache files
	 */
	public function cleanUp()
	{
		$dir = opendir($this->cacheDir);
		while($file = readdir($dir))
		{
			if($file != "." && $file != "..")
			{
				$data = self::getData($file, false);
				$age = $data["age"];
				$time = time();
				if($age <= $time)
				{
					@unlink($this->cacheDir.$file);
				}
			}
		}
	}
}

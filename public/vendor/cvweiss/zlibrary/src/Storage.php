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

class Storage
{
	public static function retrieve($locker, $default = null)
	{
		if (!isset($locker) || $locker === null) return $default;
		$contents = Db::queryField("select contents from zz_storage where locker = :locker", "contents", array(":locker" => $locker), 1);
		if ($contents === null) return $default;
		return $contents;
	}

	public static function store($locker, $contents)
	{
		return Db::execute("replace into zz_storage (locker, contents) values (:locker, :contents)", array(":locker" => $locker, ":contents" => $contents));
	}
}

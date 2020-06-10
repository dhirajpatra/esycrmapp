<?php
/* zCache
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY{ }  without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * No Cache Class
 */
class NoCache
{
	public function get($key){ return false; }
	public function set($key, $value, $timeout){return false;} 
	public function replace($key, $value, $timeout){return false;} 
	public function delete($key){return false; } 
	public function increment($key, $step, $timeout){return false; } 
	public function decrement($key, $step, $timeout){return false;} 
	public function flush(){return true;} 
}

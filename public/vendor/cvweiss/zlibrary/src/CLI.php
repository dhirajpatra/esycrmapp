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

class CLI
{
	/**
	 * @param string $message
	 */
	public static function out($message, $die = false)
	{
		$colors = array(
				"|w|" => "1;37", //White
				"|b|" => "0;34", //Blue
				"|g|" => "0;32", //Green
				"|r|" => "0;31", //Red
				"|n|" => "0" //Neutral
			       );

		foreach($colors as $color => $value)
			$message = str_replace($color, "\033[".$value."m", $message);

		echo $message.PHP_EOL;
		if($die) die();
	}

	/**
	 * @param string $prompt
	 */
	public static function prompt($prompt, $default = "") {
		$colors = array(
				"|w|" => "1;37", //White
				"|b|" => "0;34", //Blue
				"|g|" => "0;32", //Green
				"|r|" => "0;31", //Red
				"|n|" => "0" //Neutral
			       );

		foreach($colors as $color => $value)
			$prompt = str_replace($color, "\033[".$value."m", $prompt);

		echo "$prompt [$default] ";
		$answer = trim(fgets(STDIN));
		if (strlen($answer) == 0) return $default;
		return $answer;
	}

	// Password prompter kindly borrowed from http://stackoverflow.com/questions/187736/command-line-password-prompt-in-php
	public static function prompt_silent($prompt = "Enter Password:") {
		$command = "/usr/bin/env bash -c 'echo OK'";
		if (rtrim(shell_exec($command)) !== 'OK') {
			trigger_error("Can't invoke bash");
			return;
		}
		$command = "/usr/bin/env bash -c 'read -s -p \""
			. addslashes($prompt)
			. "\" mypassword && echo \$mypassword'";
		$password = rtrim(shell_exec($command));
		echo "\n";
		return $password;
	}
}

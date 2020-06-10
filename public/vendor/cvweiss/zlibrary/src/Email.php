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

class Email
{

	/**
	 * @param string $email
	 * @param string $subject
	 * @param string $body
	 * @return string
	 */
	public static function send($email, $subject, $body)
	{
		global $emailsmtp, $emailport, $emailusername, $emailpassword, $sentfromemail, $sentfromdomain, $baseDir;
		$mail = new PHPMailer();
		$mail->isSMTP();
		$mail->SMTPDebug = 0;
		$mail->SMTPAuth = true;
		$mail->Host = $emailsmtp;
		$mail->Port = $emailport;
		$mail->Username = $emailusername;
		$mail->Password = $emailpassword;
		$mail->SetFrom($sentfromemail, $sentfromdomain);
		$mail->Subject = $subject;
		$mail->Body = $body;
		$mail->AddAddress($email);
		if (!$mail->Send()) {
			Log::log("Error sending email to $email: " . $mail->ErrorInfo);
			echo "Mail error: " . $mail->ErrorInfo;
		} else {
			Log::log("Email sent to $email with subject '$subject'");
			return "Success";
		}
	}
}

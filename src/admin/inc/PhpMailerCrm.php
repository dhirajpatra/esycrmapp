<?php

namespace App\admin\inc;

// Import PHPMailer classes into the global namespace
// These must be at the top of your script, not inside a function
use PHPMailer\PHPMailer\PHPMailer;

// // Load Composer's autoloader
require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

class PhpMailerCrm {
	// Instantiation and passing `true` enables exceptions
	public function send($to, $from, $reply, $subject, $body) {
		try {
			$mail = new PHPMailer(true);
			//Server settings
			$mail->SMTPDebug = SMTP_DEBUG; // Enable verbose debug output
			$mail->isSMTP(); // Send using SMTP
			$mail->SMTPKeepAlive = true;
			$mail->CharSet = 'UTF-8';
			$mail->Host = GSMTP; // Set the SMTP server to send through
			$mail->SMTPAuth = SMTP_AUTH; // Enable SMTP authentication
			$mail->Username = GUSER; // SMTP username
			$mail->Password = GPWD; // SMTP password
			$mail->SMTPSecure = SMTP_SECURE; // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` also accepted
			$mail->Port = GPORT; // TCP port to connect to
			$mail->SMTPOptions = array(
				'ssl' => array(
					'verify_peer' => false,
					'verify_peer_name' => false,
					'allow_self_signed' => true,
				),
			);
			$mail->ContentType = 'text/html; charset=utf-8\r\n';

			//Recipients
			$mail->setFrom($from['email'], $from['name']);
			$mail->addAddress($to['email'], $to['name']); // Add a recipient
			// $mail->addAddress('ellen@example.com');               // Name is optional
			$mail->addReplyTo($reply['email'], $reply['name']);
			// $mail->addCC('cc@example.com');
			// $mail->addBCC('bcc@example.com');

			// Attachments
			// $mail->addAttachment('/var/tmp/file.tar.gz');         // Add attachments
			// $mail->addAttachment('/tmp/image.jpg', 'new.jpg');    // Optional name

			// Content
			$mail->isHTML(true); // Set email format to HTML
			$mail->Subject = $subject;
			$mail->Body = $body;
			// $mail->AltBody = $body;

			$mail->send();
			return true;
		} catch (phpmailerException $e) {
			// print_r($e);exit;
			// write into log
			$log_file = $_SERVER['DOCUMENT_ROOT'] . '/../log.txt';
			file_put_contents($log_file, "\n" . date('d-m-Y H:i:s') . ': PHPMailer Error: ' . $e->getMessage());
			// return false;
		}
	}
}

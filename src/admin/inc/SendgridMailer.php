<?php
namespace App\admin\inc;

// If you're using Composer (recommended)
require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
// Comment out the above line if not using Composer
// require("<PATH TO>/sendgrid-php.php");
// If not using Composer, uncomment the above line and
// download sendgrid-php.zip from the latest release here,
// replacing <PATH TO> with the path to the sendgrid-php.php file,
// which is included in the download:
// https://github.com/sendgrid/sendgrid-php/releases

/**
 * send mail via SendGrid
 */
class SendgridMailer {

	private $email;
	private $sendGrid;

	function __construct() {
		$this->email = new \SendGrid\Mail\Mail();
		$this->sendgrid = new \SendGrid(getenv('SENDGRID_API_KEY'));
	}

/**
 * send email
 * @param  [type] $to      [description]
 * @param  [type] $from    [description]
 * @param  [type] $reply   [description]
 * @param  [type] $subject [description]
 * @param  [type] $body    [description]
 * @return [type]          [description]
 */
	public function send($to, $from, $reply, $subject, $body) {

		$this->email->setFrom($from['email'], $from['name']);
		$this->email->setSubject($subject);
		$this->email->addTo($to['email'], $to['name']);
		$this->email->addContent("text/plain", $body);
		$this->email->addContent(
			"text/html", $body
		);

		try {
			$response = $this->sendgrid->send($this->email);
			return true;
			// $response->statusCode();
			// print_r($response->headers());
			// print $response->body() . "\n";
		} catch (Exception $e) {
			$log_file = $_SERVER['DOCUMENT_ROOT'] . '/../log.txt';
			file_put_contents($log_file, "\n" . date('d-m-Y H:i:s') . ': SendGrid Mailer Error: ' . $e->getMessage());
		}
	}
}

<?php

declare (strict_types = 1);

namespace App\admin\inc;

use App\admin\inc\Misc;
use App\ConnectDb;

/**
 * this class will process user log system
 */
class Log {
	private $dbConn;
	private $misc;
	private $ip;
	private $loggedin_user_id;
	private $uri;

	public function __construct() {
		$this->dbConn = ConnectDb::getConnection();
		$this->misc = new Misc();
		$this->misc->set_env();
		// checking session
		// $this->misc->check_session();

		$this->loggedin_user_id = isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : null;
		$this->ip = USER_IP;
		$this->uri = $_SERVER['REQUEST_URI'];
	}

	/**
	 * it will write into log for user every call
	 */
	public function write($change_done = '') {
		try {
			// check whether last one also the same request_uri in same date no need to add another rec
			$sql = $this->dbConn->prepare("select * from user_logs where user_id = ?
                and DATE(updated_at) = CURDATE()
                order by id desc limit 1");
			$sql->execute(array($this->loggedin_user_id));

			$count = $sql->rowCount();
			if ($count > 0) {
				while ($row = $sql->fetch()) {
					preg_match('/^\\' . $this->uri . '/', $row['request_uri'], $matches);
					if (empty($matches)) {
						$sql_insert = $this->dbConn->prepare("insert into user_logs (user_id, ip, request_uri, changes_done) values (?, ?, ?, ?)");
						$result = $sql_insert->execute(array($this->loggedin_user_id, $this->ip, $this->uri, $change_done));
					} elseif ($change_done != '') {
						$sql_update = $this->dbConn->prepare("update user_logs set changes_done = ?, updated_at = ? where id = ?");
						$result = $sql_update->execute(array($change_done, date("Y-m-d H:i:s"), $row['id']));
					}
				}
			}

			return true;
		} catch (\Throwable $th) {
			//throw $th;
		}
	}

	/**
	 * it will remove log for a user of more than expiry day old
	 */
	public function remove() {
		try {
			$sql = $this->dbConn->prepare("delete from user_logs where user_id = ? and updated_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
			$result = $sql->execute(array($this->loggedin_user_id, LOG_EXPIRY));

			return true;
		} catch (\Throwable $th) {
			//throw $th;
		}
	}

	/**
	 * get the last module location or work for this user before logout
	 */
	public function get_last_location() {
		try {
			$sql = $this->dbConn->prepare("select * from user_logs where user_id = ? order by id desc limit 3");
			$sql->execute(array($this->loggedin_user_id));
			$result = [];

			$count = $sql->rowCount();
			if ($count > 0) {
				while ($row = $sql->fetch()) {
					// logout not require
					preg_match('/^\/logout/', $row['request_uri'], $matches1);
					preg_match('/^\/login/', $row['request_uri'], $matches2);
					if (empty($matches1) && empty($matches2)) {
						$result[] = $row;
						break;
					}
				}
			}

			return !empty($result) ? $result[0] : array();
		} catch (\Throwable $th) {
			//throw $th;
		}
	}
}
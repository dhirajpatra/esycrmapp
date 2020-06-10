<?php
declare (strict_types = 1);

namespace App\admin\manager;

use App\admin\inc\Log;
use App\admin\inc\Misc;
use App\admin\inc\PhpMailerCrm;
use App\ConnectDb;

class Superadmin {
	private $twig;
	private $dbConn;
	private $misc;
	private $phpMailerObj;
	private $admin_to_go;
	private $link;
	private $nav_link;
	private $key;
	private $stage;
	private $stages;
	private $duration_select;
	private $countries;
	private $owner;
	private $deals;
	private $todo_descs;
	private $loggedin_user_id;
	private $company_id;
	private $loggedin_user_registration_id;
	private $currency;
	private $leadsSteps;
	private $log;
	private $googleCalendarApiObj;
	private $google_auth_url;
	private $google_redirect_url;
	private $header_totals;

	public function __construct(
		$twig
	) {
		$this->twig = $twig;
		$this->dbConn = ConnectDb::getConnection();
		$this->misc = new Misc();
		$this->phpMailerObj = new PhpMailerCrm();
		$this->log = new Log();

		// checking session
		$this->misc->check_session();

		$path = explode('/', $_SERVER['REQUEST_URI']);
		$this->admin_to_go = '';
		$this->link = '';

		$this->loggedin_user_id = $_SESSION['user']['id'];
		$this->loggedin_user_registration_id = $_SESSION['user']['registration_id'];
		$this->company_id = $_SESSION['user']['registration_id'];
		$this->currency = $_SESSION['user']['currency_code'];

		$this->sidebar_essentials();

		if (!isset($_SESSION['csrf']) || $_SESSION['csrf'] == '') {
			$this->key = sha1(microtime());
			$_SESSION['csrf'] = $this->key;
		}

		// write into log
		$this->log->write();
	}

	/**
	 *  manager dashboard including analytics and chart
	 */
	public function __invoke() {
		try {

			$this->loggedin_user_id = $_SESSION['user']['id'];

			// chart preparation date range
			if ((!array_key_exists('analytics_from', $_SESSION) || $_SESSION['analytics_from'] != '') && (!array_key_exists('analytics_to', $_SESSION) || $_SESSION['analytics_to'] != '')) {
				$from = date("Y-m-d", strtotime(date("Y-m-d", strtotime(date("Y-m-d"))) . "-1 month"));
				$to = date('Y-m-d');
				$_SESSION['analytics_from'] = $from;
				$_SESSION['analytics_to'] = $to;
			}

			// calling analytics data default 1 month date range
			$analytics = new Analyticssuper($this->dbConn, $_SESSION['analytics_from'], $_SESSION['analytics_to']);
			$result = $analytics->getDashboardDetails();
			// p($result); exit;
			$no_of_contacts = isset($result['contacts']) ? $result['contacts'] : 0;
			$no_of_active_deals = isset($result['active_deals']['total']) ? $result['active_deals']['total'] : 0; // as total also a index/row
			$total_value_of_active_deals = isset($result['active_deals']['total_value']) ? number_format($result['active_deals']['total_value'], 2) : 0;
			$no_of_won_deals = isset($result['won_deals']['total']) ? $result['won_deals']['total'] : 0;
			$won_deals_value = $no_of_won_deals > 0 ? number_format($result['won_deals']['total_value'], 2) : 0;
			$avg_won_deals_value = $no_of_won_deals > 0 ? number_format($result['won_deals']['avg'], 2) : 0;
			$no_of_lost_deals = $result['lost_deals']['total'];
			$lost_deal_value = $no_of_lost_deals > 0 ? number_format($result['lost_deals']['total_value']) : 0;
			$avg_own_deals = $result['avg_own_deals'];
			$avg_sales_cycle = round($result['avg_sales_cycle']);
			$conversion_rate = $result['conversion_rate'];
			$contacts_bulk_uploaded = $result['contacts_bulk_uploaded'];
			$google_calendar_push = $result['google_calendar_push'];
			$task_to_do = $result['tasks_to_do'];
			$task_completed = $result['tasks_completed'];
			$task_completed = $result['tasks_completed'];
			$all_task_to_do_total = 0;
			$all_task_completed_total = 0;
			$task_completed_email = 0;
			$task_completed_phone = 0;
			$task_completed_meeting = 0;
			$task_completed_task = 0;

			if (!empty($task_to_do)) {
				$all_task_to_do_total = $task_to_do['all_total'];
			}

			if (!empty($task_completed)) {
				$all_task_completed_total = $task_completed['all_total'];
				// for each type of task completed as total
				foreach ($task_completed as $task) {
					switch ($task['description']) {
					case 'Email':
						$task_completed_email = $task['total'];
						break;
					case 'Phone':
						$task_completed_phone = $task['total'];
						break;
					case 'Meeting':
						$task_completed_meeting = $task['total'];
						break;
					default:
						$task_completed_task = $task['total'];
						break;
					}
				}
			}

			$response = $this->twig->render('admin/super/pipeline.html.twig', ['uri' => $_SERVER['REQUEST_URI'], 'session_user' => isset($_SESSION['user']) ? $_SESSION['user'] : [], 'admin_to_go' => $this->admin_to_go, 'link' => $this->link, 'nav_link' => $this->nav_link, 'key' => $_SESSION['csrf'], 'user_token' => $_SESSION['user_token'], 'currency' => $this->currency, 'no_of_contacts' => $no_of_contacts, 'no_of_active_deals' => $no_of_active_deals, 'no_of_won_deals' => $no_of_won_deals, 'no_of_lost_deals' => $no_of_lost_deals, 'todo_descs' => $this->todo_descs, 'deals' => $this->deals, 'countries' => $this->countries, 'stage' => $this->stage, 'stages' => $this->stages, 'duration_select' => $this->duration_select, 'owner' => $this->owner, 'from' => $_SESSION['analytics_from'], 'to' => $_SESSION['analytics_to'], 'google_auth_url' => $this->google_auth_url, 'header_totals' => $this->header_totals, 'avg_own_deals' => $avg_own_deals, 'avg_sales_cycle' => $avg_sales_cycle, 'total_value_of_active_deals' => $total_value_of_active_deals, 'won_deals_value' => $won_deals_value, 'avg_won_deals_value' => $avg_won_deals_value, 'conversion_rate' => $conversion_rate, 'lost_deal_value' => $lost_deal_value, 'no_of_tasks_to_do' => $all_task_to_do_total, 'task_completed' => $task_completed, 'no_of_tasks' => $all_task_completed_total, 'task_completed_email' => $task_completed_email, 'task_completed_meeting' => $task_completed_meeting, 'task_completed_task' => $task_completed_task, 'task_completed_phone' => $task_completed_phone, 'contacts_bulk_uploaded' => $contacts_bulk_uploaded, 'google_calendar_push' => $google_calendar_push]);

			return $response;
		} catch (\Exception $exception) {
			$this->misc->log('Manager ' . __METHOD__, $exception);
		}
	}

	/**
	 * ajax call for the analytics reports same as dashboard
	 *
	 */
	public function get_analytics_date_range(): string {
		try {

			if ($_SERVER["REQUEST_METHOD"] == "POST") {
				$this->loggedin_user_id = $_SESSION['user']['id'];

				$from = date('Y-m-d', strtotime($_POST['super_analytics_from']));
				$to = date('Y-m-d', strtotime($_POST['super_analytics_from']));
				$_SESSION['analytics_from'] = $from;
				$_SESSION['analytics_to'] = $to;

				// calling analytics data default 1 month date range
				$analytics = new Analyticssuper($this->dbConn, $from, $to);
				$result = $analytics->getDashboardDetails();

				// p($result); exit;
				$no_of_contacts = isset($result['contacts']) ? $result['contacts'] : 0;
				$no_of_active_deals = isset($result['active_deals']['total']) ? $result['active_deals']['total'] : 0; // as total also a index/row
				$total_value_of_active_deals = isset($result['active_deals']['total_value']) ? number_format($result['active_deals']['total_value'], 2) : 0;
				$no_of_won_deals = isset($result['won_deals']['total']) ? $result['won_deals']['total'] : 0;
				$won_deals_value = $no_of_won_deals > 0 ? number_format($result['won_deals']['total_value'], 2) : 0;
				$avg_won_deals_value = $no_of_won_deals > 0 ? number_format($result['won_deals']['avg'], 2) : 0;
				$no_of_lost_deals = $result['lost_deals']['total'];
				$lost_deal_value = $no_of_lost_deals > 0 ? number_format($result['lost_deals']['total_value']) : 0;
				$avg_own_deals = $result['avg_own_deals'];
				$avg_sales_cycle = round($result['avg_sales_cycle']);
				$conversion_rate = $result['conversion_rate'];
				$contacts_bulk_uploaded = $result['contacts_bulk_uploaded'];
				$google_calendar_push = $result['google_calendar_push'];
				$task_to_do = $result['tasks_to_do'];
				$task_completed = $result['tasks_completed'];
				$all_task_to_do_total = 0;
				$all_task_completed_total = 0;
				$task_completed_email = 0;
				$task_completed_phone = 0;
				$task_completed_meeting = 0;
				$task_completed_task = 0;

				if (!empty($task_to_do)) {
					$all_task_to_do_total = $task_to_do['all_total'];
				}

				if (!empty($task_completed)) {
					$all_task_completed_total = $task_completed['all_total'];
					// for each type of task completed as total
					foreach ($task_completed as $task) {
						switch ($task['description']) {
						case 'Email':
							$task_completed_email = $task['total'];
							break;
						case 'Phone':
							$task_completed_phone = $task['total'];
							break;
						case 'Meeting':
							$task_completed_meeting = $task['total'];
							break;
						default:
							$task_completed_task = $task['total'];
							break;
						}
					}
				}

				$response = $this->twig->render('admin/super/analytics.html.twig', ['uri' => $_SERVER['REQUEST_URI'], 'session_user' => isset($_SESSION['user']) ? $_SESSION['user'] : [], 'admin_to_go' => $this->admin_to_go, 'link' => $this->link, 'nav_link' => $this->nav_link, 'key' => $_SESSION['csrf'], 'user_token' => $_SESSION['user_token'], 'currency' => $this->currency, 'no_of_contacts' => $no_of_contacts, 'no_of_active_deals' => $no_of_active_deals, 'no_of_won_deals' => $no_of_won_deals, 'no_of_lost_deals' => $no_of_lost_deals, 'todo_descs' => $this->todo_descs, 'deals' => $this->deals, 'countries' => $this->countries, 'stage' => $this->stage, 'stages' => $this->stages, 'duration_select' => $this->duration_select, 'owner' => $this->owner, 'from' => $from, 'to' => $to, 'google_auth_url' => $this->google_auth_url, 'header_totals' => $this->header_totals, 'avg_own_deals' => $avg_own_deals, 'avg_sales_cycle' => $avg_sales_cycle, 'total_value_of_active_deals' => $total_value_of_active_deals, 'won_deals_value' => $won_deals_value, 'avg_won_deals_value' => $avg_won_deals_value, 'conversion_rate' => $conversion_rate, 'top_sales_sources' => $top_sales_sources, 'lost_deal_value' => $lost_deal_value, 'no_of_tasks_to_do' => $all_task_to_do_total, 'task_completed' => $task_completed, 'no_of_tasks' => $all_task_completed_total, 'task_completed_email' => $task_completed_email, 'task_completed_meeting' => $task_completed_meeting, 'task_completed_task' => $task_completed_task, 'task_completed_phone' => $task_completed_phone, 'contacts_bulk_uploaded' => $contacts_bulk_uploaded, 'google_calendar_push' => $google_calendar_push]);

				return $response;
			}
		} catch (\Exception $exception) {
			$this->misc->log('Manager ' . __METHOD__, $exception);
		}
	}

	/**
	 * get all representatives for this manager
	 */
	public function companies() {
		try {
			$result = [];

			// it will fetch all sales rep under the manager including herself
			$sql = $this->dbConn->prepare("SELECT u.id as uid, u.User_Status, u.Name_First, u.Name_Last,
                u.Email, u.last_logged_in, u.registration_id,
                r.id as rid, r.company_name, u.created_at,
                (select count(id)
                from users where registration_id = u.registration_id) as total_sales_rep
                FROM users u
                inner join registrations r on r.id = u.registration_id
                where u.User_Roles = ?
                order by r.id desc, u.id desc");
			$sql->execute(array(2));
			$count = $sql->rowCount();

			if ($count > 0) {
				while ($row = $sql->fetch()) {
					// get no of contacts from this company
					$query_contacts = 'SELECT count(c.id) as cnt
	                FROM contact c
	                inner join users u on u.id = c.Sales_Rep
	                where u.registration_id = ?';
					$sql_contacts = $this->dbConn->prepare($query_contacts);

					$sql_contacts->execute(array($row['registration_id']));
					$count_contacts = $sql_contacts->rowCount();
					$contacts = 0;
					if ($count_contacts > 0) {
						while ($row_contacts = $sql_contacts->fetch()) {
							$contacts = $row_contacts['cnt'];
							break;
						}
					}

					// no of deals
					$query_deals = 'SELECT count(d.id) as cnt
	                FROM deals d
	                inner join users u on u.id = d.sales_rep
	                where u.registration_id = ? limit 1';
					$sql_deals = $this->dbConn->prepare($query_deals);
					$sql_deals->execute(array($row['registration_id']));
					$count_deals = $sql_deals->rowCount();

					if ($count_deals > 0) {
						$deals = 0.0;
						while ($row_deal = $sql_deals->fetch()) {
							$deals += $row_deal['cnt'];
							break;
						}
					}

					// checking whether logged in is > one month old or not
					if ($row['last_logged_in'] < date("Y-m-d H:i:s", strtotime("first day of previous month"))) {
						$row['more_than_one_month_ago'] = true;
					} else {
						$row['more_than_one_month_ago'] = false;
					}
					$row['contacts'] = $contacts;
					$row['deals'] = $deals;

					$result[] = $row;
				}
			}

			$response = $this->twig->render('admin/super/company.html.twig', ['uri' => $_SERVER['REQUEST_URI'], 'key' => $_SESSION['csrf'], 'user_token' => $_SESSION['user_token'], 'result' => $result, 'session_user' => isset($_SESSION['user']) ? $_SESSION['user'] : [], 'admin_to_go' => $this->admin_to_go, 'link' => $this->link, 'stages' => $this->stages, 'total' => $count]);

			return $response;

		} catch (\Exception $exception) {
			$this->misc->log('Manager ' . __METHOD__, $exception);
		}
	}

	/**
	 * ajax process to block or unblock a company
	 */
	public function status_update_company() {
		try {
			if ($_SERVER["REQUEST_METHOD"] == "POST" && $_SESSION['csrf'] == $_POST['csrf']) {

				$sql = $this->dbConn->prepare("update users set User_Status = ? where registration_id = ?");
				// if it is active then it will be inactive or vice versa
				$update_status = $_POST['status'] == 1 ? 2 : 1;
				$result = $sql->execute(array($update_status, $_POST['registration_id']));

				if ($result) {
					// send mail after update the values from template
					$sql_template = $this->dbConn->prepare("select * from mails where module = ? limit 1");
					$module = $_POST['status'] == 1 ? 'block-company' : 'unblock-company';
					$sql_template->execute(array($module));
					$count_template = $sql_template->rowCount();

					if ($count_template > 0) {
						while ($row_template = $sql_template->fetch()) {
							$body = str_ireplace('{{FIRST_NAME}}', $_POST['Name_First'], $row_template['body']);
							$body = str_ireplace('{{COMPANY}}', $_POST['company_name'], $body);
							$body = str_ireplace('{{SalesCRM}}', APP_NAME, $body);
							$body = str_ireplace('{{http://www.salescrm.com}}', WEBSITE_LINK, $body);
							$body = str_ireplace('{{www.salescrm.com}}', WEBSITE, $body);
							$body = str_ireplace('{{ENCODED_EMAIL}}', WEBSITE, base64_encode($email));
							$subject = $row_template['subject'];
						}
					}

					$to = [
						'email' => $_POST['Email'],
						'name' => $_POST['Name_First'],
					];

					$from = [
						'email' => MAIL_FROM_EMAIL,
						'name' => MAIL_FROM_NAME,
					];

					$reply = [
						'email' => MAIL_REPLY_TO_EMAIL,
						'name' => MAIL_REPLY_TO_NAME,
					];

					// sending mail and saving data
					if ($this->phpMailerObj->smtpmailer($to, $from, $reply, $subject, $body)) {
						echo '200';
						exit;
					} else {
						echo '400';
						exit;
					}
				} else {
					echo '409';
					exit;
				}
			}

		} catch (\Exception $exception) {
			$this->misc->log('Manager ' . __METHOD__, $exception);
		}
	}

	/**
	 * this method will fetch all mails to edit, add and update
	 */
	public function mails() {
		try {
			$result = [];

			// it will fetch all sales rep under the manager including herself
			$sql = $this->dbConn->prepare("SELECT *
                from mails
                order by id asc");
			$sql->execute(array());
			$count = $sql->rowCount();

			if ($count > 0) {
				while ($row = $sql->fetch()) {
					$result[] = $row;
				}
			}

			$response = $this->twig->render('admin/super/mail.html.twig', ['uri' => $_SERVER['REQUEST_URI'], 'key' => $_SESSION['csrf'], 'user_token' => $_SESSION['user_token'], 'result' => $result, 'session_user' => isset($_SESSION['user']) ? $_SESSION['user'] : [], 'admin_to_go' => $this->admin_to_go, 'link' => $this->link, 'stages' => $this->stages]);

			return $response;
		} catch (\Exception $exception) {
			$this->misc->log('Manager ' . __METHOD__, $exception);
		}
	}

	/**
	 * ajax process to update a mail details
	 */
	public function update_mail() {
		try {
			if ($_SERVER["REQUEST_METHOD"] == "POST" && $_SESSION['csrf'] == $_POST['csrf']) {
				$body = $_POST['body_' . $_POST['id']];

				$sql = $this->dbConn->prepare("update mails set body = ? where id = ?");
				$result = $sql->execute(array($body, $_POST['id']));

				if ($result) {
					echo '200';
					exit;
				} else {
					echo '400';
					exit;
				}
			} else {
				echo '409';
				exit;
			}

		} catch (\Exception $exception) {
			$this->misc->log('Manager ' . __METHOD__, $exception);
		}
	}

	/**
	 * ajax process to add a mail details
	 */
	public function add_mail() {
		try {
			if ($_SERVER["REQUEST_METHOD"] == "POST" && $_SESSION['csrf'] == $_POST['csrf']) {

				$sql = $this->dbConn->prepare("insert into mails(subject, body, module) values(?, ?, ?)");
				$result = $sql->execute(array($_POST['subject'], $_POST['body'], $_POST['module']));

				if ($result) {
					echo '200';
					exit;
				} else {
					echo '400';
					exit;
				}
			} else {
				echo '409';
				exit;
			}

		} catch (\Exception $exception) {
			$this->misc->log('Manager ' . __METHOD__, $exception);
		}
	}

	/**
	 * this method will fetch all mails to edit, add and update
	 */
	public function bulk_mails() {
		try {
			$result = [];

			// it will fetch all sales rep under the manager including herself
			$sql = $this->dbConn->prepare("SELECT u.id as uid, u.User_Status, u.Name_First, u.Name_Last,
                u.Email, u.last_logged_in, u.Password,
                r.id as rid, r.company_name, u.created_at,
                (select count(id)
                from users where registration_id = u.registration_id) as total_sales_rep
                FROM users u
                inner join registrations r on r.id = u.registration_id
                where u.User_Roles = ?
                order by r.id desc, u.id desc");
			$sql->execute(array(2));
			$count = $sql->rowCount();

			if ($count > 0) {
				while ($row = $sql->fetch()) {
					$result[] = $row;
				}
			}

			$sql_mails = $this->dbConn->prepare("SELECT id, subject
                from mails
                order by id asc");
			$sql_mails->execute(array());
			$count = $sql_mails->rowCount();
			$mails = [];

			if ($count > 0) {
				while ($row = $sql_mails->fetch()) {
					$mails[] = $row;
				}
			}

			$response = $this->twig->render('admin/super/bulk_mails.html.twig', ['uri' => $_SERVER['REQUEST_URI'], 'key' => $_SESSION['csrf'], 'user_token' => $_SESSION['user_token'], 'result' => $result, 'mails' => $mails, 'session_user' => isset($_SESSION['user']) ? $_SESSION['user'] : [], 'admin_to_go' => $this->admin_to_go, 'link' => $this->link, 'stages' => $this->stages]);

			return $response;
		} catch (\Exception $exception) {
			$this->misc->log('Manager ' . __METHOD__, $exception);
		}
	}

	/**
	 * ajax process to send bulk mails
	 */
	public function send_bulk_mails() {
		try {
			if ($_SERVER["REQUEST_METHOD"] == "POST" && $_SESSION['csrf'] == $_POST['csrf']) {

				if (isset($_POST['mail']) && $_POST['mail'] != '' && isset($_POST['send']) && !empty($_POST['send'])) {
					$sql_template = $this->dbConn->prepare("select * from mails where id = ? LIMIT 1");
					$sql_template->execute(array($_POST['mail']));
					$count_template = $sql_template->rowCount();

					// update the email from template
					if ($count_template > 0) {
						while ($row_template = $sql_template->fetch()) {
							$body = $row_template['body'];
							$body = str_ireplace('{{COMPANY_EMAIL}}', COMPANY_EMAIL, $body);
							$body = str_ireplace('{{SalesCRM}}', APP_NAME, $body);
							$body = str_ireplace('{{http://www.salescrm.com}}', WEBSITE_LINK, $body);
							$body = str_ireplace('{{www.salescrm.com}}', WEBSITE, $body);
							$subject = $row_template['subject'] . ' to ' . APP_NAME;

							break;

						}
						 
						// details to send mail
						// also update the name as per this user name
						foreach ($_POST['send'] as $details) {
							$temp = explode('#', $details);
							$email = $temp[0];
							$name = $temp[1];
							$company_name = $temp[2];

							$body = str_ireplace('{{FIRST_NAME}}', $name, $body);
							$body = str_ireplace('{{ENCODED_EMAIL}}', base64_encode($email), $body);
							$body = str_ireplace('{{COMPANY}}', $company_name, $body);
							$body = str_ireplace('{{USERNAME}}', '<b>' . $email . '</b>', $body);
							$body = str_ireplace('{{PASSWORD}}', 'Your password which you have provided at registration time', $body);

							$to = [
								'email' => $email,
								'name' => $name,
							];

							$reply = [
								'email' => MAIL_FROM_EMAIL,
								'name' => MAIL_FROM_NAME,
							];

							$reply_to = [
								'email' => MAIL_REPLY_TO_EMAIL,
								'name' => MAIL_REPLY_TO_NAME,
							];
							
							if ($this->misc->send_mail($to, $reply, $reply_to, $subject, $body)) {
								echo '200';
								exit;
							} else {
								echo '400';
								exit;
							}
						}
					}
				} else {
					echo '409';
					exit;
				}

			} else {
				echo '409';
				exit;
			}

		} catch (\Exception $exception) {
			$this->misc->log('Manager ' . __METHOD__, $exception);
		}
	}

	/**
	 * ajax data for dashboard chart
	 */
	public function get_chart_data_for_dashboard() {
		try {
			if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['analytics_from']) && $_POST['analytics_from'] != '' && isset($_POST['analytics_to']) && $_POST['analytics_to'] != '') {

				$_SESSION['analytics_from'] = $_POST['analytics_from'];
				$_SESSION['analytics_to'] = $_POST['analytics_to'];
			}

			$sql = $this->dbConn->prepare('select d.budget, date(d.created_at) as created_at, d.project_type, d.rating from deals d
                inner join users u on u.id = d.sales_rep
                where u.registration_id = ? and d.created_at >= ? and d.created_at <= DATE_ADD(?, INTERVAL 1 DAY) order by d.created_at');
			$sql->execute(array($_SESSION['user']['registration_id'], $_SESSION['analytics_from'], $_SESSION['analytics_to']));

			$count = $sql->rowCount();

			if ($count > 0) {
				$temp_result = [];
				while ($row = $sql->fetch()) {
					$temp_result[] = $row;
				}
				$result['status'] = 200;
				$result['data'] = $temp_result;
			}

			if (!empty($result)) {
				$result['currency'] = $_SESSION['user']['currency_symbol'];
				return json_encode($result);
				exit;
			} else {
				$result['status'] = 400;
				$result['data'] = [];
				return json_encode($result);
				exit;
			}

		} catch (\Exception $exception) {
			$this->misc->log('Superadmin ' . __METHOD__, $exception);
		}
	}

	/**
	 * this will calculate pipeline etc for sidebar
	 *
	 */
	private function sidebar_essentials() {
		try {
			// owner
			$sql = $this->dbConn->prepare('select * from user_pipeline where user_id = ? order by position');
			$sql->execute(array($_SESSION['user']['id']));
			$count = $sql->rowCount();
			$this->stages = [];

			if ($count > 0) {
				while ($row = $sql->fetch()) {
					$this->stages[] = $row;
				}
			}

			return true;
		} catch (\Exception $exception) {
			$this->misc->log('Manager ' . __METHOD__, $exception);
			return false;
		}
	}

	/**
	 * ajax add and update pipeline settings
	 */
	public function supports() {
		try {
			if ($_SERVER["REQUEST_METHOD"] == "POST" &&
				((isset($_POST['csrf']) && $_SESSION['csrf'] == $_POST['csrf']) ||
					(isset($_POST['data'][0]['csrf']) && $_SESSION['csrf'] == $_POST['data'][0]['csrf']))) {

				if (array_key_exists('data', $_POST)) {
					$change = $_POST['data'][0]['change'];
				} elseif (array_key_exists('change', $_POST)) {
					$change = $_POST['change'];
				}

				if ($change == 'add') {
					$new_stage = $_POST['new_stage'];

					// checking the stages with related deals for any sales rep
					$sql_pipeline = $this->dbConn->prepare("select max(position) as max_position from user_pipeline where registration_id = ? order by position");
					$sql_pipeline->execute(array($this->loggedin_user_registration_id));
					$count = $sql_pipeline->rowCount();

					$max_position = 0;

					if ($count > 0) {
						while ($row_pipeline = $sql_pipeline->fetch()) {
							$max_position = $row_pipeline['max_position'];
							break;
						}
						// inserting new stage
						$sql = $this->dbConn->prepare("insert into user_pipeline (user_id, deal_stage, position, registration_id) values (?, ?, ?, ?)");
						$result = $sql->execute(array($this->loggedin_user_id, $new_stage, intval($max_position) + 1, $this->loggedin_user_registration_id));
						$new_stage_id = $this->dbConn->lastInsertId();

						if ($result) {
							// $sql_pipeline = $this->dbConn->prepare("select * from user_pipeline where user_id = ? order by position");
							// $sql_pipeline->execute(array($loggedin_user_id));
							// $count = $sql_pipeline->rowCount();

							// $stages = '<ul id="sortable_pipeline_stages" class="list-group" data-toggle="tooltip" title="You can drag a stage to new position or order">';
							// if($count > 0) {
							//     while($row = $sql_pipeline->fetch()) {
							//         $stages .= '<li class="list-group-item" id="'.$row['id'].'">'.ucwords($row['deal_stage']).'</li>';
							//     }
							// }
							// $stages .= '</ul>';

							$response = [
								'status' => '200',
								'data' => '<li class="list-group-item pipeline-stages ui-sortable-handle" id="' . $new_stage_id . '">' . ucwords($new_stage) . '<span class="stage-close">x</span></li>',
							];
						} else {
							$response = [
								'status' => '409',
								'data' => '',
							];
						}
					}

					echo json_encode($response);
					exit;

				} elseif ($change == 'update') {
					// after delete or changed the positions
					$data = $_POST['data'][0];
					$stage_ids = explode('#', $data['stage_ids']);
					$stage_values = explode('#', $data['stage_values']);

					// get existing stage details
					$positions = [];
					$sql_existing = $this->dbConn->prepare('select * from user_pipeline where registration_id = ? order by position');
					$sql_existing->execute(array($this->loggedin_user_registration_id));
					$count_existing = $sql_existing->rowCount();
					if ($count_existing > 0) {
						while ($row_existing = $sql_existing->fetch()) {
							$positions[] = $row_existing['id'];
						}
					}

					// find out only the positions changed stages
					$differences = [];
					$differences_ids = [];
					foreach ($stage_ids as $key => $value) {
						if ($positions[$key] != $value) {
							$differences[$key] = $value;
							$differences_ids[] = $value;
						}
					}

					// print_r($positions); print_r($stage_ids); print_r($differences); exit;
					// PDO do not take direct array or implode into IN
					$in = str_repeat('?,', count($differences_ids) - 1) . '?';

					// get the deals related to any stage
					$sql_deals = $this->dbConn->prepare("select count(id) as cnt, stage
                        from deals group by stage having stage in( " . $in . " ) order by stage");
					$params = $differences_ids;
					$sql_deals->execute($params);
					$count = $sql_deals->rowCount();
					$result = [];

					// if these stage not related to any deal then it can be updated
					// may be later we can do one by another stage check for deal
					if ($count == 0) {
						foreach ($differences as $key => $value) {
							// update the stage
							$sql_stage = $this->dbConn->prepare("update user_pipeline set position = ? where id = ?");
							$result = $sql_stage->execute(array(intval($key + 1), $value));
							if ($result) {
								$response = [
									'status' => '200',
									'data' => '',
								];
							} else {
								$response = [
									'status' => '400',
									'data' => '',
								];
							}
						}

					} else {
						$response = [
							'status' => '409',
							'data' => '',
						];
					}

					echo json_encode($response);
					exit;

				} elseif ($change == 'delete') {
					// to delete a stage if it is not related to any deal

					$stage = $_POST['data'][0]['stage_id'];

					// get the deals related to any stage id from any of the sales rep of this manager
					$sql_deals = $this->dbConn->prepare("select count(id) as cnt, stage
                        from deals group by stage having stage = ?");
					$sql_deals->execute(array($stage));
					$count = $sql_deals->rowCount();
					$result = [];

					if ($count > 0) {
						while ($row = $sql_deals->fetch()) {
							if ($row['cnt'] > 0) {
								$result[$row['stage']] = $row['cnt'];
								$response = [
									'status' => '409',
									'data' => '',
								];
								break;
							}
						}
					} else {
						// delete this stage
						$sql_stage = $this->dbConn->prepare("delete from user_pipeline where id = ?");
						$result = $sql_stage->execute(array($stage));
						if ($result) {
							$response = [
								'status' => '200',
								'data' => '',
							];
						}
					}

					echo json_encode($response);
					exit;
				}
			} else {
				$response = [
					'status' => '400',
					'data' => '',
				];

				echo json_encode($response);
				exit;
			}
		} catch (\Exception $exception) {
			$this->misc->log('Manager ' . __METHOD__, $exception);
			return false;
		}
	}

	/**
	 * clearn all redis cache
	 */
	public function cache_clean() {
		try {
			$redis = new \Redis();
			$redis->connect('127.0.0.1', 6379);
			$redis->flushAll();

			header("location: /manager/super");
			exit;

		} catch (\Exception $exception) {
			$this->misc->log('Manager ' . __METHOD__, $exception);
		}
	}

	//Function to clean the text data received from post of fckeditor
	private function dataready($data) {
		$data = trim($data);
		$data = stripslashes($data);
		$data = htmlspecialchars($data);
		return $data;
	}
}

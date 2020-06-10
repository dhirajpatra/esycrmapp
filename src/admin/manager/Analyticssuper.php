<?php
declare (strict_types = 1);

namespace App\admin\manager;

use App\admin\inc\Misc;

/**
 * this class will process analytics for super admin
 */
class Analyticssuper {
	private $loggedin_user_id;
	private $company_id;
	private $role;
	private $from;
	private $to;
	private $dbConn;
	private $misc;

	/**
	 * this will set some of the session variable and date range
	 */
	public function __construct($dbConn, $from, $to) {
		try {
			// misc
			$this->misc = new Misc();

			$this->dbConn = $dbConn;
			$this->loggedin_user_id = $_SESSION['user']['id'];
			$this->company_id = $_SESSION['user']['registration_id'];
			$this->role = $_SESSION['user']['role_id'];
			$this->from = $from;
			$this->to = $to;
		} catch (\Exception $exception) {
			$this->misc->log('Superadmin Analytics ' . __METHOD__, $exception);
		}
	}

	/**
	 * this will accommodate all results and return
	 */
	public function getDashboardDetails() {
		try {

			$final_result = [
				'contacts' => $this->contacts(),
				'active_deals' => $this->getAddedDeals(),
				'won_deals' => $this->getWonDeals(),
				'lost_deals' => $this->getLostDeals(),
				'avg_own_deals' => number_format($this->getAvgWonDeals(), 2),
				'avg_sales_cycle' => $this->getAvgSalesCycleLength(),
				'conversion_rate' => $this->getConversionRate(),
				'tasks_completed' => $this->taskCompleted(),
				'tasks_to_do' => $this->taskToDo(),
				'contacts_bulk_uploaded' => $this->contactsBulkUploaded(),
				'google_calendar_push' => $this->googleCalendarPush(),
			];

			return $final_result;
		} catch (\Exception $exception) {
			$this->misc->log('Superadmin Analytics ' . __METHOD__, $exception);
		}
	}

	/**
	 * fetch contact added within this date range
	 */
	private function contacts() {
		try {
			if ($this->role == SUPER_ADMIN_ROLE) {
				$query = 'SELECT count(id) as cnt
                FROM contact c
                where c.created_at >= ?
                and c.created_at <= DATE_ADD(?, INTERVAL 1 DAY) limit 1';
				$sql = $this->dbConn->prepare($query);
				$sql->execute(array($this->from, $this->to));

				$count = $sql->rowCount();
				$result = 0;
				if ($count > 0) {
					$total = 0;
					while ($row = $sql->fetch()) {
						$result = $row['cnt'];
					}

				}

				return $result;
			}
		} catch (\Exception $exception) {
			$this->misc->log('Superadmin Analytics ' . __METHOD__, $exception);
		}
	}

	/**
	 * this method will fetch deals related data
	 * created within the date range
	 */
	private function getAddedDeals() {
		try {
			$result = [];
			// as sales manager himself a sales rep
			if ($this->role == SUPER_ADMIN_ROLE) {
				$query = 'SELECT sum(d.budget) as budget
                FROM deals d
                where d.created_at >= ? and d.created_at <= DATE_ADD(?, INTERVAL 1 DAY) limit 1';
				$sql = $this->dbConn->prepare($query);
				$sql->execute(array($this->from, $this->to));
				$count = $sql->rowCount();

				if ($count > 0) {
					$total = 0.0;
					while ($row = $sql->fetch()) {
						$total += $row['budget'];
						break;
					}
					$result['total_value'] = floatval($total);
					$result['total'] = $count;
				}
			}

			return $result;
		} catch (\Exception $exception) {
			$this->misc->log('Superadmin Analytics ' . __METHOD__, $exception);
		}
	}

	/**
	 * get won deals within this date range
	 */
	private function getWonDeals() {
		try {
			$result = [];
			$total = 0.0;
			$avg = 0.0;
			if ($this->role == SUPER_ADMIN_ROLE) {
				$query = 'select d.* from deals d
                where d.active = 1 and d.created_at >= ? and d.created_at <= DATE_ADD(?, INTERVAL 1 DAY)';
				$sql = $this->dbConn->prepare($query);
				$sql->execute(array($this->from, $this->to));
				$count = $sql->rowCount();
				if ($count > 0) {
					while ($row = $sql->fetch()) {
						$total += $row['budget'];
					}
				}
			}

			$avg = $total > 0 ? floatval($total / $count) : 0;
			$result['total_value'] = $total;
			$result['avg'] = $avg;
			$result['total'] = $count;

			return $result;
		} catch (\Exception $exception) {
			$this->misc->log('Superadmin Analytics ' . __METHOD__, $exception);
		}
	}

	/**
	 * deals lost in this time frame
	 */
	private function getLostDeals() {
		try {
			$result['total_value'] = 0.0;
			$result['avg'] = 0.0;
			$result['total'] = 0;

			if ($this->role == SUPER_ADMIN_ROLE) {
				$query = 'select d.* from deals d
                where d.active = ? and d.created_at >= ? and d.created_at <= DATE_ADD(?, INTERVAL 1 DAY)';

				$sql = $this->dbConn->prepare($query);
				$sql->execute(array(2, $this->from, $this->to));
				$count = $sql->rowCount();
				if ($count > 0) {
					$total = 0.0;
					$avg = 0.0;
					while ($row = $sql->fetch()) {
						$total += $row['budget'];
					}

					$avg = floatval($total / $count);
					$result['total_value'] = $total;
					$result['avg'] = $avg;
					$result['total'] = $count;
				}
			}

			return $result;
		} catch (\Exception $exception) {
			$this->misc->log('Superadmin Analytics ' . __METHOD__, $exception);
		}
	}

	/**
	 * fetch avg of all own deals in that date range
	 */
	private function getAvgWonDeals() {
		try {
			$result = 0;
			if ($this->role == SUPER_ADMIN_ROLE) {
				$query = 'select avg(d.budget) as avg from deals d
                where d.active = ? and d.created_at >= ? and d.created_at <= DATE_ADD(?, INTERVAL 1 DAY) limit 1';
				$sql = $this->dbConn->prepare($query);
				$sql->execute(array(1, $this->from, $this->to));
				$count = $sql->rowCount();
				if ($count > 0) {
					while ($row = $sql->fetch()) {
						$result = $row['avg'];
						break;
					}
				}
			}

			return floatval($result);
		} catch (\Exception $exception) {
			$this->misc->log('Superadmin Analytics ' . __METHOD__, $exception);
		}
	}

	/**
	 * fetch avg sales or deals cycle to get won or lost from start to finish
	 */
	private function getAvgSalesCycleLength() {
		try {
			$result = 0.0;
			if ($this->role == SUPER_ADMIN_ROLE) {
				$query = 'select datediff(d.proposal_due_date, date(d.created_at)) as cycle from deals d
                where d.created_at >= ? and d.created_at <= DATE_ADD(?, INTERVAL 1 DAY)';

				$sql = $this->dbConn->prepare($query);
				$sql->execute(array($this->from, $this->to));
				$count = $sql->rowCount();
				$total = 0;
				$avg = 0;
				if ($count > 0) {
					while ($row = $sql->fetch()) {
						$total += $row['cycle'];
					}

					$result = $total / $count;
				}
			}

			return $result;
		} catch (\Exception $exception) {
			$this->misc->log('Superadmin Analytics ' . __METHOD__, $exception);
		}
	}

	/**
	 * this will fetch conversion rate to own a deal for all deals
	 */
	private function getConversionRate() {
		try {
			if ($this->role == SUPER_ADMIN_ROLE) {
				$query = 'select d.active, count(d.id) as total from deals d
                where d.created_at >= ? and d.created_at <= DATE_ADD(?, INTERVAL 1 DAY) group by d.active';
				$sql = $this->dbConn->prepare($query);
				$sql->execute(array($this->from, $this->to));
				$count = $sql->rowCount();
				$result = [];
				if ($count > 0) {
					while ($row = $sql->fetch()) {
						$result[] = $row;
					}
				}

				$total = 0;
				$won = 0;
				$lost = 0;
				foreach ($result as $value) {
					$total += $value['total'];

					if ($value['active'] == 1) {
						$won++;
					} elseif ($value['active'] == 2) {
						$lost++;
					}
				}
				// echo $total . '    ' . $won . '   ' . $lost;
				// calculate the ratio
				if ($total > 0) {
					$conversion_rate['won'] = round(($won / $total) * 100);
					$conversion_rate['lost'] = round(($lost / $total) * 100);
				} else {
					$conversion_rate['won'] = 0;
					$conversion_rate['lost'] = 100;
				}

				return $conversion_rate;
			}
		} catch (\Exception $exception) {
			$this->misc->log('Superadmin Analytics ' . __METHOD__, $exception);
		}
	}

	/**
	 * fetch task details completed within this date range
	 */
	private function taskCompleted() {
		try {
			if ($this->role == SUPER_ADMIN_ROLE) {
				$query = 'SELECT count(n.id) as total, n.Todo_Desc_ID
                FROM notes n
                where n.Task_Status = ? and n.created_at >= ? and n.created_at <= DATE_ADD(?, INTERVAL 1 DAY)
                group by n.Todo_Desc_ID';
				$sql = $this->dbConn->prepare($query);
				$sql->execute(array(2, $this->from, $this->to));
				$count = $sql->rowCount();
				$result = [];
				if ($count > 0) {
					$total = 0;
					while ($row = $sql->fetch()) {
						$total += $row['total'];
						$result[] = $row;
					}
					$result['all_total'] = $total;
				}

				return $result;
			}
		} catch (\Exception $exception) {
			$this->misc->log('Superadmin Analytics ' . __METHOD__, $exception);
		}
	}

	/**
	 * fetch task details to do within this date range
	 */
	private function taskToDo() {
		try {
			if ($this->role == SUPER_ADMIN_ROLE) {
				$query = 'SELECT count(n.id) as total, n.Todo_Desc_ID
                FROM notes n
                where n.Task_Status = ? and n.created_at >= ? and n.created_at <= DATE_ADD(?, INTERVAL 1 DAY)
                group by n.Todo_Desc_ID';
				$sql = $this->dbConn->prepare($query);
				$sql->execute(array(1, $this->from, $this->to));
				$count = $sql->rowCount();
				$result = [];
				if ($count > 0) {
					$total = 0;
					while ($row = $sql->fetch()) {
						$total += $row['total'];
						$result[] = $row;
					}
					$result['all_total'] = $total;
				}

				return $result;
			}
		} catch (\Exception $exception) {
			$this->misc->log('Superadmin Analytics ' . __METHOD__, $exception);
		}
	}

	/**
	 * fetch how many contacts bulk uploaded via csv file
	 */
	private function contactsBulkUploaded() {
		try {
			if ($this->role == SUPER_ADMIN_ROLE) {
				$query = 'SELECT count(c.id) as total
                FROM contact c
                where c.created_at >= ? and c.created_at <= DATE_ADD(?, INTERVAL 1 DAY) LIMIT 1';
				$sql = $this->dbConn->prepare($query);
				$sql->execute(array($this->from, $this->to));
				$count = $sql->rowCount();

				$result = 0;
				if ($count > 0) {
					while ($row = $sql->fetch()) {
						$result = $row['total'];
						break;
					}
				}

				return $result;
			}
		} catch (\Exception $exception) {
			$this->misc->log('Superadmin Analytics ' . __METHOD__, $exception);
		}
	}

	/**
	 * fetch how many google calendar notification pushed
	 */
	private function googleCalendarPush() {
		try {
			if ($this->role == SUPER_ADMIN_ROLE) {
				$query = 'SELECT count(n.id) as total
                FROM notes n
                where n.google_calendar_update = ? and n.created_at >= ? and n.created_at <= DATE_ADD(?, INTERVAL 1 DAY) LIMIT 1';
				$sql = $this->dbConn->prepare($query);
				$sql->execute(array(1, $this->from, $this->to));
				$count = $sql->rowCount();
				$result = 0;
				if ($count > 0) {
					while ($row = $sql->fetch()) {
						$result = $row['total'];
						break;
					}
				}

				return $result;
			}
		} catch (\Exception $exception) {
			$this->misc->log('Superadmin Analytics ' . __METHOD__, $exception);
		}
	}
}

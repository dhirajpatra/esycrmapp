<?php
declare (strict_types = 1);

namespace App\admin\manager;

use App\admin\inc\Misc;

/**
 * this class will process analytics for a manager also for a rep
 */
class Analytics
{
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
    public function __construct($dbConn, $from, $to)
    {
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
            $this->misc->log('Sales Analytics ' . __METHOD__, $exception);
        }
    }

    /**
     * this will accommodate all results and return
     */
    public function getDashboardDetails()
    {
        try {

            $final_result = [
                'contacts' => $this->contacts(),
                'active_deals' => $this->getAddedDeals(),
                'won_deals' => $this->getWonDeals(),
                'lost_deals' => $this->getLostDeals(),
                'avg_own_deals' => number_format($this->getAvgWonDeals(), 2),
                'avg_sales_cycle' => $this->getAvgSalesCycleLength(),
                'conversion_rate' => $this->getConversionRate(),
                'top_sales_sources' => $this->getTopSalesSources(),
                'tasks_completed' => $this->taskCompleted(),
                'tasks_to_do' => $this->taskToDo(),
                'contacts_bulk_uploaded' => $this->contactsBulkUploaded(),
                'google_calendar_push' => $this->googleCalendarPush(),
            ];

            return $final_result;
        } catch (\Exception $exception) {
            $this->misc->log('Sales Analytics ' . __METHOD__, $exception);
        }
    }

    /**
     * fetch contact added within this date range
     */
    private function contacts()
    {
        try {
            if ($this->role == MANAGER_ROLE) {
                $query = 'SELECT count(c.id) as cnt
                FROM contact c
                inner join users u on u.id = c.Sales_Rep
                where u.registration_id = ? and c.created_at >= ?
                and c.created_at <= DATE_ADD(?, INTERVAL 1 DAY) limit 1';
                $sql = $this->dbConn->prepare($query);

                $sql->execute(array($this->company_id, $this->from, $this->to));
                $count = $sql->rowCount();
                $result = 0;
                if ($count > 0) {
                    while ($row = $sql->fetch()) {
                        $result = $row['cnt'];
                        break;
                    }

                }

                return $result;
            }
        } catch (\Exception $exception) {
            $this->misc->log('Sales Analytics ' . __METHOD__, $exception);
        }
    }

    /**
     * this method will fetch deals related data
     * created within the date range
     */
    private function getAddedDeals()
    {
        try {
            $result = [];
            // as sales manager himself a sales rep
            if ($this->role == MANAGER_ROLE) {
                $query = 'SELECT *
                FROM deals d
                inner join users u on u.id = d.sales_rep
                where d.created_at >= ? and d.created_at <= DATE_ADD(?, INTERVAL 1 DAY)
                and u.registration_id = ?';
                $sql = $this->dbConn->prepare($query);
                $sql->execute(array($this->from, $this->to, $this->company_id));
                $count = $sql->rowCount();

                if ($count > 0) {
                    $total = 0.0;
                    while ($row = $sql->fetch()) {
                        $total += $row['budget'];
                    }

                    $result['total_value'] = floatval($total);
                    $result['total'] = $count;
                }
            }

            return $result;
        } catch (\Exception $exception) {
            $this->misc->log('Sales Analytics ' . __METHOD__, $exception);
        }
    }

    /**
     * get won deals within this date range
     */
    private function getWonDeals()
    {
        try {
            $result = [];
            $total = 0.0;
            $avg = 0.0;
            $count = 0;
            if ($this->role == MANAGER_ROLE) {
                $query = 'select d.*, u.* from deals d
                inner join users u on u.id = d.sales_rep
                where u.registration_id = ? and d.active = 1 and d.created_at >= ? and d.created_at <= DATE_ADD(?, INTERVAL 1 DAY)';
                $sql = $this->dbConn->prepare($query);
                $sql->execute(array($this->company_id, $this->from, $this->to));
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
            $this->misc->log('Sales Analytics ' . __METHOD__, $exception);
        }
    }

    /**
     * deals lost in this time frame
     */
    private function getLostDeals()
    {
        try {
            $result['total_value'] = 0.0;
            $result['avg'] = 0.0;
            $result['total'] = 0;

            if ($this->role == MANAGER_ROLE) {
                $query = 'select d.*, u.* from deals d
                inner join users u on u.id = d.sales_rep
                where u.registration_id = ? and d.active = 2 and d.created_at >= ? and d.created_at <= DATE_ADD(?, INTERVAL 1 DAY)';

                $sql = $this->dbConn->prepare($query);
                $sql->execute(array($this->company_id, $this->from, $this->to));
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
            $this->misc->log('Sales Analytics ' . __METHOD__, $exception);
        }
    }

    /**
     * fetch avg of all own deals in that date range
     */
    private function getAvgWonDeals()
    {
        try {
            $result = 0;
            if ($this->role == MANAGER_ROLE) {
                $query = 'select avg(d.budget) as avg from deals d
                inner join users u on u.id = d.sales_rep
                where u.registration_id = ? and d.active = ? and d.created_at >= ? and d.created_at <= DATE_ADD(?, INTERVAL 1 DAY) limit 1';
                $sql = $this->dbConn->prepare($query);
                $sql->execute(array($this->company_id, 1, $this->from, $this->to));
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
            $this->misc->log('Sales Analytics ' . __METHOD__, $exception);
        }
    }

    /**
     * fetch avg sales or deals cycle to get won or lost from start to finish
     */
    private function getAvgSalesCycleLength()
    {
        try {
            $result = 0.0;
            if ($this->role == MANAGER_ROLE) {
                $query = 'select datediff(d.proposal_due_date, date(d.created_at)) as cycle from deals d
                inner join users u on u.id = d.sales_rep
                where u.registration_id = ? and d.created_at >= ? and d.created_at <= DATE_ADD(?, INTERVAL 1 DAY)';

                $sql = $this->dbConn->prepare($query);
                $sql->execute(array($this->company_id, $this->from, $this->to));
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
            $this->misc->log('Sales Analytics ' . __METHOD__, $exception);
        }
    }

    /**
     * this will fetch conversion rate to own a deal for all deals
     */
    private function getConversionRate()
    {
        try {
            if ($this->role == MANAGER_ROLE) {
                $query = 'select d.active, count(d.id) as total from deals d
                inner join users u on u.id = d.sales_rep
                where u.registration_id = ? and d.created_at >= ? and d.created_at <= DATE_ADD(?, INTERVAL 1 DAY) group by d.active';
                $sql = $this->dbConn->prepare($query);
                $sql->execute(array($this->company_id, $this->from, $this->to));
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
            $this->misc->log('Sales Analytics ' . __METHOD__, $exception);
        }
    }

    /**
     * fetch from which source get more sales
     */
    private function getTopSalesSources()
    {
        try {
            if ($this->role == MANAGER_ROLE) {
                $query = 'select c.Contact_First, c.Contact_Last, c.Lead_Referral_Source from deals d
                inner join contact c on c.id = d.contact_id
                inner join users u on u.id = d.sales_rep
                where u.registration_id = ? and d.active <> ? and d.created_at >= ? and
                d.created_at <= DATE_ADD(?, INTERVAL 1 DAY) order by d.budget desc limit 5';
                $sql = $this->dbConn->prepare($query);
                $sql->execute(array($this->company_id, 3, $this->from, $this->to));
                $count = $sql->rowCount();
                $result = [];
                if ($count > 0) {
                    while ($row = $sql->fetch()) {
                        $result[] = $row;
                    }
                }

                return $result;
            }
        } catch (\Exception $exception) {
            $this->misc->log('Sales Analytics ' . __METHOD__, $exception);
        }
    }

    /**
     * fetch task details completed within this date range
     */
    private function taskCompleted()
    {
        try {
            if ($this->role == MANAGER_ROLE) {
                $query = 'SELECT count(n.id) as total, n.Todo_Desc_ID, t.description
                FROM notes n
                inner join todo_desc t on t.id = n.Todo_Desc_ID
                inner join users u on u.id = n.sales_rep
                where u.registration_id = ? and n.Task_Status = ? and n.created_at >= ? and n.created_at <= DATE_ADD(?, INTERVAL 1 DAY)
                group by n.Todo_Desc_ID';
                $sql = $this->dbConn->prepare($query);
                $sql->execute(array($this->company_id, 2, $this->from, $this->to));
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
            $this->misc->log('Sales Analytics ' . __METHOD__, $exception);
        }
    }

    /**
     * fetch task details to do within this date range
     */
    private function taskToDo()
    {
        try {
            if ($this->role == MANAGER_ROLE) {
                $query = 'SELECT count(n.id) as total, n.Todo_Desc_ID, t.description
                FROM notes n
                inner join todo_desc t on t.id = n.Todo_Desc_ID
                inner join users u on u.id = n.sales_rep
                where u.registration_id = ? and n.Task_Status = ? and n.created_at >= ? and n.created_at <= DATE_ADD(?, INTERVAL 1 DAY)
                group by n.Todo_Desc_ID';
                $sql = $this->dbConn->prepare($query);
                $sql->execute(array($this->company_id, 1, $this->from, $this->to));
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
            $this->misc->log('Sales Analytics ' . __METHOD__, $exception);
        }
    }

    /**
     * fetch how many contacts bulk uploaded via csv file
     */
    private function contactsBulkUploaded()
    {
        try {
            if ($this->role == MANAGER_ROLE) {
                $query = 'SELECT count(c.id) as total
                FROM contact c
                inner join users u on u.id = c.sales_rep
                where c.source = ? and u.registration_id = ? and c.created_at >= ? and c.created_at <= DATE_ADD(?, INTERVAL 1 DAY) LIMIT 1';
                $sql = $this->dbConn->prepare($query);
                $sql->execute(array(1, $this->company_id, $this->from, $this->to));
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
            $this->misc->log('Sales Analytics ' . __METHOD__, $exception);
        }
    }

    /**
     * fetch how many google calendar notification pushed
     */
    private function googleCalendarPush()
    {
        try {
            if ($this->role == MANAGER_ROLE) {
                $query = 'SELECT count(n.id) as total
                FROM notes n
                inner join users u on u.id = n.sales_rep
                where u.registration_id = ? and n.google_calendar_update = ? and n.created_at >= ? and n.created_at <= DATE_ADD(?, INTERVAL 1 DAY) LIMIT 1';
                $sql = $this->dbConn->prepare($query);
                $sql->execute(array($this->company_id, 1, $this->from, $this->to));
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
            $this->misc->log('Sales Analytics ' . __METHOD__, $exception);
        }
    }
}
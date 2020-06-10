<?php

declare (strict_types = 1);

namespace App\admin;

use App\admin\inc\Log;
use App\admin\inc\Misc;
use App\admin\inc\PhpMailerCrm;
use App\ConnectDb;

class Search
{
    private $twig;
    private $dbConn;
    private $misc;
    private $key;
    private $phpMailerObj;
    private $log;
    private $loggedin_user_id;
    private $company_id;
    private $loggedin_user_registration_id;
    private $currency;

    public function __construct(
        $twig
    ) {
        $this->twig = $twig;
        $this->dbConn = ConnectDb::getConnection();
        $this->misc = new Misc();
        $this->phpMailerObj = new PhpMailerCrm();

        // checking session
        $this->misc->check_session();

        $this->loggedin_user_id = $_SESSION['user']['id'];
        $this->loggedin_user_registration_id = $_SESSION['user']['registration_id'];
        $this->company_id = $_SESSION['user']['registration_id'];
        $this->currency = $_SESSION['user']['currency_code'];

        // normal login all the time
        if (!isset($_SESSION['csrf'])) {
            $this->key = sha1(microtime());
            $_SESSION['csrf'] = $this->key;
        }
    }

    /**
     * this ajax method will fetch the searches for all search results
     * 
     */
    public function __invoke()
    {
        try {
            if ($_SERVER["REQUEST_METHOD"] == "GET") {
                if (isset($_SESSION['query_params']) && $_SESSION['query_params']['query'] != '') {
                    $query = $_SESSION['query_params']['query'];

                    switch ($_SESSION['user']['role_id']) {
                        // for sales rep 
                        case 1:
                        $result = $this->sales_search($query);                            
                        break;
                        // for manager
                        case 2:
                        $result = $this->manager_search($query);
                        break;
                        // for super admin
                        case 3:
                        $result = $this->super_search($query);
                        break;
                    }

                    if (!empty($result)) {
                        echo json_encode($result);                        
                        exit;
                    } else {
                        echo '';
                        exit;
                    }
                } else {
                    echo '';
                    exit;
                }
            }
        } catch (\Exception $exception) {
            $this->misc->log('Login' . __METHOD__, $exception);
        }
    }

    /**
     * this is for super admin search panel
     *
     * @return void
     */
    private function super_search($query)
    {
        try { 
            $result = [];
            
            // for companies
            $sql = $this->dbConn->prepare("SELECT *
                FROM registrations r
                where r.company_name LIKE :term
                order by r.id desc LIMIT " . SEARCH_LIMIT_PER_CAT);
            $sql->execute(array(":term" => "%" . $query . "%"));
            $count = $sql->rowCount();            

            if ($count > 0) {
                while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
                    $row['role'] = SUPER_ADMIN_ROLE;
                    $result['Companies'][] = $row;
                }
            } else {
                $result['Companies'][] = [];
            }
            
            // for contacts
            $sql = $this->dbConn->prepare("SELECT *
                FROM contact c
                where c.Contact_First LIKE :term or c.Contact_Last LIKE :term 
                or c.Lead_Referral_Source LIKE :term or c.Company LIKE :term
                order by c.id desc LIMIT " . SEARCH_LIMIT_PER_CAT);
            $sql->execute(array(":term" => "%" . $query . "%"));
            $count = $sql->rowCount();

            if ($count > 0) {
                while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
                    $row['role'] = SUPER_ADMIN_ROLE;
                    $result['Contacts'][] = $row;
                }
            } else {
                $result['Contacts'][] = [];
            }

            // for deals
            $sql = $this->dbConn->prepare("SELECT *
                FROM deals d
                where d.project_type LIKE :term or d.project_description LIKE :term
                or d.deliverables LIKE :term
                order by d.id desc LIMIT " . SEARCH_LIMIT_PER_CAT);
            $sql->execute(array(":term" => "%" . $query . "%"));
            $count = $sql->rowCount();

            if ($count > 0) {
                while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
                    $row['role'] = SUPER_ADMIN_ROLE;
                    $result['Deals'][] = $row;
                }
            } else {
                $result['Deals'][] = [];
            }

            // for tasks
            $sql = $this->dbConn->prepare("SELECT *
                FROM notes n
                where n.Notes LIKE :term
                order by n.id desc LIMIT " . SEARCH_LIMIT_PER_CAT);
            $sql->execute(array(":term" => "%" . $query . "%"));
            $count = $sql->rowCount();

            if ($count > 0) {
                while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
                    $row['role'] = SUPER_ADMIN_ROLE;
                    $result['Tasks'][] = $row;
                }
            } else {
                $result['Tasks'][] = [];
            }

            return $result;
        } catch (\Exception $exception) {
            $this->misc->log('Login' . __METHOD__, $exception);
        }
    }

    /**
     * this is for manager search panel
     *
     * @return void
     */
    private function manager_search($query)
    {
        try {
            $result = [];

            // for contacts
            $sql = $this->dbConn->prepare("SELECT *
                FROM contact c
                inner join users u on u.id = c.Sales_Rep
                where (c.Contact_First LIKE :term or c.Contact_Last LIKE :term 
                or c.Lead_Referral_Source LIKE :term or c.Company LIKE :term)
                and u.registration_id = $this->company_id
                order by c.id desc LIMIT " . SEARCH_LIMIT_PER_CAT);
            $sql->execute(array(":term" => "%" . $query . "%"));
            $count = $sql->rowCount();

            if ($count > 0) {
                while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
                    $row['role'] = MANAGER_ROLE;
                    $result['Contacts'][] = $row;
                }
            } else {
                $result['Contacts'][] = [];
            }

            // for deals
            $sql = $this->dbConn->prepare("SELECT *
                FROM deals d
                inner join users u on u.id = d.sales_rep
                where (d.project_type LIKE :term or d.project_description LIKE :term
                or d.deliverables LIKE :term)
                and u.registration_id = $this->company_id
                order by d.id desc LIMIT " . SEARCH_LIMIT_PER_CAT);
            $sql->execute(array(":term" => "%" . $query . "%"));
            $count = $sql->rowCount();

            if ($count > 0) {
                while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
                    $row['role'] = MANAGER_ROLE;
                    $result['Deals'][] = $row;
                }
            } else {
                $result['Deals'][] = [];
            }

            // for tasks
            $sql = $this->dbConn->prepare("SELECT *
                FROM notes n
                inner join users u on u.id = n.Sales_Rep
                where (n.Notes LIKE :term)
                and u.registration_id = $this->company_id
                order by n.id desc LIMIT " . SEARCH_LIMIT_PER_CAT);
            $sql->execute(array(":term" => "%" . $query . "%"));
            $count = $sql->rowCount();

            if ($count > 0) {
                while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
                    $row['role'] = MANAGER_ROLE;
                    $result['Tasks'][] = $row;
                }
            } else {
                $result['Tasks'][] = [];
            }

            return $result;
        } catch (\Exception $exception) {
            $this->misc->log('Login' . __METHOD__, $exception);
        }
    }

    /**
     * this is for sales search panel
     *
     * @return void
     */
    private function sales_search($query)
    {
        try {
            $result = [];

            // for contacts
            $sql = $this->dbConn->prepare("SELECT *
                FROM contact c
                where (c.Contact_First LIKE :term or c.Contact_Last LIKE :term 
                or c.Lead_Referral_Source LIKE :term or c.Company LIKE :term)
                and c.Sales_Rep = $this->loggedin_user_id
                order by c.id desc LIMIT " . SEARCH_LIMIT_PER_CAT);
            $sql->execute(array(":term" => "%" . $query . "%"));
            $count = $sql->rowCount();

            if ($count > 0) {
                while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
                    $row['role'] = SALES_REP_ROLE;
                    $result['Contacts'][] = $row;
                }
            } else {
                $result['Contacts'][] = [];
            }

            // for deals
            $sql = $this->dbConn->prepare("SELECT *
                FROM deals d
                where (d.project_type LIKE :term or d.project_description LIKE :term
                or d.deliverables LIKE :term)
                and d.sales_rep = $this->loggedin_user_id
                order by d.id desc LIMIT " . SEARCH_LIMIT_PER_CAT);
            $sql->execute(array(":term" => "%" . $query . "%"));
            $count = $sql->rowCount();

            if ($count > 0) {
                while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
                    $row['role'] = SALES_REP_ROLE;
                    $result['Deals'][] = $row;
                }
            } else {
                $result['Deals'][] = [];
            }

            // for tasks
            $sql = $this->dbConn->prepare("SELECT *
                FROM notes n
                where (n.Notes LIKE :term)
                and n.Sales_Rep = $this->loggedin_user_id
                order by n.id desc LIMIT " . SEARCH_LIMIT_PER_CAT);
            $sql->execute(array(":term" => "%" . $query . "%"));
            $count = $sql->rowCount();

            if ($count > 0) {
                while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
                    $row['role'] = SALES_REP_ROLE;
                    $result['Tasks'][] = $row;
                }
            } else {
                $result['Tasks'][] = [];
            }

            return $result;
        } catch (\Exception $exception) {
            $this->misc->log('Login' . __METHOD__, $exception);
        }
    }
}

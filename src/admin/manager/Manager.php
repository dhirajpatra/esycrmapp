<?php
declare (strict_types = 1);

namespace App\admin\manager;

use App\admin\inc\Log;
use App\admin\inc\Misc;
use App\ConnectDb;

class Manager
{
    private $twig;
    private $dbConn;
    private $misc;
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
        $this->log = new Log();

        // checking session
        $this->misc->check_session();

        $path = explode('/', $_SERVER['REQUEST_URI']);
        if (in_array('manager', $path)) {
            $this->admin_to_go = 'Sales Admin';
            $this->link = '/sales';
        } elseif ($_SESSION['user']['role_id'] == MANAGER_ROLE) {
            // manager
            $this->admin_to_go = 'Manager Panel';
            $this->link = '/manager';
        } else {
            $this->admin_to_go = '';
            $this->link = '';
        }

        $this->loggedin_user_id = $_SESSION['user']['id'];
        $this->loggedin_user_registration_id = $_SESSION['user']['registration_id'];
        $this->company_id = $_SESSION['user']['registration_id'];
        $this->currency = $_SESSION['user']['currency_code'];

        // get the totals nos for header
        $this->header_totals = $this->calculateTotals($_SESSION['user']['id'], $_SESSION['user']['registration_id'], $_SESSION['user']['role_id'], $this->dbConn);

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
    public function __invoke(): string
    {
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
            $analytics = new Analytics($this->dbConn, $_SESSION['analytics_from'], $_SESSION['analytics_to']);
            $result = $analytics->getDashboardDetails();
            // p($result); exit;
            $no_of_contacts = isset($result['contacts']) ? $result['contacts'] : 0;
            $no_of_active_deals = isset($result['active_deals']['total_value']) && $result['active_deals']['total_value'] > 0 ? $result['active_deals']['total'] : 0; // as total also a index/row
            $total_value_of_active_deals = isset($result['active_deals']['total_value']) ? number_format($result['active_deals']['total_value'], 2) : 0;
            $no_of_won_deals = isset($result['won_deals']['total']) ? $result['won_deals']['total'] : 0;
            $won_deals_value = $no_of_won_deals > 0 ? number_format($result['won_deals']['total_value'], 2) : 0;
            $avg_won_deals_value = $no_of_won_deals > 0 ? number_format($result['won_deals']['avg'], 2) : 0;
            $no_of_lost_deals = $result['lost_deals']['total'];
            $lost_deal_value = $no_of_lost_deals > 0 ? number_format($result['lost_deals']['total_value']) : 0;
            $avg_own_deals = $result['avg_own_deals'];
            $avg_sales_cycle = round($result['avg_sales_cycle']);
            $conversion_rate = $result['conversion_rate'];
            $top_sales_sources = !empty($result['top_sales_sources']) ? $result['top_sales_sources'] : [null];
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

            $response = $this->twig->render('admin/manager/pipeline.html.twig', ['uri' => $_SERVER['REQUEST_URI'], 'session_user' => isset($_SESSION['user']) ? $_SESSION['user'] : [], 'admin_to_go' => $this->admin_to_go, 'link' => $this->link, 'nav_link' => $this->nav_link, 'key' => $_SESSION['csrf'], 'user_token' => $_SESSION['user_token'], 'currency' => $this->currency, 'no_of_contacts' => $no_of_contacts, 'no_of_active_deals' => $no_of_active_deals, 'no_of_won_deals' => $no_of_won_deals, 'no_of_lost_deals' => $no_of_lost_deals, 'todo_descs' => $this->todo_descs, 'deals' => $this->deals, 'countries' => $this->countries, 'stage' => $this->stage, 'stages' => $this->stages, 'duration_select' => $this->duration_select, 'owner' => $this->owner, 'from' => $_SESSION['analytics_from'], 'to' => $_SESSION['analytics_to'], 'google_auth_url' => $this->google_auth_url, 'header_totals' => $this->header_totals, 'avg_own_deals' => $avg_own_deals, 'avg_sales_cycle' => $avg_sales_cycle, 'total_value_of_active_deals' => $total_value_of_active_deals, 'won_deals_value' => $won_deals_value, 'avg_won_deals_value' => $avg_won_deals_value, 'conversion_rate' => $conversion_rate, 'top_sales_sources' => $top_sales_sources, 'lost_deal_value' => $lost_deal_value, 'no_of_tasks_to_do' => $all_task_to_do_total, 'task_completed' => $task_completed, 'no_of_tasks' => $all_task_completed_total, 'task_completed_email' => $task_completed_email, 'task_completed_meeting' => $task_completed_meeting, 'task_completed_task' => $task_completed_task, 'task_completed_phone' => $task_completed_phone, 'contacts_bulk_uploaded' => $contacts_bulk_uploaded, 'google_calendar_push' => $google_calendar_push]);

            return $response;
        } catch (\Exception $exception) {
            $this->misc->log('Manager ' . __METHOD__, $exception);
        }
    }

    /**
     * ajax call for the analytics reports same as dashboard
     *
     */
    public function get_analytics_date_range(): string
    {
        try {

            if ($_SERVER["REQUEST_METHOD"] == "POST") {
                $this->loggedin_user_id = $_SESSION['user']['id'];

                $from = date('Y-m-d', strtotime($_POST['manager_analytics_from']));
                $to = date('Y-m-d', strtotime($_POST['manager_analytics_to']));
                $_SESSION['analytics_from'] = $from;
                $_SESSION['analytics_to'] = $to;

                // calling analytics data default 1 month date range
                $analytics = new Analytics($this->dbConn, $from, $to);
                $result = $analytics->getDashboardDetails();

                // p($result); exit;
                $no_of_contacts = isset($result['contacts']) ? $result['contacts'] : 0;
                $no_of_active_deals = isset($result['active_deals']['total_value']) && $result['active_deals']['total_value'] > 0 ? $result['active_deals']['total'] : 0; // as total also a index/row
                $total_value_of_active_deals = isset($result['active_deals']['total_value']) ? number_format($result['active_deals']['total_value'], 2) : 0;
                $no_of_won_deals = isset($result['won_deals']['total']) ? $result['won_deals']['total'] : 0;
                $won_deals_value = $no_of_won_deals > 0 ? number_format($result['won_deals']['total_value'], 2) : 0;
                $avg_won_deals_value = $no_of_won_deals > 0 ? number_format($result['won_deals']['avg'], 2) : 0;
                $no_of_lost_deals = $result['lost_deals']['total'];
                $lost_deal_value = $no_of_lost_deals > 0 ? number_format($result['lost_deals']['total_value']) : 0;
                $avg_own_deals = $result['avg_own_deals'];
                $avg_sales_cycle = round($result['avg_sales_cycle']);
                $conversion_rate = $result['conversion_rate'];
                $top_sales_sources = !empty($result['top_sales_sources']) ? $result['top_sales_sources'] : [null];
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

                $response = $this->twig->render('admin/manager/analytics.html.twig', ['uri' => $_SERVER['REQUEST_URI'], 'session_user' => isset($_SESSION['user']) ? $_SESSION['user'] : [], 'admin_to_go' => $this->admin_to_go, 'link' => $this->link, 'nav_link' => $this->nav_link, 'key' => $_SESSION['csrf'], 'user_token' => $_SESSION['user_token'], 'currency' => $this->currency, 'no_of_contacts' => $no_of_contacts, 'no_of_active_deals' => $no_of_active_deals, 'no_of_won_deals' => $no_of_won_deals, 'no_of_lost_deals' => $no_of_lost_deals, 'todo_descs' => $this->todo_descs, 'deals' => $this->deals, 'countries' => $this->countries, 'stage' => $this->stage, 'stages' => $this->stages, 'duration_select' => $this->duration_select, 'owner' => $this->owner, 'from' => $from, 'to' => $to, 'google_auth_url' => $this->google_auth_url, 'header_totals' => $this->header_totals, 'avg_own_deals' => $avg_own_deals, 'avg_sales_cycle' => $avg_sales_cycle, 'total_value_of_active_deals' => $total_value_of_active_deals, 'won_deals_value' => $won_deals_value, 'avg_won_deals_value' => $avg_won_deals_value, 'conversion_rate' => $conversion_rate, 'top_sales_sources' => $top_sales_sources, 'lost_deal_value' => $lost_deal_value, 'no_of_tasks_to_do' => $all_task_to_do_total, 'task_completed' => $task_completed, 'no_of_tasks' => $all_task_completed_total, 'task_completed_email' => $task_completed_email, 'task_completed_meeting' => $task_completed_meeting, 'task_completed_task' => $task_completed_task, 'task_completed_phone' => $task_completed_phone, 'contacts_bulk_uploaded' => $contacts_bulk_uploaded, 'google_calendar_push' => $google_calendar_push]);

                return $response;
            }
        } catch (\Exception $exception) {
            $this->misc->log('Manager ' . __METHOD__, $exception);
        }
    }

    /**
     * get all representatives for this manager
     */
    public function salesrep()
    {

        try {
            $result = [];

            // it will fetch all sales rep under the manager including herself
            $sql = $this->dbConn->prepare("SELECT u.id as uid, u.Name_First, u.Name_Last, u.Email
                FROM users u
                inner join registrations c on c.id = u.registration_id
                inner join roles r on r.id = u.User_Roles
                inner join user_status s on s.id = u.User_Status
                where (r.id = ? or r.id = ?) and s.id = ? and c.id = ?
                order by r.id desc, u.id desc");
            $sql->execute(array(MANAGER_ROLE, SALES_REP_ROLE, ACTIVE_USER, $this->company_id));
            $count = $sql->rowCount();

            if ($count > 0) {
                $i = 0;
                while ($row = $sql->fetch()) {
                    $result[$i]['user'] = $row;

                    $sql_contacts = $this->dbConn->prepare("SELECT c.id, c.Contact_First, c.Contact_Last, c.Title, c.Company, c.Industry, c.Lead_Referral_Source,
                        sum(d.budget) as total_budget, c.Sales_Rep, max(d.rating) as rating
                        FROM users u
                        left join contact c on c.Sales_Rep = u.id
                        inner join deals d on d.contact_id = c.id
                        group by c.id
                        having c.Sales_Rep = ? order by c.id");
                    $sql_contacts->execute(array($row['uid']));
                    $count_contacts = $sql_contacts->rowCount();

                    if ($count_contacts > 0) {
                        while ($row_contacts = $sql_contacts->fetch()) {
                            $result[$i]['contacts'][] = $row_contacts;
                        }
                    }

                    $i++;
                }
            }

            $response = $this->twig->render('admin/manager/salesrep.html.twig', ['uri' => $_SERVER['REQUEST_URI'], 'key' => $_SESSION['csrf'], 'user_token' => $_SESSION['user_token'], 'result' => $result, 'session_user' => isset($_SESSION['user']) ? $_SESSION['user'] : [], 'admin_to_go' => $this->admin_to_go, 'link' => $this->link, 'stages' => $this->stages]);

            return $response;

        } catch (\Exception $exception) {
            $this->misc->log('Manager ' . __METHOD__, $exception);
        }
    }

    /**
     * show add sales rep form
     */
    public function add_salesrep()
    {
        try {
            // check the limit of sales rep for this company exclude this manager
            $sql = $this->dbConn->prepare("SELECT count(id) as total
                FROM users
                where registration_id = ? and id <> ? limit 1");
            $sql->execute(array($this->loggedin_user_registration_id, $this->loggedin_user_id));
            $count = $sql->rowCount();
            if ($count > 0) {
                $row = $sql->fetch();
                if ($row['total'] < $_SESSION['user']['sales_rep_limit']) {
                    $response = $this->twig->render('admin/manager/add_salesrep.html.twig', ['uri' => $_SERVER['REQUEST_URI'], 'key' => $_SESSION['csrf'], 'session_user' => isset($_SESSION['user']) ? $_SESSION['user'] : [], 'admin_to_go' => $this->admin_to_go, 'link' => $this->link, 'user_token' => $_SESSION['user_token'], 'stages' => $this->stages]);

                } else {
                    // limit reached
                    $response = $this->twig->render('admin/manager/add_salesrep_limit_reach.html.twig', ['uri' => $_SERVER['REQUEST_URI'], 'key' => $_SESSION['csrf'], 'session_user' => isset($_SESSION['user']) ? $_SESSION['user'] : [], 'admin_to_go' => $this->admin_to_go, 'link' => $this->link, 'user_token' => $_SESSION['user_token'], 'stages' => $this->stages]);
                }
            }

            return $response;
        } catch (\Exception $exception) {
            $this->misc->log('Manager ' . __METHOD__, $exception);
        }
    }

    /**
     * process sales rep addition
     */
    public function add_salesrep_process()
    {
        try {
            if ($_SERVER["REQUEST_METHOD"] == "POST" && $_SESSION['csrf'] == $_POST['csrf']) {
                $this->loggedin_user_id = $_SESSION['user']['id'];
                // username email
                $email_to = $_POST['inputEmail'];

                // activation mail link process which was sent to the rep when manager send invitation
                $activation_url_part = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/activation/" . base64_encode($email_to) . "/";

                // checking whether this email already exist
                $sql = $this->dbConn->prepare("SELECT * FROM users WHERE Email = ?");
                $sql->execute(array($email_to));
                $count = $sql->rowCount();

                if ($count == 0) {
                    $first_name = $_POST['first_name'];
                    $last_name = $_POST['last_name'];
                    $status = PENDING_USER; // not activated yet
                    $registration_id = $_SESSION['user']['registration_id']; // company id of the sales manager
                    $role = SALES_REP_ROLE; // sales rep
                    $company_name = $_SESSION['user']['company_name'];
                    $manager_name = $_SESSION['user']['first_name'] . ' ' . $_SESSION['user']['last_name'];

                    // generate invitation code
                    $p_salt = $this->misc->rand_string(20);
                    $site_salt = SECRET; // from config.php
                    // password for user
                    $invitation_code = $this->misc->rand_number(10);
                    // need to match and double check the active link

                    $salted_hash = hash('sha256', $invitation_code . $site_salt . $p_salt);
                    $activation_link = $activation_url_part . $salted_hash;

                    // send mail after update the values from template
                    $sql_template = $this->dbConn->prepare("select * from mails where module = ? limit 1");
                    $sql_template->execute(array('add_sales_rep'));
                    $count_template = $sql_template->rowCount();

                    if ($count_template > 0) {
                        while ($row_template = $sql_template->fetch()) {
                            $body = str_ireplace('{{FIRST_NAME}}', $first_name, $row_template['body']);
                            $body = str_ireplace('{{COMPANY}}', $company_name, $body);
                            $body = str_ireplace('{{MANAGER_NAME}}', $manager_name, $body);
                            $body = str_ireplace('{{ACTIVATION_LINK}}', $activation_link, $body);
                            $body = str_ireplace('{{SalesCRM}}', APP_NAME, $body);
                            $body = str_ireplace('{{http://www.salescrm.com}}', WEBSITE_LINK, $body);
                            $body = str_ireplace('{{www.salescrm.com}}', WEBSITE, $body);
                            $body = str_ireplace('{{USERNAME}}', $email_to, $body);
                            $body = str_ireplace('{{PASSWORD}}', $invitation_code, $body);
                            $body = str_ireplace('{{ENCODED_EMAIL}}', base64_encode($email_to), $body);
                            $subject = $row_template['subject'] . ' to join with ' . $manager_name . ' for ' . $company_name;
                        }
                    }

                    $to = [
                        'email' => $email_to,
                        'name' => $first_name,
                    ];

                    $from = [
                        'email' => MAIL_FROM_EMAIL,
                        'name' => MAIL_FROM_NAME,
                    ];

                    $reply = [
                        'email' => MAIL_REPLY_TO_EMAIL,
                        'name' => MAIL_REPLY_TO_NAME,
                    ];

                    // sending mail to invite sales rep
                    if ($this->misc->send_mail($to, $from, $reply, $subject, $body)) {
                        $sql_insert_user = $this->dbConn->prepare("insert into users(registration_id, Name_First, Name_Last, Email, Password, User_Roles, User_Status, psalt, invitation_code) value(?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $value_user = $sql_insert_user->execute(array($registration_id, $first_name, $last_name, $email_to, $salted_hash, $role, $status, $p_salt, $invitation_code));

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
     * this method will fetch all tasks to show as notification
     */
    public function show_notification()
    {
    }

    /**
     * ajax data for dashboard chart
     */
    public function get_chart_data_for_dashboard()
    {
        try {
            if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['analytics_from']) && $_POST['analytics_from'] != '' && isset($_POST['analytics_to']) && $_POST['analytics_to'] != '') {

                $_SESSION['analytics_from'] = $_POST['analytics_from'];
                $_SESSION['analytics_to'] = $_POST['analytics_to'];
            }

            if (isset($_SESSION['analytics_from']) && isset($_SESSION['analytics_to'])) {
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
            $this->misc->log('Manager ' . __METHOD__, $exception);
        }
    }

    /**
     * this will calculate pipeline etc for sidebar
     *
     */
    private function sidebar_essentials()
    {
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
    public function pipeline_settings()
    {
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
                    $sql_pipeline = $this->dbConn->prepare("select max(position) as max_position from
					user_pipeline where registration_id = ? order by position");
                    $sql_pipeline->execute(array($this->loggedin_user_registration_id));
                    $count = $sql_pipeline->rowCount();

                    $max_position = 0;

                    if ($count > 0) {
                        while ($row_pipeline = $sql_pipeline->fetch()) {
                            $max_position = $row_pipeline['max_position'];
                            break;
                        }
                        // inserting new stage
                        $sql = $this->dbConn->prepare("insert into user_pipeline
						(user_id, deal_stage, position, registration_id) values (?, ?, ?, ?)");
                        $result = $sql->execute(array($this->loggedin_user_id, $new_stage,
                            intval($max_position) + 1, $this->loggedin_user_registration_id));
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
                    $sql_existing = $this->dbConn->prepare('select * from user_pipeline where
					registration_id = ? order by position');
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

                    if (!empty($differences_ids)) {
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
                                $sql_stage = $this->dbConn->prepare("update user_pipeline
								set position = ? where id = ?");
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

                                    echo json_encode($response);
                                    exit;
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
                    }
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

                            echo json_encode($response);
                            exit;
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
     * this will calculate the total of active contacts, active deals and pending tasks
     */
    public function calculateTotals($loggedin_user_id, $registration_id, $role, $dbConn)
    {
        try {
            $result = [];
            // sales rep
            // if ($role == 1) {
            //     // tasks later also need to take consideration the due date
            //     $sql_tasks = $dbConn->prepare("SELECT count(n.id) as tot from notes n
            // where n.Sales_Rep = ? and n.Task_Status = ? limit 1");
            //     $sql_tasks->execute(array($loggedin_user_id, 1));
            //     $count = $sql_tasks->rowCount();
            //     $pendingTasksTotal = 0;
            //     if ($count > 0) {
            //         while ($row = $sql_tasks->fetch()) {
            //             $pendingTasksTotal += $row['tot'];
            //         }
            //     }

            //     // deals
            //     $sql_deals = $dbConn->prepare("SELECT count(d.id) as tot from deals d
            // where d.sales_rep = ? and d.active = ? limit 1");
            //     $sql_deals->execute(array($loggedin_user_id, 0));
            //     $count = $sql_deals->rowCount();
            //     $activeDealsTotal = 0;
            //     if ($count > 0) {
            //         while ($row = $sql_deals->fetch()) {
            //             $activeDealsTotal += $row['tot'];
            //         }
            //     }

            //     // contacts
            //     $sql_contacts = $dbConn->prepare("SELECT count(c.id) as tot from contact c
            // where c.Sales_Rep = ? limit 1");
            //     $sql_contacts->execute(array($loggedin_user_id));
            //     $count = $sql_contacts->rowCount();
            //     $contactsTotal = 0;
            //     if ($count > 0) {
            //         while ($row = $sql_contacts->fetch()) {
            //             $contactsTotal += $row['tot'];
            //         }
            //     }

            //     $result = [
            //         'tasks' => $pendingTasksTotal,
            //         'deals' => $activeDealsTotal,
            //         'contacts' => $contactsTotal
            //     ];

            //     // sales manager
            // } else
            if ($role == 2) {

                // tasks
                $sql_tasks = $dbConn->prepare("SELECT count(n.id) as tot from notes n
                    inner join deals d on d.id = n.Deal
                    inner join users u on u.id = d.sales_rep
                    where u.registration_id = ? and n.Task_Status = ? limit 1");
                $sql_tasks->execute(array($registration_id, 1));
                $pendingTasksTotal = $sql_tasks->rowCount();
                if ($pendingTasksTotal > 0) {
                    while ($row = $sql_tasks->fetch()) {
                        $pendingTasksTotal = $row['tot'];
                    }
                }

                // deals
                $sql_deals = $dbConn->prepare("SELECT count(d.id) as tot from deals d
					inner join users u on u.id = d.sales_rep
                where u.registration_id = ? and d.active = ? limit 1");
                $sql_deals->execute(array($registration_id, 0));
                $count = $sql_deals->rowCount();
                $activeDealsTotal = 0;
                if ($count > 0) {
                    while ($row = $sql_deals->fetch()) {
                        $activeDealsTotal += $row['tot'];
                    }
                }

                // contacts
                $sql_contacts = $dbConn->prepare("SELECT count(c.id) as tot
                    FROM users u
                    inner join contact c on c.Sales_Rep = u.id
                    where u.registration_id = ? limit 1");
                $sql_contacts->execute(array($registration_id));
                $contactsTotal = $sql_contacts->rowCount();
                if ($contactsTotal > 0) {
                    while ($row = $sql_contacts->fetch()) {
                        $contactsTotal = $row['tot'];
                    }
                }

                $result = [
                    'tasks' => $pendingTasksTotal,
                    'deals' => $activeDealsTotal,
                    'contacts' => $contactsTotal,
                ];
            } elseif ($role == 3) {
                // super admin

                // tasks
                $sql_tasks = $dbConn->prepare("SELECT count(n.id) as tot from notes n limit 1");
                $sql_tasks->execute(array(1));
                $pendingTasksTotal = $sql_tasks->rowCount();
                if ($pendingTasksTotal > 0) {
                    while ($row = $sql_tasks->fetch()) {
                        $pendingTasksTotal = $row['tot'];
                    }
                }

                // deals
                $sql_deals = $dbConn->prepare("SELECT count(d.id) as tot from deals d
                    where d.active = ? limit 1");
                $sql_deals->execute(array(0));
                $activeDealsTotal = $sql_deals->rowCount();
                if ($activeDealsTotal > 0) {
                    while ($row = $sql_deals->fetch()) {
                        $activeDealsTotal = $row['tot'];
                    }
                }

                // contacts
                $sql_contacts = $dbConn->prepare("SELECT count(c.id) as tot
                    FROM users u
                    inner join contact c on c.Sales_Rep = u.id limit 1");
                $sql_contacts->execute(array());
                $contactsTotal = $sql_contacts->rowCount();
                if ($contactsTotal > 0) {
                    while ($row = $sql_contacts->fetch()) {
                        $contactsTotal = $row['tot'];
                    }
                }

                $result = [
                    'tasks' => $pendingTasksTotal,
                    'deals' => $activeDealsTotal,
                    'contacts' => $contactsTotal,
                ];
            }

            return $result;
        } catch (\Exception $exception) {
            $this->misc->log('Login' . __METHOD__, $exception);
        }
    }

}

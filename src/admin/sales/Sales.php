<?php

declare (strict_types = 1);

namespace App\admin\sales;

use App\admin\inc\GmailApi;
use App\admin\inc\GoogleCalendarApi;
use App\admin\inc\Log;
use App\admin\inc\Misc;
use App\ConnectDb;
use Google_Client;
use Google_Service_Calendar;
use Google_Service_Gmail;

/**
 * this is mail class for sales processes
 */
class Sales
{
    private $twig;
    private $dbConn;
    private $misc;
    private $phpMailerObj;
    private $admin_to_go;
    private $link;
    private $nav_link;
    private $key;
    private $stage;
    private $duration_select;
    private $countries;
    private $owner;
    private $deals;
    private $todo_descs;
    private $loggedin_user_id;
    private $company_id;
    private $currency;
    private $leadsSteps;
    private $log;
    private $googleCalendarApiObj;
    private $gmailApiObj;
    private $google_auth_url;
    private $google_redirect_url;
    private $gmail_auth_url;
    private $gmail_redirect_url;
    private $header_totals;
    private $limits;
    private $all_task_status;
    private $user_profile;
    private $gmail_service;

    public function __construct(
        $twig
    ) {
        // misc
        $this->misc = new Misc();

        // checking session
        $this->misc->check_session();
        $this->twig = $twig;
        $this->dbConn = ConnectDb::getConnection();

        $this->log = new Log();
        $this->loggedin_user_id = $_SESSION['user']['id'];
        $this->company_id = $_SESSION['user']['registration_id'];

        if (isset($_SERVER['HTTPS']) &&
            ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1) ||
            isset($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
            $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
            $protocol = 'https://';
        } else {
            $protocol = 'http://';
        }

        if (!array_key_exists('google_calendar_token', $_SESSION['user']) || !array_key_exists('google_gmail_token', $_SESSION['user']) || !array_key_exists('google_login_token', $_SESSION['user'])) {
            $sql_access_tokens = $this->dbConn->prepare("select * from user_access_tokens where user_id = ? limit 1");
            $sql_access_tokens->execute(array($this->loggedin_user_id));
            $count = $sql_access_tokens->rowCount();
            if ($count > 0) {
                while ($row = $sql_access_tokens->fetch()) {
                    $_SESSION['user']['google_calendar_token'] = $row['google_calendar_token'];
                    $_SESSION['user']['google_gmail_token'] = $row['google_gmail_token'];
                    $_SESSION['user']['google_login_token'] = $row['google_login_token'];
                }

                // gmail id for google api
                setcookie('gmail_id', base64_encode($row['gmail'] != null ? $row['gmail'] : ''), time() + COOKIE_SET_TIME, "/");

                // reload the page to effect sesion with dynamic link etc update
                header("Refresh:0");
                exit;
            }
        } elseif (!array_key_exists('gmail_id', $_COOKIE)) {
            $sql = $this->dbConn->prepare('select * from user_access_tokens where user_id = ? limit 1');
            $sql->execute(array($this->loggedin_user_id));
            $count = $sql->rowCount();

            if ($count > 0) {
                while ($row = $sql->fetch()) {
                    if ($row['email'] != null) {
                        setcookie('gmail_id', base64_encode($row['email']), time() + COOKIE_SET_TIME, "/");
                    }
                    break;
                }
            }
        }

        // gmail api
        if (!isset($this->gmailApiObj)) {
            $this->gmailApiObj = new GmailApi();
            $this->gmail_redirect_url = $protocol . $_SERVER['HTTP_HOST'] . GMAIL_OAUTH_CLIENT_REDIRECT_URL;
            // oauth scopes
            $scopees = [
                'https://www.googleapis.com/auth/userinfo.email',
                'https://www.googleapis.com/auth/gmail.labels',
                'https://www.googleapis.com/auth/gmail.compose',
                'https://www.googleapis.com/auth/gmail.modify',
                'https://www.googleapis.com/auth/gmail.send',
            ];
            if (!isset($_SESSION['user']['google_gmail_token']) || $_SESSION['user']['google_gmail_token'] == '') {
                $this->gmail_auth_url = 'https://accounts.google.com/o/oauth2/auth?scope=' .
                urlencode(implode(' ', $scopees)) .
                '&redirect_uri=' . $this->gmail_redirect_url .
                    '&response_type=code&client_id=' . GMAIL_OAUTH_CLIENT_ID .
                    '&access_type=offline';
            } else {
                $this->gmail_auth_url = '';
                // set gmail client and create service
                $client = $this->gmailApiObj->getClient($_SESSION['user']['google_gmail_token']);
                $this->gmail_service = new Google_Service_Gmail($client);
            }
        }

        if (!isset($this->googleCalendarApiObj)) {
            // google calendar API need to update the key and secret based on the domain changes
            // https://console.developers.google.com/apis/credentials/oauthclient
            $this->googleCalendarApiObj = new GoogleCalendarApi();
            $this->google_redirect_url = $protocol . $_SERVER['HTTP_HOST'] . GOOGLE_OAUTH_CLIENT_REDIRECT_URL;
            if (!isset($_SESSION['user']['google_calendar_token']) || $_SESSION['user']['google_calendar_token'] == '') {
                $this->google_auth_url = 'https://accounts.google.com/o/oauth2/auth?scope=' . urlencode('https://www.googleapis.com/auth/calendar') . '&redirect_uri=' . $this->google_redirect_url . '&response_type=code&client_id=' . GOOGLE_OAUTH_CLIENT_ID . '&access_type=online';
            } else {
                $this->google_auth_url = '';
            }
        }

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

        // get the totals nos for header
        $this->header_totals = $this->calculateTotals($_SESSION['user']['id'], $_SESSION['user']['registration_id'], $_SESSION['user']['role_id'], $this->dbConn);

        $this->currency = $_SESSION['user']['currency_code'];

        // take the last item
        $this->nav_link = array_pop($path);
        // for sidebar sales
        $this->sidebar_essentials();

        // chart preparation date range
        if (!array_key_exists('sales_analytics_from', $_SESSION) || $_SESSION['sales_analytics_from'] == '') {
            $from = date("Y-m-d", strtotime(date("Y-m-d", strtotime(date("Y-m-d"))) . "-1 month"));
            $_SESSION['sales_analytics_from'] = $from;
        }
        if (!array_key_exists('sales_analytics_to', $_SESSION) || $_SESSION['sales_analytics_to'] == '') {
            $to = date('Y-m-d');
            $_SESSION['sales_analytics_to'] = $to;
        }

        if (!isset($_SESSION['csrf']) || $_SESSION['csrf'] == '') {
            // create csrf key for all forms
            $this->key = sha1(microtime());
            $_SESSION['csrf'] = $this->key;
        }

        // write into log
        $this->log->write();
    }

    /**
     * it will show the tasks
     */
    public function __invoke(): string
    {
        try {
            $sql = $this->dbConn->prepare("SELECT n.id as nid, n.Notes, n.Date, n.todo_desc_id, n.task_status, n.Task_Update,
                n.sales_rep, n.todo_due_date, n.due_date, n.start_time, n.duration,
                u.Name_First, u.Name_Last, c.Contact_First,
                c.Contact_Last, c.Company, c.Email, s.status as task_status,
                td.description as todo_description, p.deal_stage, d.id,
                d.sales_rep, d.project_type, u.Name_First, u.Name_Last, u.login_with_code
                FROM notes n
                inner join users u on u.id = n.sales_rep
                inner join deals d on d.id = n.Deal
                inner join contact c on c.id = d.contact_id
                inner join task_status s on s.id = n.task_status
                inner join todo_desc td on td.id = n.todo_desc_id
                inner join user_pipeline p on p.id = d.stage
                group by nid
                having n.sales_rep = ?
                order by nid desc");
            $sql->execute(array($this->loggedin_user_id));
            $count = $sql->rowCount();
            $tasks = [];
            $login_with_code = 0;

            if ($count > 0) {
                while ($row = $sql->fetch()) {
                    $login_with_code = $row['login_with_code'];
                    // comment count
                    $sql_comment_count = $this->dbConn->prepare("select c.*, n.id as nid
                        from comments c
                        inner join notes n on n.id = c.note_id
                        group by c.id
                        having nid = ? and c.status = ?");
                    $sql_comment_count->execute(array($row['nid'], 0));
                    $count_comment_count = $sql_comment_count->rowCount();
                    $row['comment_count'] = $count_comment_count;

                    $tasks[] = $row;
                }
            }

            $response = $this->twig->render('admin/sales/tasks.html.twig', [
                'uri' => $_SERVER['REQUEST_URI'],
                'session_user' => isset($_SESSION['user']) ? $_SESSION['user'] : [],
                'admin_to_go' => $this->admin_to_go,
                'link' => $this->link,
                'user_token' => $_SESSION['user_token'],
                'tasks' => $tasks,
                'nav_link' => $this->nav_link,
                'key' => $_SESSION['csrf'],
                'todo_descs' => $this->todo_descs,
                'deals' => $this->deals,
                'countries' => $this->countries,
                'stage' => $this->stage,
                'duration_select' => $this->duration_select,
                'owner' => $this->owner,
                'google_auth_url' => $this->google_auth_url,
                'gmail_auth_url' => $this->gmail_auth_url,
                'header_totals' => $this->header_totals,
                'login_with_code' => $login_with_code,
                'all_task_status' => $this->all_task_status,
                'gmail_service' => $this->gmail_service,
            ]);

            return $response;
        } catch (\Exception $exception) {
            $this->misc->log('Sales ' . __METHOD__, $exception);
        }
    }

    /**
     * all these details and variables required for sidebar of sales panel
     * need to put in cache
     */
    private function sidebar_essentials()
    {
        try {
            // owner
            // $sql = $this->dbConn->prepare('select * from users where registration_id = ? order by id');
            // $sql->execute(array($_SESSION['user']['registration_id']));
            // $count = $sql->rowCount();
            // $this->owner = [];

            // if ($count > 0) {
            //     $this->owner = [];
            //     while ($row = $sql->fetch()) {
            //         $this->owner[] = $row;
            //     }
            // }

            // get all task status
            if (empty($this->all_task_status)) {
                $sql_all_task_status = $this->dbConn->prepare('select * from task_status');
                $sql_all_task_status->execute(array());
                $count_task_status = $sql_all_task_status->rowCount();
                if ($count_task_status > 0) {
                    $temp = [];
                    while ($row_task_status = $sql_all_task_status->fetch()) {
                        $temp[] = $row_task_status;
                    }

                    $this->all_task_status = $temp;
                }
            }

            // create status stage select option
            if (empty($this->stage)) {
                $sql = $this->dbConn->prepare("select * from user_pipeline where user_id = ? order by id limit 1");
                $sql->execute(array($this->loggedin_user_id));
                $count = $sql->rowCount();
                $this->stage = [];

                if ($count > 0) {
                    $row = $sql->fetch();
                    $this->stage = $row['id'];
                }
            }

            // also add to Deal won and Deal lost options

            // to do desc
            if (empty($this->todo_descs)) {
                $sql = $this->dbConn->prepare('select * from todo_desc order by id');
                $count = $sql->execute();
                $this->todo_descs = [];

                if ($count > 0) {
                    while ($row = $sql->fetch()) {
                        $this->todo_descs[] = $row;
                    }
                }
            }

            // deals
            if (empty($this->deals)) {
                $sql = $this->dbConn->prepare("select d.* from contact c
                inner join deals d on d.contact_id = c.id
                inner join users u on u.id = d.sales_rep
                where d.sales_rep = ? and d.active = 0");
                $sql->execute(array($this->loggedin_user_id));
                $count = $sql->rowCount();
                $this->deals = [];

                if ($count > 0) {
                    while ($row = $sql->fetch()) {
                        $this->deals[] = $row;
                    }
                }
            }

            // limits - how many deals and contacts rest from current limit
            if (empty($this->limits)) {
                $sql = $this->dbConn->prepare("select * from companies
                where registration_id = ? limit 1");
                $sql->execute(array($this->company_id));
                $count = $sql->rowCount();
                $this->limits = [];

                if ($count > 0) {
                    while ($row = $sql->fetch()) {
                        $this->limits = $row;
                        break;
                    }
                }
            }

            // countries
            if (empty($this->countries)) {
                $sql = $this->dbConn->prepare('select * from countries order by id');
                $count = $sql->execute();
                $this->countries = [];

                if ($count > 0) {
                    while ($row = $sql->fetch()) {
                        $this->countries[] = $row;
                    }
                }
            }

            // duration select
            if (empty($this->duration_select)) {
                $this->duration_select = [];
                for ($hours = 0; $hours < 3; $hours++) {
                    // the interval for hours is '1'
                    for ($mins = 0; $mins < 60; $mins += 15) {
                        // the interval for mins is '15'
                        $this->duration_select[] = str_pad((string) $hours, 2, '0', STR_PAD_LEFT) . ':'
                        . str_pad((string) $mins, 2, '0', STR_PAD_LEFT);
                    }
                }
            }

            return true;
        } catch (\Exception $exception) {
            $this->misc->log('Sales ' . __METHOD__, $exception);
            return false;
        }
    }

    /**
     * it will add a comments for that note id
     */
    public function add_comment()
    {
        try {
            if ($_SERVER["REQUEST_METHOD"] == "POST" && $_SESSION['csrf'] == $_POST['csrf']) {
                $note_id = $_POST['note_id'];
                $task_status = $_POST['task_status'];
                $comment = $_POST['comment'];
                $created_at = date("Y-m-d h:i:s");
                $result = false;

                // task status must not be completed
                if ($task_status != 'completed') {
                    $sql_insert = $this->dbConn->prepare("insert into comments (comment,
                    note_id,
                    created_at) values (?, ?, ?)");
                    $result = $sql_insert->execute(array($comment, $note_id, $created_at));
                }

                if ($result) {
                    echo '200';
                    exit;
                } else {
                    echo '400';
                    exit;
                }
            }
        } catch (\Exception $exception) {
            $this->misc->log('Sales ' . __METHOD__, $exception);
        }
    }

    /**
     * it will edit/delete a comments for that note id
     */
    public function edit_comment()
    {
        try {
            if ($_SERVER["REQUEST_METHOD"] == "POST" && $_SESSION['csrf'] == $_POST['csrf']) {
                $comment_id = $_POST['comment_id'];
                $note_id = $_POST['note_id'];
                $change = $_POST['change'];

                if ($change == 'delete') {
                    // 0 = active 1 = deleted
                    $sql_comments = $this->dbConn->prepare("update comments set status = ? where id = ?");
                    $result_comments = $sql_comments->execute(array(1, $comment_id));

                    if ($result_comments) {
                        $result = [
                            'status' => '200',
                            'data' => $note_id,
                        ];
                    } else {
                        $result = [
                            'status' => '400',
                            'data' => '',
                        ];
                    }

                    return json_encode($result);
                    exit;

                } elseif ($change == 'edit') {
                }
            }
        } catch (\Exception $exception) {
            $this->misc->log('Sales ' . __METHOD__, $exception);
        }
    }

    /**
     * get all comments of a note
     */
    public function get_note_comments()
    {
        try {
            if ($_SERVER["REQUEST_METHOD"] == "POST") {
                $note_id = $_POST['note_id'];

                // get all comments
                $sql_comments = $this->dbConn->prepare("select c.*, n.id as nid, n.Notes
                    from comments c
                    inner join notes n on n.id = c.note_id
                    group by c.id
                    having n.id = ?
                    and status = 0
                    order by c.id desc");
                $sql_comments->execute(array($note_id));
                $count_comments = $sql_comments->rowCount();

                if ($count_comments > 0) {
                    $comments = '<div id="server-result_comment_edit" class="alert" role="alert"></div>
                    <div class="card-body">
                    <div class="table-responsive">
                    <table class="table table-bordered datatable" id="dataTable2" width="100%" cellspacing="0">
                    <thead>
                    <tr>
                    <th>When</th>
                    <th>Comment</th>
                    <th>Note</th>
                    <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>';

                    while ($row_comments = $sql_comments->fetch()) {
                        $comments .= '<tr>
                        <td>' . $row_comments['created_at'] . '</td>
                        <td>' . $row_comments['comment'] . '</td>
                        <td>' . $row_comments['Notes'] . '</td>
                        <td data-toggle="tooltip" title="Delete">
                        <form method="post" name="frm_delete_comment" id="frm_delete_comment" action="/sales/edit_comment" class="simple-form">
                        <input type="hidden" name="csrf" id="csrf" value="' . $_SESSION['csrf'] . '" />
                        <input type="hidden" name="change" id="change" value="delete">
                        <input type="hidden" name="note_id" id="note_id" value="' . $row_comments['nid'] . '">
                        <input type="hidden" name="comment_id" id="comment_id" value="' . $row_comments['id'] . '">
                        <button type="submit" id="delete_comment_submit" class="fabutton"><i class="fas fa-trash-alt"></i></button>
                        </form>
                        </td>
                        </tr>';
                    }

                    $comments .= '</tbody></table></div></div>';
                } else {
                    $comments = '';
                }

                echo $comments;
                exit;
            }
        } catch (\Exception $exception) {
            $this->misc->log('Sales ' . __METHOD__, $exception);
        }
    }

    /**
     * fetch all active deals
     */
    public function deals()
    {
        try {
            $sql = $this->dbConn->prepare("select * from user_pipeline where user_id = ? order by position");
            $sql->execute(array($this->loggedin_user_id));
            $count = $sql->rowCount();

            $this->leadsSteps = [];
            $leadsStepsBudget = [];
            if ($count > 0) {
                while ($row = $sql->fetch()) {
                    $this->leadsSteps[$row['id']] = $row['deal_stage'];
                    $leadsStepsBudget[$row['id']] = 0;

                    $sql_budget = $this->dbConn->prepare("SELECT sum(d.budget) as budget
                        FROM deals d
                        where d.stage = ? and d.sales_rep = ? and d.active = 0");
                    $sql_budget->execute(array($row['id'], $this->loggedin_user_id));
                    $count_budget = $sql_budget->rowCount();
                    if ($count_budget > 0) {
                        while ($row_budget = $sql_budget->fetch()) {
                            $leadsStepsBudget[$row['id']] = $row_budget['budget'];
                        }
                    }
                }
            }

            // get id of contact_status values
            reset($this->leadsSteps);
            $lead_status = key($this->leadsSteps);

            $csrf_deal_delete = sha1(microtime());
            $_SESSION['csrf_deal_delete'] = $csrf_deal_delete;

            $csrf_deal_edit = sha1(microtime());
            $_SESSION['csrf_deal_edit'] = $csrf_deal_edit;

            // dynamic pipeline stages from user data
            $tabs = '';
            $i = 1;
            foreach ($this->leadsSteps as $id => $val) {
                $currency_tab = $leadsStepsBudget[$id] != '' ? $this->currency . ' ' : '';
                // first tab keep active
                if ($i == 1) {
                    $tabs .= '<li><a id="tabs_add_leads_tab' . $i . '" class="">' . ucwords($val) . '</a><div id="budget_tabs_add_leads_tab' . $i . '" class="budget_total budget_total_active">' . $currency_tab . $leadsStepsBudget[$id] . '</div></li>';
                } else {
                    $tabs .= '<li><a id="tabs_add_leads_tab' . $i . '" class="inactive">' . ucwords($val) . '</a><div id="budget_tabs_add_leads_tab' . $i . '" class="budget_total">' . $currency_tab . $leadsStepsBudget[$id] . '</div></li>';
                }
                $i++;
            }

            // different $id is different status or leads status or stage
            $sql = $this->dbConn->prepare("SELECT c.Contact_First, c.Contact_Last, c.Company, c.Email, c.Website, c.Phone, c.Background_Info,
                d.*
                FROM deals d
                inner join users u on u.id = d.sales_rep
                inner join contact c on c.id = d.contact_id
                group by d.id
                having d.stage = ? and d.sales_rep = ? and active = 0
                order by d.id desc");
            $sql->execute(array($lead_status, $this->loggedin_user_id));
            $count = $sql->rowCount();
            $final = '';
            $modal = '';

            if ($count > 0) {
                $final .= '<table class="table table-bordered datatable" id="dataTable3" width="100%" cellspacing="0">
                <thead>
                <tr>
                <th>Deal Description</th>
                <th>Contact Person</th>
                <th>Company Name</th>
                <th>Budget</th>
                <th>Edit</th>
                <th data-hover="tooltip" title="Move a Deal to another Pipeline stage">Move To</th>
                </tr>
                </thead>
                <tbody>';

                while ($row = $sql->fetch()) {
                    $proposal_due_date = explode('-', $row['proposal_due_date']);
                    $proposal_due_date = $proposal_due_date[1] . '/' . $proposal_due_date[2] . '/' . $proposal_due_date[0];

                    $i = 1;
                    $move_menu = '';
                    foreach ($this->leadsSteps as $key => $val) {
                        $currency_budget = $row['budget'] != '' ? $this->currency . ' ' : '';
                        // first tab keep active
                        if ($i == 1) {
                            $move_menu .= '<a class="dropdown-item active" href="#" onclick="move(' . $lead_status . ', ' . $key . ', ' . $row['id'] . ');">' . ucwords($val) . '</a>';
                        } else {
                            $move_menu .= '<a class="dropdown-item" href="#" onclick="move(' . $lead_status . ', ' . $key . ', ' . $row['id'] . ');">' . ucwords($val) . '</a>';
                        }
                        $i++;
                    }

                    // for Deal won and Deal lost options
                    $move_menu .= '<a class="dropdown-item deal-won-dropdown-item" href="#" onclick="move(' . $lead_status . ', \'won\', ' . $row['id'] . ');">Deal Won</a>';
                    $move_menu .= '<a class="dropdown-item deal-lost-dropdown-item" href="#" onclick="move(' . $lead_status . ', \'lost\', ' . $row['id'] . ');">Deal Lost</a>';

                    $move_button = '<div class="dropdown show">
                    <a class="btn-small btn-success dropdown-toggle" href="#" role="button" name="move_button" id="move_button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="fas fa-truck-moving fa-2x" style="color:#DDDDDD;"></i>
                    </a>
                    <div class="dropdown-menu" aria-labelledby="dropdownMenuLink">
                    ' . $move_menu . '
                    </div>
                    </div>';

                    $final .= '<tr class="table tr">
                    <td>' . $row['project_description'] . '</td>
                    <td>' . $row['Contact_First'] . ' ' . $row['Contact_Last'] . '</td>
                    <td>' . $row['Company'] . '</td>
                    <td>' . $currency_budget . $row['budget'] . '</td>
                    <td>
                    <div class="row edit-row">
                    <span class="edit-span" id="sales_task_edit_submit" data-toggle="modal" data-target="#edit_deal_modal_' . $row['id'] . '"><button type="button" class="btn btn-primary"><i class="fas fa-edit" data-toggle="tooltip" title="Edit"></i></button></span>
                    <span class="edit-span"><form method="post" name="frm_data_deal_delete_other" id="frm_data_deal_delete_other" action="/sales/edit_deal">
                    <input type="hidden" name="csrf_deal_edit" id="csrf_deal_edit" value="' . $_SESSION['csrf'] . '" />
                    <input type="hidden" id="edit_deal_change" name="edit_deal_change" value="delete">
                    <input type="hidden" id="edit_deal_id" name="edit_deal_id" value="' . $row['id'] . '">
                    <button type="submit" class="btn btn-primary"><i class="fa fa-eye-slash" data-toggle="tooltip" title="Hide"></i></button>
                    </form></span>
                    </div>
                    </td>
                    <td>' . $move_button . '</td>
                    </tr>';

                    $original_values = 'edit_deal_stage=' . $lead_status . '&edit_deal_contact_lookup=' . $row['Contact_First'] . '&edit_deal_email=' . $row['Email'] . '&edit_deal_phone=' . $row['Phone'] . '&edit_deal_company=' . $row['Company'] . '&edit_deal_deal=' . $row['project_type'] . '&edit_deal_amount=' . $row['budget'] . '&edit_deal_rating=' . $row['rating'] . '&edit_deal_project_description=' . $row['project_description'] . '&edit_deal_deliverables=' . $row['deliverables'] . '&edit_deal_proposal_due_date=' . $proposal_due_date . '';

                    // print the modal for later use
                    $row['original_values'] = $original_values;
                    $row['proposal_due_date'] = $proposal_due_date;
                    $modal .= $this->create_deal_modal($row);
                }

                $final .= '</tbody>
                </table>';
            } else {
                $final .= '';
            }

            $final .= $modal;
            $containers = '<div class="spacer">
            &nbsp;
            </div><br clear="all">';
            $i = 1;

            foreach ($this->leadsSteps as $id => $status) {
                // first container keep display
                if ($i == 1) {
                    $containers .= '<div class="container_tabs_add_leads" id="tabs_add_leads_tab' . $i . 'C">' . $final . '</div><div class="container_tabs_add_leads">Click here to <button type="button" class="btn btn-warning add-new-button" class="nav-link" data-toggle="modal" data-target="#add_new_modal">
					<span>
						<i class="fas fa-fw fa-plus-square"></i>Add</span>
					</button></div>';
                } else {
                    $containers .= '<div class="container_tabs_add_leads" id="tabs_add_leads_tab' . $i . 'C" style="display: none;"></div>';
                }

                $i++;
            }

            $response = $this->twig->render('admin/sales/deals.html.twig', ['uri' => $_SERVER['REQUEST_URI'], 'session_user' => isset($_SESSION['user']) ? $_SESSION['user'] : [], 'admin_to_go' => $this->admin_to_go, 'link' => $this->link, 'nav_link' => $this->nav_link, 'user_token' => $_SESSION['user_token'], 'currency' => $this->currency, 'tabs' => $tabs, 'containers' => $containers, 'owner' => $this->owner, 'key' => $_SESSION['csrf'], 'stage' => $this->stage, 'duration_select' => $this->duration_select, 'deals' => $this->deals, 'todo_descs' => $this->todo_descs, 'countries' => $this->countries, 'google_auth_url' => $this->google_auth_url, 'header_totals' => $this->header_totals, 'limits' => $this->limits]);

            return $response;
        } catch (\Exception $exception) {
            $this->misc->log('Sales ' . __METHOD__, $exception);
        }
    }

    /**
     * this will create modal for each deal
     * @return [type] [description]
     */
    private function create_deal_modal($row)
    {
        try {
            $modal = '<!-- Edit Deal Modal -->
            <div class="modal fade" id="edit_deal_modal_' . $row['id'] . '" tabindex="-1" role="dialog" aria-labelledby="Edit Deal" aria-hidden="true">
            <div class="modal-dialog" role="document">
            <div class="modal-content">
            <div class="modal-header">
            <h5 class="modal-title" id="edit_deal_exampleModalLongTitle">Edit Deal</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
            </button>
            </div>
            <div class="modal-body">
            <div class="container_tabs_add_forms">
            <div id="edit_deal_server-results_' . $row['id'] . '" class="alert alert-success edit_deal_server-results" role="alert">Red or green border means required field.</div>
            <div id="edit_deal_form">
            <form action="/sales/edit_deal" method="post" id="frm_edit_deal" class="simple-form">
            <input type="hidden" name="csrf_deal_edit" id="csrf_deal_edit" value="' . $_SESSION['csrf'] . '" />
            <input type="hidden" name="edit_deal_change" id="edit_deal_change" value="edit">
            <input type="hidden" name="edit_deal_id" id="edit_deal_id" value="' . $row['id'] . '">
            <input type="hidden" name="original_values" id="original_values" value="' . $row['original_values'] . '">
            <input type="hidden" name="edit_deal_stage" id="edit_deal_stage" value="' . $row['stage'] . '">
            <div class="row">
            <div class="form-group">
            <input type="text" name="edit_deal_contact_lookup" id="edit_deal_contact_lookup" placeholder="Contact" value="' . $row['Contact_First'] . '" class="form-control" required disabled>
            <div id="edit_deal_contact" name="edit_deal_contact" class="form-control" style="display:none;"></div>
            </div>
            <div class="form-group">
            <input type="text" name="edit_deal_email" id="edit_deal_email" value="' . $row['Email'] . '"  placeholder="Email" class="form-control" required disabled>
            </div>
            <div class="form-group">
            <input type="text" name="edit_deal_phone" id="edit_deal_phone" value="' . $row['Phone'] . '"  placeholder="Phone" class="form-control" required disabled>
            </div>
            </div>
            <div class="row">
            <div class="form-group">
            <input type="text" name="edit_deal_company" id="edit_deal_company" value="' . $row['Company'] . '"  placeholder="Company" class="form-control" disabled>
            </div>
            <div class="form-group">
            <input type="text" name="edit_deal_deal" id="edit_deal_deal" value="' . $row['project_type'] . '"  maxlength="200" placeholder="Deal name eg. Logo design" class="form-control" required>
            </div>
            </div>
            <div class="row">
            <div class="form-group">
            <input type="text" name="edit_deal_amount" id="edit_deal_amount" value="' . $row['budget'] . '"  placeholder="Amount eg. 1800" class="form-control" required>
            </div>
            <div class="form-group">
            <input type="number" name="edit_deal_rating" id="edit_deal_rating" min="1" max="5" placeholder="Deal Rating: range 1 to 5" value="' . $row['rating'] . '" class="form-control" required>
            </div>
            </div>
            <div class="row">
            <div class="form-group">
            <input type="text" name="edit_deal_project_description" id="edit_deal_project_description" value="' . $row['project_description'] . '"  placeholder="Deal details eg. Logo design for his new website." class="form-control">
            </div>
            <div class="form-group">
            <input type="text" name="edit_deal_deliverables" id="edit_deal_deliverables" value="' . $row['deliverables'] . '"  maxlength="200" placeholder="Deliverables eg. psd, png and related all files of logo in zip format." class="form-control">
            </div>
            </div>
            <div class="row">
            <div class="form-group">
            <input type="text" class="edit_deal_proposal_due_date" name="edit_deal_proposal_due_date_' . $row['id'] . '" id="edit_deal_proposal_due_date_' . $row['id'] . '" value="' . $row['proposal_due_date'] . '"  placeholder="Proposal due date, click to select date" class="form-control">
            </div>
            </div>
            <div class="row">
            <div class="form-group">
            <input type="submit" class="btn btn-primary" id="edit_deal_submit" value="Update">
            <input class="btn btn-secondary" type="reset">
            </div>
            </div>
            </form>
            </div>
            </div>
            </div>
            </div>
            </div>
            </div>
            <!-- modal end -->';

            return $modal;
        } catch (\Exception $exception) {
            $this->misc->log('Sales ' . __METHOD__, $exception);
        }
    }

    /**
     * fetch active deals from ajax for different tabs
     */
    public function get_deal_details()
    {
        try {

            if ($_SERVER["REQUEST_METHOD"] == "POST") {
                $sql = $this->dbConn->prepare("select * from user_pipeline where user_id = ? order by position");
                $sql->execute(array($this->loggedin_user_id));
                $count = $sql->rowCount();

                $this->leadsSteps = [];
                $leadsStepsBudget = [];
                if ($count > 0) {
                    while ($row = $sql->fetch()) {
                        $this->leadsSteps[$row['id']] = $row['deal_stage'];
                        $leadsStepsBudget[$row['id']] = 0;

                        $sql_budget = $this->dbConn->prepare("SELECT sum(d.budget) as budget
                            FROM deals d
                            where d.stage = ? and d.sales_rep = ?");
                        $sql_budget->execute(array($row['id'], $this->loggedin_user_id));
                        $count_budget = $sql_budget->rowCount();
                        if ($count_budget > 0) {
                            while ($row_budget = $sql_budget->fetch()) {
                                $leadsStepsBudget[$row['id']] = $row_budget['budget'];
                            }
                        }
                    }
                }

                // get id of contact_status values comes from Ajax tab click
                $contact_status = strtolower($_POST['contact']);
                $lead_status = array_search(strtolower($contact_status), array_map('strtolower', $this->leadsSteps));

                $sql = $this->dbConn->prepare("SELECT c.Contact_First, c.Contact_Last, c.Company, c.Email, c.Phone, c.Website,
                    d.*
                    FROM deals d
                    inner join users u on u.id = d.sales_rep
                    inner join contact c on c.id = d.contact_id
                    group by d.id
                    having d.stage = ? and d.sales_rep = ? and active = 0
                    order by d.id desc");
                $sql->execute(array($lead_status, $this->loggedin_user_id));
                $count = $sql->rowCount();
                $final = '';

                if ($count > 0) {
                    $final .= '<table class="table table-bordered datatable_deals" id="' . $lead_status . '" width="100%" cellspacing="0">
                    <thead>
                    <tr>
                    <th>Deal Description</th>
                    <th>Contact Person</th>
                    <th>Company Name</th>
                    <th>Budget</th>
                    <th>Edit</th>
                    <th data-hover="tooltip" title="Move a Deal to another Pipeline stage">Move To</th>
                    </tr>
                    </thead>
                    <tbody>';

                    while ($row = $sql->fetch()) {
                        $currency_budget = $row['budget'] != '' ? $this->currency . ' ' : '';
                        $move_menu = '';

                        foreach ($this->leadsSteps as $key => $val) {
                            // current tab keep active
                            if ($lead_status == $key) {
                                $move_menu .= '<a class="dropdown-item active" href="#" onclick="move(' . $lead_status . ', ' . $key . ', ' . $row['id'] . ');">' . ucwords($val) . '</a>';
                            } else {
                                $move_menu .= '<a class="dropdown-item" href="#" onclick="move(' . $lead_status . ', ' . $key . ', ' . $row['id'] . ');">' . ucwords($val) . '</a>';
                            }
                        }

                        // for Deal won and Deal lost options
                        $move_menu .= '<a class="dropdown-item deal-won-dropdown-item" href="#" onclick="move(' . $lead_status . ', \'won\', ' . $row['id'] . ');" data-toggle="modal" data-target="#dealwonModal">Deal Won</a>';
                        $move_menu .= '<a class="dropdown-item deal-lost-dropdown-item" href="#" onclick="move(' . $lead_status . ', \'lost\', ' . $row['id'] . ');" data-toggle="modal" data-target="#deallostModal">Deal Lost</a>';

                        $move_button = '<div class="dropdown show">
                        <a class="btn-small btn-success dropdown-toggle" href="#" role="button" name="move_button" id="move_button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-truck-moving fa-2x" style="color:#DDDDDD;"></i>
                        </a>
                        <div class="dropdown-menu" aria-labelledby="dropdownMenuLink">
                        ' . $move_menu . '
                        </div>
                        </div>';
                        // allong with edit and hide deal form option for other deal tabs
                        $final .= '<tr class="table tr">
                        <td>' . $row['project_description'] . '</td>
                        <td>' . $row['Contact_First'] . ' ' . $row['Contact_Last'] . '</td>
                        <td>' . $row['Company'] . '</td>
                        <td>' . $currency_budget . $row['budget'] . '</td>
                        <td>
                        <div class="row edit-row">
                        <span class="edit-span" id="sales_task_edit_submit" data-toggle="modal" data-target="#edit_deal_modal_' . $row['id'] . '"><button type="button" class="btn btn-primary"><i class="fas fa-edit" data-toggle="tooltip" title="Edit"></i></button></span>
                        <span class="edit-span"  id="delete_deal_submit"><form method="post" name="frm_data_deal_delete_other" id="frm_data_deal_delete_other" action="/sales/edit_deal">
                        <input type="hidden" name="csrf_deal_edit" id="csrf_deal_edit" value="' . $_SESSION['csrf'] . '" />
                        <input type="hidden" id="edit_deal_change" name="edit_deal_change" value="delete">
                        <input type="hidden" id="edit_deal_id" name="edit_deal_id" value="' . $row['id'] . '">
                        <button type="submit" class="btn btn-primary"><i class="fa fa-eye-slash" data-toggle="tooltip" title="Hide"></i></button>
                        </form></span>
                        </div>
                        </td>
                        <td>' . $move_button . '</td>
                        </tr>';

                        $original_values = 'edit_deal_stage=' . $lead_status . '&edit_deal_contact_lookup=' . $row['Contact_First'] . '&edit_deal_email=' . $row['Email'] . '&edit_deal_phone=' . $row['Phone'] . '&edit_deal_company=' . $row['Company'] . '&edit_deal_deal=' . $row['project_type'] . '&edit_deal_amount=' . $row['budget'] . '&edit_deal_rating=' . $row['rating'] . '&edit_deal_project_description=' . $row['project_description'] . '&edit_deal_deliverables=' . $row['deliverables'] . '&edit_deal_proposal_due_date=' . $row['proposal_due_date'] . '';
                        // for first/default deal tab
                        echo '<!-- Edit Deal Modal -->
                        <div class="modal fade" id="edit_deal_modal_' . $row['id'] . '" tabindex="-1" role="dialog" aria-labelledby="Edit Deal" aria-hidden="true">
                        <div class="modal-dialog" role="document">
                        <div class="modal-content" style="width:120%;">
                        <div class="modal-header">
                        <h5 class="modal-title" id="edit_deal_exampleModalLongTitle">Edit Deal</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                        </button>
                        </div>
                        <div class="modal-body">
                        <div class="container_tabs_add_forms">
                        <div id="edit_deal_server-results_' . $row['id'] . '" class="alert alert-success edit_deal_server-results" role="alert">Red or green border means required field.</div>
                        <div id="edit_deal_form_other">
                        <form action="/sales/edit_deal" method="post" id="frm_edit_deal" class="simple-form deal-other" >
                        <input type="hidden" name="csrf_deal_edit" id="csrf_deal_edit" value="' . $_SESSION['csrf'] . '" />
                        <input type="hidden" name="edit_deal_change" id="edit_deal_change" value="edit">
                        <input type="hidden" name="edit_deal_id" id="edit_deal_id" value="' . $row['id'] . '">
                        <input type="hidden" name="original_values" id="original_values" value="' . $original_values . '">
                        <input type="hidden" name="edit_deal_stage" id="edit_deal_stage" value="' . $row['stage'] . '">
                        <div class="row">
                        <div class="form-group">
                        <input type="text" name="edit_deal_contact_lookup" id="edit_deal_contact_lookup" placeholder="Contact" value="' . $row['Contact_First'] . '" class="form-control" required>
                        <div id="edit_deal_contact" name="edit_deal_contact" class="form-control" style="display:none;"></div>
                        </div>
                        <div class="form-group">
                        <input type="text" name="edit_deal_email" id="edit_deal_email" value="' . $row['Email'] . '"  placeholder="Email" class="form-control" required>
                        </div>
                        <div class="form-group">
                        <input type="text" name="edit_deal_phone" id="edit_deal_phone" value="' . $row['Phone'] . '"  placeholder="Phone" class="form-control" required>
                        </div>
                        </div>
                        <div class="row">
                        <div class="form-group">
                        <input type="text" name="edit_deal_company" id="edit_deal_company" value="' . $row['Company'] . '"  placeholder="Company" class="form-control" >
                        </div>
                        <div class="form-group">
                        <input type="text" name="edit_deal_deal" id="edit_deal_deal" value="' . $row['project_type'] . '"  maxlength="200" placeholder="Deal name eg. Logo design" class="form-control" required>
                        </div>
                        </div>
                        <div class="row">
                        <div class="form-group">
                        <input type="text" name="edit_deal_amount" id="edit_deal_amount" value="' . $row['budget'] . '"  placeholder="Amount eg. 1800" class="form-control" required>
                        </div>
                        <div class="form-group">
                        <input type="number" name="edit_deal_rating" id="edit_deal_rating" min="1" max="5" placeholder="Deal Rating: range 1 to 5" value="' . $row['rating'] . '" class="form-control" required>
                        </div>
                        </div>
                        <div class="row">
                        <div class="form-group">
                        <input type="text" name="edit_deal_project_description" id="edit_deal_project_description" value="' . $row['project_description'] . '"  placeholder="Deal details eg. Logo design for his new website." class="form-control">
                        </div>
                        <div class="form-group">
                        <input type="text" name="edit_deal_deliverables" id="edit_deal_deliverables" value="' . $row['deliverables'] . '"  maxlength="200" placeholder="Deliverables eg. psd, png and related all files of logo in zip format." class="form-control">
                        </div>
                        </div>
                        <div class="row">
                        <div class="form-group">
                        <input type="text" class="edit_deal_proposal_due_date" name="edit_deal_proposal_due_date_' . $row['id'] . '" id="edit_deal_proposal_due_date_' . $row['id'] . '" value="' . $row['proposal_due_date'] . '"  placeholder="Proposal due date, click to select date" class="form-control">
                        </div>
                        </div>
                        <div class="row">
                        <div class="form-group">
                        <input type="submit" class="btn btn-primary" id="edit_deal_submit" value="Update" >
                        <input class="btn btn-secondary" type="reset">
                        </div>
                        </div>
                        </form>
                        </div>
                        </div>
                        </div>
                        </div>
                        </div>
                        </div>
                        <!-- modal end -->';
                    }

                    $final .= '</tbody>
                    </table>Click here to <button type="button" class="btn btn-warning add-new-button" class="nav-link" data-toggle="modal" data-target="#add_new_modal">
					<span>
						<i class="fas fa-fw fa-plus-square"></i>Add</span>
					</button>';
                } else {
                    $final .= 'Click here to <button type="button" class="btn btn-warning add-new-button" class="nav-link" data-toggle="modal" data-target="#add_new_modal">
					<span>
						<i class="fas fa-fw fa-plus-square"></i>Add</span>
					</button>';
                }

                echo $final;
            }
        } catch (\Exception $exception) {
            $this->misc->log('Sales ' . __METHOD__, $exception);
        }
    }

    /**
     * this will fetch contacts for this user
     */
    public function contacts()
    {
        try {
            // only not hide/delete contact will be shown
            $sql = $this->dbConn->prepare("SELECT c.*, a.address, a.address_street1, a.address_street2, a.address_city, a.address_state, a.address_zip, a.address_country
                from contact c
                inner join addresses a on a.id = c.Address_Id
                where c.Sales_Rep = ?
                order by c.id desc");
            $sql->execute(array($this->loggedin_user_id));
            $count = $sql->rowCount();
            $final = [];

            if ($count > 0) {
                while ($row = $sql->fetch()) {
                    $final[] = $row;
                }
            }

            $response = $this->twig->render('admin/sales/contacts.html.twig', ['uri' => $_SERVER['REQUEST_URI'], 'session_user' => isset($_SESSION['user']) ? $_SESSION['user'] : [], 'admin_to_go' => $this->admin_to_go, 'link' => $this->link, 'nav_link' => $this->nav_link, 'user_token' => $_SESSION['user_token'], 'currency' => $this->currency, 'contacts' => $final, 'owner' => $this->owner, 'key' => $_SESSION['csrf'], 'stage' => $this->stage, 'duration_select' => $this->duration_select, 'deals' => $this->deals, 'todo_descs' => $this->todo_descs, 'countries' => $this->countries, 'google_auth_url' => $this->google_auth_url, 'header_totals' => $this->header_totals, 'limits' => $this->limits]);

            return $response;
        } catch (\Exception $exception) {
            $this->misc->log('Sales ' . __METHOD__, $exception);
        }
    }

    /**
     * ajax look up function for contacts
     */
    public function contact_lookup()
    {
        try {
            if ($_SERVER["REQUEST_METHOD"] == "POST") {
                $look_up_characters = $_POST['contact'];

                $loggedin_user_id = $_SESSION['user']['id'];
                $sql = $this->dbConn->prepare("SELECT c.id, c.*
                    FROM contact c
                    inner join users u on u.id = c.Sales_Rep
                    where (c.Contact_Title LIKE ?
                    or c.Contact_First LIKE ?
                    or c.Contact_Middle LIKE ?
                    or c.Contact_Last LIKE ?
                    or c.Email LIKE ?)
                    and c.status = 0
                    and c.Sales_Rep = ? order by c.id desc");
                $sql->execute(array("%$look_up_characters%", "%$look_up_characters%", "%$look_up_characters%", "%$look_up_characters%", "%$look_up_characters%", $loggedin_user_id));
                $count = $sql->rowCount();

                if ($count > 0) {
                    $final = '<select id="contact_select" name="contact_select" class="form-control"><option value="">Select you contact</option>';
                    while ($row = $sql->fetch()) {
                        $details = $row['Contact_First'] . '#' . $row['Email'] . '#' . $row['Company'] . '#' . $row['Phone'] . '#' . $row['Contact_Title'] . '#' . $row['Contact_Middle'] . '#' . $row['Contact_Last'] . '#' . $row['Lead_Referral_Source'] . '#' . $row['Date_of_Initial_Contact'] . '#' . $row['Title'] . '#' . $row['Industry'] . '#' . $row['Website'] . '#' . $row['LinkedIn_Profile'] . '#' . $row['Background_Info'] . '#' . $row['Address_Id'];

                        $final .= '<option value="' . $details . '">' . $row['Contact_First'] . ' (' . $row['Email'] . ' , ' . $row['Company'] . ')</option>';
                    }

                    $final .= '</select>';

                    echo $final;
                } else {
                    echo '';
                }
            }
        } catch (\Exception $exception) {
            $this->misc->log('Sales ' . __METHOD__, $exception);
        }
    }

    /**
     * it will move a deal from one stage to another stage
     */
    public function move_deal()
    {
        try {
            if ($_SERVER["REQUEST_METHOD"] == "POST") {
                $current = $_POST['current'];
                $to_move = $_POST['to_move'];
                $deal_id = $_POST['deal_id'];

                if ($to_move == 'won') {
                    $sql_update = "update deals set active = ? where id = ?";
                    $sql_update = $this->dbConn->prepare($sql_update);
                    $result = $sql_update->execute(array(1, $deal_id));
                } elseif ($to_move == 'lost') {
                    $sql_update = "update deals set active = ? where id = ?";
                    $sql_update = $this->dbConn->prepare($sql_update);
                    $result = $sql_update->execute(array(2, $deal_id));
                } else {
                    $sql_update = $this->dbConn->prepare("update deals set stage = ? where id = ? and stage = ?");
                    $result = $sql_update->execute(array($to_move, $deal_id, $current));
                }

                if ($result) {
                    echo '200';
                    exit;
                } else {
                    echo '400';
                    exit;
                }
            }
        } catch (\Exception $exception) {
            $this->misc->log('Sales ' . __METHOD__, $exception);
        }
    }

    /**
     * ajax feedback after won a deal
     * 0=active
     *   1=won
     * 2=lost
     * 3=delete
     */
    public function deal_won()
    {
        try {
            if ($_SERVER["REQUEST_METHOD"] == "POST") {
                $feedback = $_POST['deal_won_feedback'];
                $current = $_POST['own_deal_stage'];
                $deal_id = $_POST['own_deal_id'];

                $sql = 'insert into deals_feedback (deal_id, type, feedback) values (?, ?, ?)';
                $sql = $this->dbConn->prepare($sql);
                $result = $sql->execute(array($deal_id, 'deal won', $feedback));

                if ($result) {

                    $sql_update = "update deals set active = ? where id = ?";
                    $sql_update = $this->dbConn->prepare($sql_update);
                    $result_update = $sql_update->execute(array(1, $deal_id));

                    if ($result_update) {
                        echo '200';
                        exit;
                    }

                } else {
                    echo '400';
                    exit;
                }
            }
        } catch (\Exception $exception) {
            $this->misc->log('Sales ' . __METHOD__, $exception);
        }
    }

    /**
     * ajax update for deal lost feedback
     */
    public function deal_lost()
    {
        try {
            if ($_SERVER["REQUEST_METHOD"] == "POST") {
                $feedback = $_POST['deal_lost_feedback'];
                $deal_id = $_POST['lost_deal_id'];

                $sql = 'insert into deals_feedback (deal_id, type, feedback) values (?, ?, ?)';
                $sql = $this->dbConn->prepare($sql);
                $result = $sql->execute(array($deal_id, 'deal lost', $feedback));

                if ($result) {
                    $sql_update = "update deals set active = ? where id = ?";
                    $sql_update = $this->dbConn->prepare($sql_update);
                    $result_update = $sql_update->execute(array(2, $deal_id));

                    if ($result_update) {
                        echo '200';
                        exit;
                    }
                } else {

                    echo '400';
                    exit;
                }
            }
        } catch (\Exception $exception) {
            $this->misc->log('Sales ' . __METHOD__, $exception);
        }
    }

    /**
     * ajax data for analytics chart
     */
    public function get_chart_data_for_dashboard()
    {
        try {
            if ($_SERVER["REQUEST_METHOD"] == "POST") {
                if (isset($_POST['analytics_from']) && $_POST['analytics_from'] != '' && isset($_POST['analytics_to']) && $_POST['analytics_to'] != '') {
                    $_SESSION['sales_analytics_from'] = $_POST['analytics_from'];
                    $_SESSION['sales_analytics_to'] = $_POST['analytics_to'];
                } elseif ($_SESSION['sales_analytics_from'] == '' && $_SESSION['sales_analytics_to'] == '') {
                    $from = date("Y-m-d", strtotime(date("Y-m-d", strtotime(date("Y-m-d"))) . "-1 month"));
                    $to = date('Y-m-d');
                    $_SESSION['sales_analytics_from'] = $from;
                    $_SESSION['sales_analytics_to'] = $to;
                }

                $sql = $this->dbConn->prepare('select budget, date(created_at) as created_at, project_type, rating from deals where sales_rep = ? and created_at >= ? and created_at <= DATE_ADD(?, INTERVAL 1 DAY) order by created_at');
                $sql->execute(array($this->loggedin_user_id, $_SESSION['sales_analytics_from'], $_SESSION['sales_analytics_to']));

                $count = $sql->rowCount();
                $temp_result = [];

                if ($count > 0) {
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
            }
        } catch (\Exception $exception) {
            $this->misc->log('Sales ' . __METHOD__, $exception);
        }
    }

    /**
     * this will process the whole analytics reports
     */
    public function analytics()
    {
        try {
            // calling analytics data
            $analytics = new Analytics($this->dbConn, $_SESSION['sales_analytics_from'], $_SESSION['sales_analytics_to']);
            $result = $analytics->getDashboardDetails();
            // p($result);exit;
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

            $response = $this->twig->render('admin/sales/analytics.html.twig', ['uri' => $_SERVER['REQUEST_URI'], 'session_user' => isset($_SESSION['user']) ? $_SESSION['user'] : [], 'admin_to_go' => $this->admin_to_go, 'link' => $this->link, 'nav_link' => $this->nav_link, 'key' => $_SESSION['csrf'], 'user_token' => $_SESSION['user_token'], 'currency' => $this->currency, 'no_of_contacts' => $no_of_contacts, 'no_of_active_deals' => $no_of_active_deals, 'no_of_won_deals' => $no_of_won_deals, 'no_of_lost_deals' => $no_of_lost_deals, 'todo_descs' => $this->todo_descs, 'deals' => $this->deals, 'countries' => $this->countries, 'stage' => $this->stage, 'duration_select' => $this->duration_select, 'owner' => $this->owner, 'from' => $_SESSION['sales_analytics_from'], 'to' => $_SESSION['sales_analytics_to'], 'google_auth_url' => $this->google_auth_url, 'header_totals' => $this->header_totals, 'avg_own_deals' => $avg_own_deals, 'avg_sales_cycle' => $avg_sales_cycle, 'total_value_of_active_deals' => $total_value_of_active_deals, 'won_deals_value' => $won_deals_value, 'avg_won_deals_value' => $avg_won_deals_value, 'conversion_rate' => $conversion_rate, 'top_sales_sources' => $top_sales_sources, 'lost_deal_value' => $lost_deal_value, 'no_of_tasks_to_do' => $all_task_to_do_total, 'task_completed' => $task_completed, 'no_of_tasks' => $all_task_completed_total, 'task_completed_email' => $task_completed_email, 'task_completed_meeting' => $task_completed_meeting, 'task_completed_task' => $task_completed_task, 'task_completed_phone' => $task_completed_phone, 'contacts_bulk_uploaded' => $contacts_bulk_uploaded, 'google_calendar_push' => $google_calendar_push]);

            return $response;
        } catch (\Exception $exception) {
            $this->misc->log('Sales ' . __METHOD__, $exception);
        }
    }

    /**
     * ajax call to fetch analytics report of a date range
     */
    public function get_analytics_date_range()
    {
        try {
            if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['csrf']) && $_POST['csrf'] == $_SESSION['csrf']) {

                $from = date('Y-m-d', strtotime($_POST['sales_analytics_from']));
                $to = date('Y-m-d', strtotime($_POST['sales_analytics_to']));
                $_SESSION['sales_analytics_from'] = $from;
                $_SESSION['sales_analytics_to'] = $to;

                // calling analytics data
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

                if (!empty($task_to_do) && isset($task_to_do['all_total'])) {
                    $all_task_to_do_total = $task_to_do['all_total'];
                }

                if (!empty($task_completed) && isset($task_completed['all_total'])) {
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

                $response = $this->twig->render('admin/sales/analytics.html.twig', ['uri' => $_SERVER['REQUEST_URI'], 'session_user' => isset($_SESSION['user']) ? $_SESSION['user'] : [], 'admin_to_go' => $this->admin_to_go, 'link' => $this->link, 'nav_link' => $this->nav_link, 'key' => $_SESSION['csrf'], 'user_token' => $_SESSION['user_token'], 'currency' => $this->currency, 'no_of_contacts' => $no_of_contacts, 'no_of_active_deals' => $no_of_active_deals, 'no_of_won_deals' => $no_of_won_deals, 'no_of_lost_deals' => $no_of_lost_deals, 'todo_descs' => $this->todo_descs, 'deals' => $this->deals, 'countries' => $this->countries, 'stage' => $this->stage, 'duration_select' => $this->duration_select, 'owner' => $this->owner, 'from' => $from, 'to' => $to, 'google_auth_url' => $this->google_auth_url, 'header_totals' => $this->header_totals, 'avg_own_deals' => $avg_own_deals, 'avg_sales_cycle' => $avg_sales_cycle, 'total_value_of_active_deals' => $total_value_of_active_deals, 'won_deals_value' => $won_deals_value, 'avg_won_deals_value' => $avg_won_deals_value, 'conversion_rate' => $conversion_rate, 'top_sales_sources' => $top_sales_sources, 'lost_deal_value' => $lost_deal_value, 'no_of_tasks_to_do' => $all_task_to_do_total, 'task_completed' => $task_completed, 'no_of_tasks' => $all_task_completed_total, 'task_completed_email' => $task_completed_email, 'task_completed_meeting' => $task_completed_meeting, 'task_completed_task' => $task_completed_task, 'task_completed_phone' => $task_completed_phone, 'contacts_bulk_uploaded' => $contacts_bulk_uploaded, 'google_calendar_push' => $google_calendar_push]);

                return $response;
            }
        } catch (\Exception $exception) {
            $this->misc->log('Sales ' . __METHOD__, $exception);
        }
    }

    /**
     * this ajax call will add a deal
     * // $_POST Array (
     * [contact_lookup] => Tim
     * [contact_select] => Tim#tim@levis.com#Levis#(321) 321-4321###Smith##2014-10-10#Supply Chain Manager#Apparel#www.levis.com#www.sample.com#Jeans and apparel for eastern U.S.######6
     * [email] => tim@levis.com
     * [phone] => (321) 321-4321
     * [company] => Levis
     * [deal] => Just Started
     * [amount] => 1800
     * [rating] => 5
     * [project_description] => This is a just a description test for this deal
     * [deliverables] => It need to deliver with full details
     * [proposal_due_date] => 09/30/2019
     * [status] => 29
     * [owner] => 6 )
     */
    public function add_deal()
    {
        try {
            if ($_SERVER["REQUEST_METHOD"] == "POST" && $_SESSION['csrf'] == $_POST['csrf']) {
                $contact = explode('#', $_POST['email']);
                $email = $_POST['email'];
                $phone = $_POST['phone'];
                $company = $_POST['company'];
                $project_type = $_POST['deal'];
                $budget = $_POST['amount'];
                $rating = $_POST['rating'];
                $stage = $_POST['stage'];
                $project_description = $_POST['project_description'];
                $proposal_due_date = $_POST['proposal_due_date'];
                $date = explode('/', $proposal_due_date);
                $proposal_due_date = $date[2] . $date[0] . $date[1];
                $deliverables = $_POST['deliverables'];
                $owner = $_SESSION['user']['id'];
                $first_name = $contact[0];

                // need to check the company deal limits
                $sql = $this->dbConn->prepare("select count(d.id) as total from deals d
                    inner join users u on u.id = d.sales_rep
                    where u.registration_id = ? limit 1");
                $sql->execute(array($this->company_id));
                $count = $sql->rowCount();
                if ($count > 0) {
                    while ($row_check = $sql->fetch()) {
                        // limit checking
                        if ($row_check['total'] < $_SESSION['user']['deals_limit']) {
                            // this contact's sales rep must be from same company
                            $sql_contact = $this->dbConn->prepare("select c.* from contact c
							inner join users u on c.Sales_Rep = u.id
								where c.Email = ? and u.registration_id = ? limit 1");

                            $sql_contact->execute(array($email, $this->company_id));
                            $count_contact = $sql_contact->rowCount();

                            if ($count_contact > 0) {
                                while ($row_contact = $sql_contact->fetch()) {
                                    $sql_insert = $this->dbConn->prepare("insert into deals (
                                        contact_id,
                                        stage,
                                        sales_rep,
                                        rating,
                                        project_type,
                                        project_description,
                                        proposal_due_date,
                                        budget,
                                        deliverables)
                                        values (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                                    $result = $sql_insert->execute(array($row_contact['id'], $stage, $row_contact['Sales_Rep'], $rating, $project_type, $project_description, $proposal_due_date, $budget, $deliverables));
                                    break;
                                }

                                echo '200';
                                exit;
                            }

                            echo '400';
                            exit;
                        } else {
                            echo '409';
                            exit;
                        }
                    }
                }
            }

            echo '409';
            exit;
        } catch (\Exception $exception) {
            $this->misc->log('Sales ' . __METHOD__, $exception);
        }
    }

    // /**
    //  * this will edit a deal
    //  * Array
    //  *   (
    //  *       [edit_deal_change] => edit
    //  *       [edit_deal_id] => 18
    //  *       [original_values] => edit_deal_stage=52&edit_deal_contact_lookup=Naomi&edit_deal_email=naomir@gmail.com&edit_deal_phone=53534545&edit_deal_company=Tesla Fake&edit_deal_deal=Provide a beautiful birthday cake&edit_deal_amount=200.00&edit_deal_rating=3.00&edit_deal_project_description=Chocolate Cake &edit_deal_deliverables=cake&edit_deal_proposal_due_date=2019-11-30
    //         [edit_deal_stage] => 52
    //         [edit_deal_contact_lookup] => Naomi
    //         [edit_deal_email] => naomir@gmail.com
    //         [edit_deal_phone] => 53534545
    //         [edit_deal_company] => Tesla Fake
    //         [edit_deal_deal] => Provide a beautiful birthday cake
    //         [edit_deal_amount] => 4000
    //         [edit_deal_rating] => 4
    //         [edit_deal_project_description] => Chocolate Cake
    //         [edit_deal_deliverables] => cake
    //         [edit_deal_proposal_due_date_other] => 12/11/2019
    //     )

    //     0=active
    //     1=won
    //     2=lost
    //     3=delete

    //  * @return [type] [description]
    //  */
    public function edit_deal()
    {
        try {
            if ($_SERVER["REQUEST_METHOD"] == "POST" && $_SESSION['csrf'] == $_POST['csrf_deal_edit']) {
                $edit_deal_change = $_POST['edit_deal_change'];
                $edit_deal_id = $_POST['edit_deal_id'];

                // $date = explode('/', $edit_deal_proposal_due_date);
                // $edit_deal_proposal_due_date = $date[2] . $date[0] . $date[1];
                $edit_deal_owner = $this->loggedin_user_id;

                if ($edit_deal_change == 'delete') {
                    // update code for deal starting
                    $sql_deal = "update deals
                    set active = ?
                    where id = ?";

                    $sql_deal = $this->dbConn->prepare($sql_deal);
                    $result_deal_result = $sql_deal->execute(array(DEAL_DELETE, $edit_deal_id));

                    if ($result_deal_result) {
                        echo '200';
                        exit;
                    } else {
                        echo '400';
                        exit;
                    }
                } elseif ($edit_deal_change == 'edit') {

                    $edit_deal_stage = $_POST['edit_deal_stage'];
                    // $edit_deal_contact_lookup = $_POST['edit_deal_contact_lookup'];
                    // $edit_deal_email = $_POST['edit_deal_email'];
                    // $edit_deal_phone = $_POST['edit_deal_phone'];
                    // $edit_deal_company = $_POST['edit_deal_company'];
                    $edit_deal_deal = $_POST['edit_deal_deal'];
                    $edit_deal_amount = $_POST['edit_deal_amount'];
                    $edit_deal_rating = $_POST['edit_deal_rating'];
                    $edit_deal_project_description = $_POST['edit_deal_project_description'];
                    $edit_deal_deliverables = $_POST['edit_deal_deliverables'];
                    $edit_deal_proposal_due_date = '';
                    if (isset($_POST['edit_deal_proposal_due_date_' . $edit_deal_id]) && $_POST['edit_deal_proposal_due_date_' . $edit_deal_id] != '0000-00-00') {
                        $edit_deal_proposal_due_date = $_POST['edit_deal_proposal_due_date_' . $edit_deal_id];
                    } elseif (isset($_POST['edit_deal_proposal_due_date_other_' . $edit_deal_id]) && $_POST['edit_deal_proposal_due_date_other_' . $edit_deal_id] != '0000-00-00') {
                        $edit_deal_proposal_due_date = $_POST['edit_deal_proposal_due_date_other_' . $edit_deal_id];
                    }

                    if ($edit_deal_proposal_due_date != '') {
                        // $dates = explode('/', $edit_deal_proposal_due_date);
                        // $edit_deal_proposal_due_date = $dates[2] . '-' . $dates[0] . '-' . $dates[1];

                        // update code for deal starting
                        $sql_deal = "update deals
                        set stage = ?,
                        rating = ?,
                        project_type = ?,
                        project_description = ?,
                        proposal_due_date = ?,
                        budget = ?,
                        deliverables = ?
                        where id = ?";

                        $sql_deal = $this->dbConn->prepare($sql_deal);
                        $result_deal_result = $sql_deal->execute(array($edit_deal_stage, $edit_deal_rating, $edit_deal_deal, $edit_deal_project_description, date('Y-m-d H:i:s', strtotime($edit_deal_proposal_due_date)), $edit_deal_amount, $edit_deal_deliverables, $edit_deal_id));

                    } else {
                        echo '409';
                        exit;
                    }

                    if ($result_deal_result) {
                        echo '200';
                        exit;
                    } else {
                        echo '400';
                        exit;
                    }
                }
            }
        } catch (\Exception $exception) {
            $this->misc->log('Sales ' . __METHOD__, $exception);
        }
    }

    /**
     * this ajax will add a todo
     * // Array
     * (
     *       [stage] => 29
     *       [todo_desc] => Array
     *           (
     *               [0] => 1
     *               [1] => 2
     *               [2] => 4
     *           )
     *
     *       [todo_detail] => Actual to do test
     *       [todo_due_date] => 10/09/2019
     *       [todo_due_time] => 11:00
     *       [duration] => 00:30
     *       [deal] => 14
     *       [owner] => 6
     *   )
     */
    public function add_todo()
    {
        try {
            if ($_SERVER["REQUEST_METHOD"] == "POST" && $_SESSION['csrf'] == $_POST['csrf']) {
                $stage = $_POST['stage'];
                $todo_desc = $_POST['todo_desc'];
                $todo_detail = $_POST['todo_detail'];
                $todo_due_date = $_POST['todo_due_date'];
                $date = \DateTime::createFromFormat('m/d/Y', $todo_due_date);
                $temp_due_date = $date->format("Y-m-d");
                $todo_due_time = $_POST['todo_due_time'];
                $temp_duration = $_POST['duration'];
                $duration_details = explode(':', $temp_duration);
                $duration = $duration_details[0] * 60 + $duration_details[1];
                $todo_due_date_details = $todo_due_date . ' ' . date("g:i a", strtotime($todo_due_time)) . ' to ' . date("g:i a", strtotime($todo_due_time . "+{$duration} minutes"));
                $deal = $_POST['deal'];
                $owner = $_SESSION['user']['id'];

                foreach ($todo_desc as $todo) {
                    $sql_insert = $this->dbConn->prepare("insert into notes (Date,
                        Notes,
                        Is_New_Todo,
                        Todo_Desc_Id,
                        Todo_Due_Date,
                        due_date,
                        start_time,
                        duration,
                        Deal,
                        Task_Status,
                        Task_Update,
                        Sales_Rep) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $result = $sql_insert->execute(array(date("Y-m-d"), $todo_detail, 1, $todo, $todo_due_date_details, $temp_due_date, $todo_due_time, $temp_duration, $deal, 1,
                        '', $owner));
                }

                if ($result) {
                    echo '200';
                    exit;
                }

                echo '400';
                exit;
            }
        } catch (\Exception $exception) {
            $this->misc->log('Sales ' . __METHOD__, $exception);
        }
    }

    /**
     * this ajax will add a contact
     *
     * // Array
     *    (
     *       [stage] => 29
     *       [title] =>
     *       [first] => Gutetn
     *       [last] => Barg
     *       [email] => guten@barg.com
     *       [phone] => 123456789
     *       [company] => Book Printing
     *       [lead_referral_source] => google
     *       [designation] => Owner
     *       [industry] => Publishing
     *       [website] => www.gutenbarg.com
     *       [background] =>
     *       [address1] => test address
     *       [street] => test street
     *       [city] => Berlin
     *       [state] => Berlin
     *       [zip] => 23445
     *       [country] => 81
     *       [owner] => 6
     *   )
     */
    public function add_contact()
    {
        try {
            $result = '400';

            if ($_SERVER["REQUEST_METHOD"] == "POST" && $_SESSION['csrf'] == $_POST['csrf']) {
                $title = $_POST['title'];
                $first_name = $_POST['first'];
                $last_name = $_POST['last'];
                $email = $_POST['email'];
                $phone = $_POST['phone'];
                $company = $_POST['company'];
                $lead_referral_source = $_POST['lead_referral_source'];
                $designation = $_POST['designation'];
                $industry = $_POST['industry'];
                $website = $_POST['website'];
                $background = $_POST['background'];
                $address1 = $_POST['address1'];
                $street = $_POST['street'];
                $city = $_POST['city'];
                $state = $_POST['state'];
                $zip = $_POST['zip'] != '' ? $_POST['zip'] : 0;
                $country = $_POST['country'];
                $owner = $_SESSION['user']['id'];

                // check contacts within the company limit
                $sql = $this->dbConn->prepare("select count(c.id) as total from contact c
                    inner join users u on u.id = c.Sales_Rep
                    where u.registration_id = ? limit 1");
                $sql->execute(array($this->company_id));
                $count = $sql->rowCount();
                if ($count > 0) {
                    while ($row = $sql->fetch()) {
                        // if the contacts alreaday have for the whole company less then can add
                        if ($row['total'] < $_SESSION['user']['contacts_limit']) {

                            // check contact is already there or not by email
                            $sql = $this->dbConn->prepare("select * from contact where email = ?");
                            $sql->execute(array($email));
                            $count_email = $sql->rowCount();

                            if ($count_email == 0) {
                                $sql_insert = $this->dbConn->prepare("insert into addresses (address,
                                    address_street1,
                                    address_city,
                                    address_state,
                                    address_zip,
                                    address_country) values (?, ?, ?, ?, ?, ?)");
                                $sql_insert->execute(array($address1, $street, $city, $state, $zip, $country));
                                $address_id = $this->dbConn->lastInsertId();

                                $sql_insert = $this->dbConn->prepare("insert into contact (Contact_Title,
                                    Contact_First,
                                    Contact_Last,
                                    Lead_Referral_Source,
                                    Date_of_Initial_Contact,
                                    Title,
                                    Company,
                                    Industry,
                                    Phone,
                                    Email,
                                    Website,
                                    Address_Id,
                                    Sales_Rep,
                                    Background_Info) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                                $result = $sql_insert->execute(array($title, $first_name, $last_name, $lead_referral_source, date("Y-m-d"), $designation, $company, $industry, $phone, $email, $website, $address_id, $this->loggedin_user_id, $background));

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
                        } else {
                            echo '409';
                            exit;
                        }
                    }
                }

                echo '409';
                exit;
            }
        } catch (\Exception $exception) {
            $this->misc->log('Sales ' . __METHOD__, $exception);
        }
    }

    /**
     * this ajax will edit a contact including delete
     *
     * // Array
     *    (
     *       [stage] => 29
     *       [title] =>
     *       [first] => Gutetn
     *       [last] => Barg
     *       [email] => guten@barg.com
     *       [phone] => 123456789
     *       [company] => Book Printing
     *       [lead_referral_source] => google
     *       [designation] => Owner
     *       [industry] => Publishing
     *       [website] => www.gutenbarg.com
     *       [background] =>
     *       [address1] => test address
     *       [street] => test street
     *       [city] => Berlin
     *       [state] => Berlin
     *       [zip] => 23445
     *       [country] => 81
     *       [owner] => 6
     *   )
     */
    public function edit_contact()
    {
        try {
            if ($_SERVER["REQUEST_METHOD"] == "POST" && $_SESSION['csrf'] == $_POST['csrf_contact_edit']) {

                $id = $_POST['edit_contact_id'];
                $address_id = $_POST['edit_contact_address_id'];

                if ($_POST['edit'] == 'update') {

                    $title = $_POST['title'];
                    $first_name = $_POST['first'];
                    $last_name = $_POST['last'];
                    $email = $_POST['email'];
                    $phone = $_POST['phone'];
                    $company = $_POST['company'];
                    $lead_referral_source = $_POST['lead_referral_source'];
                    $designation = $_POST['designation'];
                    $industry = $_POST['industry'];
                    $website = $_POST['website'];
                    $background = $_POST['background'];
                    $address1 = $_POST['address1'];
                    $street = $_POST['street'];
                    $city = $_POST['city'];
                    $state = $_POST['state'];
                    $zip = $_POST['zip'] != '' ? $_POST['zip'] : 0;
                    $country = $_POST['country'];
                    $owner = $this->loggedin_user_id;

                    // first need to update the address for this contact
                    $sql_update = $this->dbConn->prepare("update addresses set address = ?,
                        address_street1 = ?,
                        address_city = ?,
                        address_state = ?,
                        address_zip = ?,
                        address_country = ? where id = ?");
                    $sql_update->execute(array($address1, $street, $city, $state, $zip, $country, $address_id));

                    // now need to update the contact details
                    $sql_update = $this->dbConn->prepare("update contact set Contact_Title = ?,
                        Contact_First = ?,
                        Contact_Last = ?,
                        Lead_Referral_Source = ?,
                        Title = ?,
                        Company = ?,
                        Industry = ?,
                        Phone = ?,
                        Email = ?,
                        Website = ?,
                        Sales_Rep = ?,
                        Background_Info = ? where id = ?");
                    $result = $sql_update->execute(array($title, $first_name, $last_name, $lead_referral_source, $designation, $company, $industry, $phone, $email, $website, $owner, $background, $id));

                    if ($result) {
                        echo '200';
                        exit;
                    } else {
                        echo '400';
                        exit;
                    }

                } elseif ($_POST['edit'] == 'delete') {
                    // check contact is already there with any deal or not
                    $sql = $this->dbConn->prepare("select id from deals where contact_id = ?");
                    $sql->execute(array($id));
                    $count = $sql->rowCount();

                    if ($count == 0) {
                        // delete contact address first
                        $sql_delete = $this->dbConn->prepare("delete from contact where id = ?");
                        $result = $sql_delete->execute(array($id));

                        // update contact as deleted 1
                        // $sql_delete = $this->dbConn->prepare("update contact set status = ? where id = ?");
                        // $result = $sql_delete->execute(array(1, $id));

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

                } elseif ($_POST['edit'] == 'unhide') {

                    // delete contact address first
                    // $sql_delete_address = $this->dbConn->prepare("delete from addresses where id = ?");
                    // $address_result = $sql_delete_address->execute(array($address_id));

                    // update contact as unhide 0
                    $sql_delete = $this->dbConn->prepare("update contact set status = ? where id = ?");
                    $result = $sql_delete->execute(array(0, $id));

                    if ($result) {
                        echo '200';
                        exit;
                    } else {
                        echo '400';
                        exit;
                    }

                }

            } else {
                echo '400';
                exit;
            }
        } catch (\Exception $exception) {
            $this->misc->log('Sales ' . __METHOD__, $exception);
        }
    }

    /**
     * this method will fetch all task to do and create notifications to push and show
     */
    public function show_notification()
    {
        try {
            if ($_SERVER["REQUEST_METHOD"] == "POST") {
                // tasks later also need to take consideration the due date
                $sql_tasks = $this->dbConn->prepare("SELECT n.id as nid, n.Notes, n.Date, n.todo_desc_id, n.task_status, n.Task_Update, n.Is_New_Todo,
                    n.sales_rep, n.todo_due_date, u.Name_First, u.Name_Last, c.Contact_First,
                    c.Contact_Last, c.Company, c.Email, s.status as task_status,
                    td.description as todo_description, p.deal_stage, d.id,
                    d.sales_rep, d.project_type, u.Name_First, u.Name_Last
                    FROM notes n
                    inner join users u on u.id = n.sales_rep
                    inner join deals d on d.id = n.Deal
                    inner join contact c on c.id = d.contact_id
                    inner join task_status s on s.id = n.task_status
                    inner join todo_desc td on td.id = n.todo_desc_id
                    inner join user_pipeline p on p.id = d.stage
                    group by n.ID
                    having n.sales_rep = ?
                    and n.Task_Status = ?
                    and n.Is_New_Todo = ?
                    order by d.id desc");
                $sql_tasks->execute(array($this->loggedin_user_id, 1, 1));
                $count = $sql_tasks->rowCount();
                $result = '';
                $ids = [];
                if ($count > 0) {
                    while ($row = $sql_tasks->fetch()) {
                        $ids[] = $row['nid'];
                        $result .= '<div class="toast fade show" style="position: absolute; top: 0; right: 0;">
                        <div class="toast" role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="toast-header">
                        <img src="/images/logo.png" width="20" height="20" class="rounded mr-2" alt="Esy CRM">
                        <strong class="mr-auto">Notification</strong>
                        <small>' . $row['todo_due_date'] . '</small>
                        <button type="button" class="ml-2 mb-1 close" data-dismiss="toast" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                        </button>
                        </div>
                        <div class="toast-body">' . $row['Contact_First'] . ' ' . $row['Contact_Last'] . ': ' . $row['Notes'] . ' ' . $row['todo_description'] . ' ' . $row['Task_Update'] . '</div>
                        </div>
                        </div>';
                    }

                    // PDO do not take direct array or implode into IN
                    $in = str_repeat('?,', count($ids) - 1) . '?';
                    // update the notification to read
                    // $this->dbConn->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_WARNING );
                    $sql_update = $this->dbConn->prepare("update notes set Is_New_Todo = ? where id IN( " . $in . " )");
                    $params = array_merge(array(2), $ids);
                    $sql_update->execute($params);
                }

                echo $result;
                exit;
            }
        } catch (\Exception $exception) {
            $this->misc->log('Sales ' . __METHOD__, $exception);
        }
    }

    /**
     * this will ajax process to save bulk contacts from csv file uploaded by user
     *
     * Array ( [0] => Contact_Title [1] => Contact_First [2] => Contact_Middle [3] => Contact_Last [4] => Lead_Referral_Source [5] => Date_of_Initial_Contact [6] => Title [7] => Company [8] => Industry [9] => Phone [10] => Email [11] => Website [12] => LinkedIn_Profile [13] => Background_Info [14] => address [15] => address_street1 [16] => address_street2 [17] => address_city [18] => address_state [19] => address_zip [20] => address_country )
     */
    public function contacts_csv_upload()
    {
        try {
            if ($_SERVER["REQUEST_METHOD"] == "POST" && $_SESSION['csrf'] == $_POST['csrf']) {
                $fileName = $_FILES["csv_import"]["tmp_name"];

                if ($_FILES["csv_import"]["size"] > 0) {
                    $file = fopen($fileName, "r");

                    // check how many contacts have right now and can upload more
                    $total_contacts = $_SESSION['user']['header_totals']['contacts'];
                    // a company has a limit how many contacts can keep max
                    $more_can_upload = $_SESSION['user']['contacts_limit'] - $total_contacts;

                    if ($more_can_upload > 0) {
                        $result = false;
                        $row = 1;
                        while (($column = fgetcsv($file, 10000, ",")) !== false && $more_can_upload > 0) {
                            // header row
                            if ($row > 1) {
                                try {
                                    // check that same contact already in db for this sales rep or not
                                    $sql = $this->dbConn->prepare("select * from contact where Contact_First = ? and Contact_Last = ? and Email = ? and Sales_Rep = ?");
                                    $sql->execute(array($column[1], $column[2], $column[3], $this->loggedin_user_id));
                                    $count = $sql->rowCount();

                                    if ($count == 0) {
                                        // transaction begins
                                        $this->dbConn->beginTransaction();
                                        // to save addresses
                                        $address = [
                                            isset($column[12]) && $column[12] != null ? $column[12] : '',
                                            isset($column[13]) && $column[13] != null ? $column[13] : '',
                                            isset($column[14]) && $column[14] != null ? $column[14] : '',
                                            isset($column[15]) && $column[15] != null ? $column[15] : '',
                                            isset($column[16]) && $column[16] != null ? $column[16] : '',
                                            isset($column[17]) && $column[17] != null ? $column[17] : 0,
                                            isset($column[18]) && $column[18] != null ? $column[18] : '',
                                        ];

                                        // divides the data for addresses and contacts one by another
                                        $sql_address = $this->dbConn->prepare("insert into addresses (address, address_street1, address_street2, address_city, address_state, address_zip, address_country) values (?, ?, ?, ?, ?, ?, ?)");

                                        $sql_address->execute($address);
                                        $address_id = $this->dbConn->lastInsertId();

                                        $contact = [
                                            isset($column[0]) ? $column[0] : '',
                                            isset($column[1]) ? $column[1] : '',
                                            isset($column[2]) ? $column[2] : '',
                                            isset($column[3]) ? $column[3] : '',
                                            isset($column[4]) ? $column[4] : '',
                                            isset($column[5]) ? $column[5] : '',
                                            isset($column[6]) ? $column[6] : '',
                                            isset($column[7]) ? $column[7] : '',
                                            isset($column[8]) ? $column[8] : '',
                                            isset($column[9]) ? $column[9] : '',
                                            isset($column[10]) ? $column[10] : '',
                                            isset($column[11]) ? $column[11] : '',
                                            $address_id,
                                            $this->loggedin_user_id,
                                            date('Y-m-d'),
                                            1, // source as bulk csv upload
                                        ];

                                        // contact details
                                        $sql_contact = $this->dbConn->prepare("insert into contact (Contact_Title, Contact_First, Contact_Last, Email, Phone, Company, Lead_Referral_Source, Title, Industry, Website, LinkedIn_Profile, Background_Info, Address_Id, Sales_Rep, Date_of_Initial_Contact, source) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                                        $result = $sql_contact->execute($contact);

                                        // complete both transaction
                                        $this->dbConn->commit();

                                        $more_can_upload--;
                                    }
                                } //Our catch block will handle any exceptions that are thrown.
                                 catch (\Exception $e) {
                                    //An exception has occurred, which means that one of our database queries
                                    //failed.
                                    //Print out the error message.
                                    echo $e->getMessage();
                                    //Rollback the transaction.
                                    $this->dbConn->rollBack();
                                }
                            }

                            $row++;
                        }
                    } else {
                        echo '409';
                        exit;
                    }
                }

                if ($result) {
                    echo '200';
                    exit;
                }

                echo '400';
                exit;
            }
        } catch (\Exception $exception) {
            $this->misc->log('Sales ' . __METHOD__, $exception);
        }
    }

    /**
     * this will connect with gmail
     */
    public function connect_gmail()
    {
        try {
            if (isset($_GET['code'])) {
                // Get the API client and construct the service object.
                $token = $this->gmailApiObj->GetAccessToken(
                    GMAIL_OAUTH_CLIENT_ID,
                    $this->gmail_redirect_url,
                    GMAIL_OAUTH_CLIENT_SECRET,
                    $_GET['code']
                );

                $client = $this->gmailApiObj->getClient($token);
                $this->gmail_service = new Google_Service_Gmail($client);
                $this->user_profile = $this->gmailApiObj->getUserProfile($this->gmail_service);
                setcookie('gmail_id', base64_encode($this->user_profile->emailAddress), time() + COOKIE_SET_TIME, "/");

                // save the token into db for this user
                $sql_token = $this->dbConn->prepare("update user_access_tokens
                set google_gmail_token = ?, email = ? where user_id = ?");
                $sql_token->execute(array(json_encode($token), $this->user_profile->emailAddress, $this->loggedin_user_id));

                if ($_SESSION['user']['google_gmail_token'] == '') {
                    $_SESSION['user']['google_gmail_token'] = json_encode($token);
                }
                // set the gmail api
                $this->gmail_auth_url = '';
                // set gmail client and create service
                $client = $this->gmailApiObj->getClient($_SESSION['user']['google_gmail_token']);
                $this->gmail_service = new Google_Service_Gmail($client);

                header('location: /sales');
                exit;
            } else {
                return false;
            }
        } catch (\Exception $exception) {
            $this->misc->log('Sales ' . __METHOD__, $exception);
        }
    }

    /**
     * ajax process to send mail from task via user's gmail account
     * Array
     *   (
     *       [csrf] => c201e0da6d0a400035b0a2a02d380b9f258c31b7
     *       [email_task_id] => 9
     *       [task_email_to] => dhiraj.patra@gmail.com
     *       [task_email_subject] => testing from task by gmail
     *       [task_email_body] => dhiraj.patra@gmail.com
     *   )
     */
    public function send_task_email()
    {
        try {
            if ($_SERVER["REQUEST_METHOD"] == "POST" && $_SESSION['csrf'] == $_POST['csrf']) {
                // get user's email
                if (!isset($_COOKIE['gmail_id']) || $_COOKIE['gmail_id'] == '') {
                    $sql = $this->dbConn->prepare('select * from user_access_tokens where user_id = ? limit 1');
                    $sql->execute(array($this->loggedin_user_id));
                    $count = $sql->rowCount();

                    if ($count > 0) {
                        while ($row = $sql->fetch()) {
                            if ($row['email'] != null) {
                                setcookie('gmail_id', base64_encode($row['email']), time() + COOKIE_SET_TIME, "/");
                            } else {
                                $this->user_profile = $this->gmailApiObj->getUserProfile($this->gmail_service);
                                // update database
                                $sql_token = $this->dbConn->prepare("update user_access_tokens
                set email = ? where user_id = ?");
                                $sql_token->execute(array($this->user_profile->emailAddress, $this->loggedin_user_id));
                            }
                            break;
                        }
                    } else {
                        echo '409';
                        exit;
                    }
                } else {
                    // fetch all labels
                    // Print the labels in the user's account.
                    $user = isset($_COOKIE['gmail_id']) ? base64_decode($_COOKIE['gmail_id']) : null;
                    if ($user != null) {
                        // existing labels from user's gmail
                        $labels_obj = $this->gmailApiObj->listLabels($this->gmail_service, $user);
                        $labels = [];
                        foreach ($labels_obj as $label) {
                            $labels[$label->id] = $label->name;
                        }

                        // get deal and task data
                        $sql = $this->dbConn->prepare("SELECT n.id as nid, n.Notes, n.Date, n.todo_desc_id, n.task_status, n.Task_Update,
                        n.sales_rep, n.todo_due_date, n.due_date, n.start_time, n.duration,
                        u.Name_First, u.Name_Last,
                        c.Contact_First, c.Contact_Last, c.Company, c.Email,
                        s.status as task_status,
                        td.description as todo_description, p.deal_stage, d.id as did,
                        d.sales_rep, d.project_type, u.Name_First, u.Name_Last, u.login_with_code
                        FROM notes n
                        inner join users u on u.id = n.sales_rep
                        inner join deals d on d.id = n.Deal
                        inner join contact c on c.id = d.contact_id
                        inner join task_status s on s.id = n.task_status
                        inner join todo_desc td on td.id = n.todo_desc_id
                        inner join user_pipeline p on p.id = d.stage
                        where n.id = ? limit 1");
                        $sql->execute(array($_POST['email_task_id']));
                        $count = $sql->rowCount();

                        if ($count > 0) {
                            // check and crate top level label in gmail
                            foreach ($this->all_task_status as $status) {
                                $top_label = ucwords(preg_replace('/\s+/', '', $status['status']));

                                // creating label as task status
                                if (!in_array($top_label, $labels)) {
                                    $bg_color = null;
                                    switch ($top_label) {
                                        case 'Pending':
                                            $bg_color = '#ffad47';
                                            break;
                                        case 'Working':
                                            $bg_color = '#89d3b2';
                                            break;
                                        case 'Onhold':
                                            $bg_color = '#fb4c2f';
                                            break;
                                        case 'Idea':
                                            $bg_color = '#662e37';
                                            break;
                                        case 'Completed':
                                            $bg_color = '#149e60';
                                            break;
                                    }

                                    $this->gmailApiObj->createLabel($this->gmail_service, $user, $top_label, $bg_color);
                                }
                            }

                            // create sub label as Deal
                            while ($row = $sql->fetch()) {
                                $temp_label = ucwords(preg_replace('/\s+/', '', $row['task_status']));
                                $temp_sub_label = $temp_label . '/' . ucwords($row['project_type']) . '_' . $row['did'];

                                // creating sub label as deal name and id under label or task status
                                if (!in_array($temp_sub_label, $labels)) {
                                    $label_id = $this->gmailApiObj->createLabel($this->gmail_service, $user, $temp_sub_label);
                                }
                                break;
                            }
                        }

                        // get latest labels from user's gmail
                        $labels_obj = $this->gmailApiObj->listLabels($this->gmail_service, $user);
                        $labels = [];
                        foreach ($labels_obj as $label) {
                            $labels[$label->id] = $label->name;
                        }

                        // send mail later we can make a sechudle send as well
                        // get label id for Deal label on which this email will be set
                        if (!isset($label_id) || !isset($label_id_sub)) {
                            // for task status label
                            $label_id = array_search($temp_label, $labels);
                            // for deal name and id label
                            $label_id_sub = array_search($temp_sub_label, $labels);
                        }

                        // create mime message along with headers
                        $mime_message = $this->gmailApiObj->createMessage($user, $_POST['task_email_to'], $_POST['task_email_subject'], $_POST['task_email_body']);
                        // send mail
                        $message = $this->gmailApiObj->sendMessage($this->gmail_service, $user, $mime_message, $label_id_sub);
                        // modify label of sent mail with deal label
                        $labels_to_add = [$label_id, $label_id_sub];
                        $this->gmailApiObj->modifyMessage($this->gmail_service, $user, $message->id, $labels_to_add, ['Sent']);

                        echo '200';
                        exit;
                    } else {
                        echo '409';
                        exit;
                    }
                }

                echo '200';
                exit;

            } else {
                echo '409';
                exit;
            }
        } catch (\Exception $exception) {
            $this->misc->log('Sales ' . __METHOD__, $exception);
        }
    }

    /**
     * this will connect with google calendar
     */
    public function connect_google_calendar()
    {
        try {
            if (isset($_GET['code'])) {
                // get all notes and tasks which is not saved into google calendar yet
                $sql = $this->dbConn->prepare('select * from notes where Task_Status = ?
                and google_calendar_update = ? and Sales_Rep = ?');
                $sql->execute(array(1, 0, $this->loggedin_user_id));
                $count = $sql->rowCount();

                if ($count > 0) {
                    // Access Token
                    $tokenPath = $_SERVER["DOCUMENT_ROOT"] . '/../' . 'google_calendar_token.json';
                    if (file_exists($tokenPath)) {
                        $accessToken = json_decode(file_get_contents($tokenPath), true);
                    }
                    if (!isset($accessToken) && empty($_SESSION['google_access_token'])) {
                        // Get the API client and construct the service object.
                        $data = $this->googleCalendarApiObj->GetAccessToken(GOOGLE_OAUTH_CLIENT_ID, $this->google_redirect_url, GOOGLE_OAUTH_CLIENT_SECRET, $_GET['code']);

                        // Save the access token as a session variable
                        $_SESSION['google_access_token'] = $data['access_token'];
                    } else {
                        // Save the token to a file.
                        if (!file_exists(dirname($tokenPath))) {
                            mkdir(dirname($tokenPath), 0700, true);
                        }
                        file_put_contents($tokenPath, json_encode($accessToken));
                        $_SESSION['google_access_token'] = $accessToken;
                    }

                    // to update db
                    $ids = [];

                    while ($row = $sql->fetch()) {

                        // Create event on primary calendar
                        $day = mb_substr($row['Todo_Due_Date'], 0, 10);
                        $dd = date("Y-m-d", strtotime($day));
                        $time = explode('to', mb_substr($row['Todo_Due_Date'], 11));
                        $start_time = $dd . 'T' . date("H:i:s", strtotime(trim($time[0])));
                        $end_time = $dd . 'T' . date("H:i:s", strtotime(trim($time[1])));

                        // Get user calendar timezone
                        $user_timezone = $this->googleCalendarApiObj->GetUserCalendarTimezone($_SESSION['google_access_token']);

                        // get user calendar lists
                        $user_calendars = $this->googleCalendarApiObj->GetCalendarsList($_SESSION['google_access_token']);

                        if (!empty($user_calendars)) {
                            $calendar_id = $user_calendars[0]['id'];
                        } else {
                            $calendar_id = 'primary';
                        }

                        $event_title = $row['Notes'];

                        // Event starting & finishing at a specific time
                        $full_day_event = 0; // no
                        $event_time = ['start_time' => $start_time, 'end_time' => $end_time];

                        // Full day event
                        // $full_day_event = 1;
                        // $event_time = ['event_date' => $dd];

                        // Create event on primary calendar
                        $event_id = $this->googleCalendarApiObj->CreateCalendarEvent($calendar_id, $event_title, $full_day_event, $event_time, $user_timezone, $_SESSION['google_access_token']);

                        if ($event_id != null) {
                            $ids[] = $row['id'];
                        }

                        // echo json_encode(['event_id' => $event_id]);
                    }

                    // update db that google calendar updated
                    if (!empty($ids)) {
                        $sql_update = $this->dbConn->prepare('update notes set google_calendar_update = ? where id in (?)');
                        $result = $sql_update->execute(array(1, implode(', ', $ids)));
                        if ($result) {
                            header('location: /sales');
                            exit;
                        }
                    }
                } else {
                    header('location: /sales');
                    exit;
                }
            }
        } catch (\Exception $exception) {
            $this->misc->log('Sales ' . __METHOD__, $exception);
        }
    }

    /**
     * Returns an authorized API client.
     * @return Google_Client the authorized client object
     */
    private function getGoogleClientTest()
    {
        try {
            // .json files from google auth
            $tokenPath = __DIR__ . DIRECTORY_SEPARATOR . '../inc/' . GOOGLE_CALENDAR_CLIENT_CONFIG_FILE;
            $credential = __DIR__ . DIRECTORY_SEPARATOR . '../inc/' . GOOGLE_CALENDAR_AUTH_FILE;

            $client = new Google_Client();
            $client->setApplicationName('Google Calendar API PHP Quickstart');
            $client->setScopes(Google_Service_Calendar::CALENDAR_READONLY);
            $client->setAuthConfig($credential);
            $client->setAccessType('offline');
            $client->setPrompt('select_account consent');

            // Load previously authorized token from a file, if it exists.
            // The file token.json stores the user's access and refresh tokens, and is
            // created automatically when the authorization flow completes for the first
            // time.

            if (file_exists($tokenPath)) {
                $accessToken = json_decode(file_get_contents($tokenPath), true);
                $client->setAccessToken($accessToken);
            }

            // If there is no previous token or it's expired.
            if ($client->isAccessTokenExpired()) {
                // Refresh the token if possible, else fetch a new one.
                if ($client->getRefreshToken()) {
                    $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                } else {
                    // Request authorization from the user.
                    $authUrl = $client->createAuthUrl();
                    printf("Open the following link in your browser:\n%s\n", $authUrl);
                    print 'Enter verification code: ';
                    $authCode = trim("4/swGQhr0v6lvXddDIyGuBInzzNmbYeb2gXfYDFZJ1mh-7NWzcDltnQGM");

                    // Exchange authorization code for an access token.
                    $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
                    $client->setAccessToken($accessToken);

                    // Check to see if there was an error.
                    if (array_key_exists('error', $accessToken)) {
                        throw new \Exception(join(', ', $accessToken));
                    }
                }
                // Save the token to a file.
                if (!file_exists(dirname($tokenPath))) {
                    mkdir(dirname($tokenPath), 0700, true);
                }
                file_put_contents($tokenPath, json_encode($client->getAccessToken()));
            }
            return $client;
        } catch (\Exception $exception) {
            $this->misc->log('Sales ' . __METHOD__, $exception);
        }
    }

    private function getGoogleClient()
    {
        try {
            // .json files from google auth
            $tokenPath = __DIR__ . DIRECTORY_SEPARATOR . '../inc/' . GOOGLE_CALENDAR_CLIENT_CONFIG_FILE;
            $credential = __DIR__ . DIRECTORY_SEPARATOR . '../inc/' . GOOGLE_CALENDAR_AUTH_FILE;

            $client = new Google_Client();
            $client->setApplicationName('Google Calendar API PHP Quickstart');
            $client->setScopes(Google_Service_Calendar::CALENDAR_READONLY);
            $client->setAuthConfig($credential);
            $client->setAccessType('offline');
            $client->setPrompt('select_account consent');

            // Load previously authorized token from a file, if it exists.
            // The file token.json stores the user's access and refresh tokens, and is
            // created automatically when the authorization flow completes for the first
            // time.

            if (file_exists($tokenPath)) {
                $accessToken = json_decode(file_get_contents($tokenPath), true);
                $client->setAccessToken($accessToken);
            }

            // If there is no previous token or it's expired.
            if ($client->isAccessTokenExpired()) {
                // Refresh the token if possible, else fetch a new one.
                if ($client->getRefreshToken()) {
                    $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                } else {
                    // Request authorization from the user.
                    $authUrl = $client->createAuthUrl();
                    printf("Open the following link in your browser:\n%s\n", $authUrl);
                    print 'Enter verification code: ';
                    $authCode = trim("4/swGQhr0v6lvXddDIyGuBInzzNmbYeb2gXfYDFZJ1mh-7NWzcDltnQGM");

                    // Exchange authorization code for an access token.
                    $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
                    $client->setAccessToken($accessToken);

                    // Check to see if there was an error.
                    if (array_key_exists('error', $accessToken)) {
                        throw new Exception(join(', ', $accessToken));
                    }
                }
                // Save the token to a file.
                if (!file_exists(dirname($tokenPath))) {
                    mkdir(dirname($tokenPath), 0700, true);
                }
                file_put_contents($tokenPath, json_encode($client->getAccessToken()));
            }
            return $client;
        } catch (\Exception $exception) {
            $this->misc->log('Sales ' . __METHOD__, $exception);
        }
    }

    /**
     * this will calculate the total of active contacts, active deals and pending tasks
     */
    private function calculateTotals($loggedin_user_id, $registration_id, $role, $dbConn)
    {
        try {
            // tasks later also need to take consideration the due date
            $sql_tasks = $dbConn->prepare("SELECT count(n.id) as tot from notes n
                where n.Sales_Rep = ? and n.Task_Status >= ? limit 1");
            $sql_tasks->execute(array($loggedin_user_id, 1));
            $count = $sql_tasks->rowCount();
            $pendingTasksTotal = 0;
            if ($count > 0) {
                while ($row = $sql_tasks->fetch()) {
                    $pendingTasksTotal += $row['tot'];
                }
            }

            // tasks which are not added to google calendar. later also need to take consideration the due date
            $sql_tasks_google = $dbConn->prepare("SELECT count(n.id) as tot from notes n
                where n.Sales_Rep = ? and n.Task_Status >= ? and google_calendar_update = 0 limit 1");
            $sql_tasks_google->execute(array($loggedin_user_id, 1));
            $count_google = $sql_tasks_google->rowCount();
            $pendingGoogleTasksTotal = 0;
            if ($count_google > 0) {
                while ($row_google = $sql_tasks_google->fetch()) {
                    $pendingGoogleTasksTotal += $row_google['tot'];
                }
            }

            // deals
            $sql_deals = $dbConn->prepare("SELECT count(d.id) as tot from deals d
                where d.sales_rep = ? and d.active = ? limit 1");
            $sql_deals->execute(array($loggedin_user_id, 0));
            $count = $sql_deals->rowCount();
            $activeDealsTotal = 0;
            if ($count > 0) {
                while ($row = $sql_deals->fetch()) {
                    $activeDealsTotal += $row['tot'];
                }
            }

            // contacts
            $sql_contacts = $dbConn->prepare("SELECT count(c.id) as tot from contact c
                where c.Sales_Rep = ? limit 1");
            $sql_contacts->execute(array($loggedin_user_id));
            $count = $sql_contacts->rowCount();
            $contactsTotal = 0;
            if ($count > 0) {
                while ($row = $sql_contacts->fetch()) {
                    $contactsTotal += $row['tot'];
                }
            }

            $result = [
                'tasks' => $pendingTasksTotal,
                'tasks_google' => $pendingGoogleTasksTotal,
                'deals' => $activeDealsTotal,
                'contacts' => $contactsTotal,
            ];

            return $result;
        } catch (\Exception $exception) {
            $this->misc->log('Sales ' . __METHOD__, $exception);
        }
    }

    /**
     * fetch ajax task details to edit
     */
    public function get_task_details()
    {
        try {
            if ($_SERVER["REQUEST_METHOD"] == "POST") {
                $sql = $this->dbConn->prepare("select * from notes where id = ? limit 1");
                $sql->execute(array($_POST['task_id']));
                $count = $sql->rowCount();

                $result = [];
                if ($count > 0) {
                    while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
                        $result[] = $row;
                    }
                }

                return json_encode($result);
            }
        } catch (\Exception $exception) {
            $this->misc->log('Sales ' . __METHOD__, $exception);
        }
    }

    /**
     * update a task by ajax
     */
    public function edit_task()
    {
        try {
            if ($_SERVER["REQUEST_METHOD"] == "POST" && $_SESSION['csrf'] == $_POST['data']['csrf']) {

                $task_edit_id = $_POST['data']['task_edit_id'];
                $task_status = $_POST['data']['todo_status'];
                $task_update = $_POST['data']['task_update'];
                $todo_due_date = $_POST['data']['todo_due_date'];
                $date = \DateTime::createFromFormat('m/d/Y', $todo_due_date);
                $temp_due_date = $date->format("Y-m-d");
                $todo_due_time = $_POST['data']['todo_due_time'];
                $temp_duration = $_POST['data']['duration'];
                $duration_details = explode(':', $temp_duration);
                $duration = $duration_details[0] * 60 + $duration_details[1];
                $todo_due_date_details = $todo_due_date . ' ' . date("g:i a", strtotime($todo_due_time)) . ' to ' . date("g:i a", strtotime($todo_due_time . "+{$duration} minutes"));
                $deal = $_POST['data']['deal'];
                $owner = $this->loggedin_user_id;

                $sql_update = $this->dbConn->prepare("update notes set Date = ?,
                    Is_New_Todo = ?,
                    Todo_Due_Date = ?,
                    due_date = ?,
                    start_time = ?,
                    duration = ?,
                    Deal = ?,
                    Task_Status = ?,
                    Task_Update = ?,
                    Sales_Rep = ?
                    where id = ?");
                $result = $sql_update->execute(array(date("Y-m-d"), 2, $todo_due_date_details, $temp_due_date, $todo_due_time, $temp_duration, $deal, $task_status, $task_update, $owner,
                    $task_edit_id));

                if ($result) {
                    echo '200';
                    exit;
                }

                echo '400';
                exit;
            }
        } catch (\Exception $exception) {
            $this->misc->log('Sales ' . __METHOD__, $exception);
        }
    }

    /**
     * this ajax method wil delete a task
     * @return [type] [description]
     */
    public function delete_task()
    {
        try {
            if ($_SERVER["REQUEST_METHOD"] == "POST") {

                $task_edit_id = $_POST['task_id'];

                // delete comments related to this notes
                $sql_update = $this->dbConn->prepare("update comments set status = ?
                    where note_id = ?");
                $result = $sql_update->execute(array(1, $task_edit_id));

                // delete note
                $sql_update = $this->dbConn->prepare("update notes set Task_Status = ?
                    where id = ?");
                $result = $sql_update->execute(array(2, $task_edit_id));

                if ($result) {
                    echo '200';
                    exit;
                }

                echo '400';
                exit;
            }
        } catch (\Exception $exception) {
            $this->misc->log('Sales ' . __METHOD__, $exception);
        }
    }

    /**
     * this ajax function will fetch all deals
     */
    public function get_deals(): string
    {
        try {
            $response = [
                'status' => 400,
                'data' => '',
            ];

            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                $sql = $this->dbConn->prepare("select d.* from contact c
                    inner join deals d on d.contact_id = c.id
                    inner join users u on u.id = d.sales_rep
                    where d.sales_rep = ? and d.active = 0");
                $sql->execute(array($this->loggedin_user_id));
                $count = $sql->rowCount();
                $this->deals = [];

                if ($count > 0) {
                    while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
                        $this->deals[] = $row;
                    }

                    $response = [
                        'status' => 200,
                        'data' => $this->deals,
                    ];
                }

                return json_encode($response);
                exit;
            }
        } catch (\Exception $exception) {
            $this->misc->log('Sales ' . __METHOD__, $exception);
        }
    }

    /**
     * this ajax function will udpate password
     */
    public function change_password()
    {
        try {
            $response = 400;

            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                $password = $_POST['inputPassword'];
                // already validated via jquery
                $conf_password = $_POST['confirmPassword'];

                $p_salt = $this->misc->rand_string(20);
                $site_salt = SECRET; // from .env
                $salted_hash = hash('sha256', $password . $site_salt . $p_salt);

                $sql = $this->dbConn->prepare("update users set Password = ?, psalt = ? where id = ?");
                $value_user = $sql->execute(array($salted_hash, $p_salt, $this->loggedin_user_id));

                if ($value_user) {
                    $response = 200;
                }

                return $response;
                exit;
            }
        } catch (\Exception $exception) {
            $this->misc->log('Sales ' . __METHOD__, $exception);
        }
    }

    /**
     * this ajax function will udpate login with code or not
     */
    public function update_login_with_code()
    {
        try {
            $response = 400;

            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                $login_with_code = isset($_POST['login_with_code']) && $_POST['login_with_code'] == 'on'
                ? 1 : 0;

                $sql = $this->dbConn->prepare("update users set login_with_code = ?
                where id = ?");
                $value_user = $sql->execute(array($login_with_code, $this->loggedin_user_id));

                if ($value_user) {
                    $response = 200;
                }

                return $response;
                exit;
            }
        } catch (\Exception $exception) {
            $this->misc->log('Sales ' . __METHOD__, $exception);
        }
    }
}
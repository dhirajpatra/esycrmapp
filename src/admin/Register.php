<?php
declare (strict_types = 1);

namespace App\admin;

use App\admin\inc\Log;
use App\admin\inc\Misc;
use App\ConnectDb;

class Register
{
    private $twig;
    private $dbConn;
    private $misc;
    private $log;

    public function __construct(
        $twig
    ) {
        $this->twig = $twig;
        $this->dbConn = ConnectDb::getConnection();
        $this->misc = new Misc();
        $this->log = new Log();

    }

    /**
     * this will show the main page
     *
     * @return string
     */
    public function __invoke()
    {
        try {
            // checking session
            $this->misc->check_session();

            $logged_in = isset($_COOKIE['logged_in']) ? $_COOKIE['logged_in'] : null;

            // if already logged in then directly transfered
            if (isset($logged_in) && strlen($logged_in) <= 64) {
                $id = base64_decode($logged_in);

                // if session already not set
                if (!isset($_SESSION['user']['id'])) {
                    $sql = $this->dbConn->prepare("SELECT u.*, rg.id as rgid, r.id as roleid, r.role,
                    s.status, c.code, rg.company_name, rg.industry_id, rg.type, rg.address_id,
                    rg.currency_id, rg.reason_id, rg.referral_registration_id, c.symbol,
                    cm.contacts_limit, cm.deals_limit, cm.sales_rep_limit, cm.special_support,
                t.google_calendar_token, t.google_gmail_token, t.google_login_token, t.email as gmail,
                    FROM users u
                    inner join roles r on r.id = u.User_Roles
                    inner join user_status s on s.id = u.User_Status
                    inner join registrations rg on rg.id = u.registration_id
                    left join currency c on c.id = rg.currency_id
                    inner join companies cm on cm.registration_id = rg.id
                    inner join user_access_tokens t on t.user_id = u.id
                    group by rgid, u.id, cm.id
                    having u.id = ?
                    limit 1");

                    $sql->execute(array($id));
                    $count = $sql->rowCount();

                    if ($count > 0) {
                        while ($row = $sql->fetch()) {
                            $status = $row['status'];

                            if ($status == 'active') {
                                // get the totals nos for header
                                $login_obj = new Login();
                                $header_totals = $login_obj->calculateTotals($row['id'], $row['registration_id'], $row['roleid'], $this->dbConn);
                                //create a cryptographically secure token.
                                $user_token = bin2hex(openssl_random_pseudo_bytes(24));
                                //assign the token to a session variable.
                                $_SESSION['user_token'] = $user_token;
                                if (!isset($_SESSION['user']) or empty($_SESSION['user'])) {
                                    $_SESSION['user'] = [];
                                }

                                // referral code is not there then update
                                if ($row['referral_code'] == '') {
                                    $row['referral_code'] = $this->misc->rand_number(10);
                                }

                                // update last logged in time
                                $sql_logged_in = $this->dbConn->prepare('update users set last_logged_in = ?, referral_code = ? where id = ?');
                                $sql_logged_in->execute(array(date('Y-m-d H:i:s'), $row['referral_code'], $row['id']));

                                $user = [
                                    'email' => $row['Email'],
                                    'id' => $row['id'],
                                    'title' => $row['Name_Title'],
                                    'first_name' => $row['Name_First'],
                                    'middle_name' => $row['Name_Middle'],
                                    'last_name' => $row['Name_Last'],
                                    'role_id' => $row['User_Roles'],
                                    'role' => $row['role'],
                                    'registration_id' => $row['registration_id'],
                                    'company_name' => str_replace(' ', '', ucwords($row['company_name'])),
                                    'currency_code' => $row['code'],
                                    'currency_symbol' => $row['symbol'],
                                    'header_totals' => $header_totals,
                                    'deals_limit' => $row['deals_limit'],
                                    'contacts_limit' => $row['contacts_limit'],
                                    'sales_rep_limit' => $row['sales_rep_limit'],
                                    'special_support' => ($row['special_support'] == 1 ? true : false),
                                    'referral_code' => $row['referral_code'],
                                    'google_calendar_token' => $row['google_calendar_token'],
                                    'google_gmail_token' => $row['google_gmail_token'],
                                    'google_login_token' => $row['google_login_token'],
                                ];

                                $_SESSION['user'] = $user;
                                // gmail id for google api
                                setcookie('gmail_id', base64_encode($row['gmail'] != null ? $row['gmail'] : ''), time() + COOKIE_SET_TIME, "/");

                                // write into log
                                $this->log->write();

                                // get user's last location
                                $last_uri = '';
                                $result_last_uri = $this->log->get_last_location();

                            } else {
                                header("location: /login?error=Invalid_email_or_password_._Or_not_activated_kindly_check_your_email_and_click_on_activation_link.");
                                exit;
                            }
                        }
                    }

                } else {
                    // not for super admin
                    if ($_SESSION['user']['role_id'] != 3 && !empty($result_last_uri)) {
                        $last_uri = $result_last_uri['request_uri'];
                    }

                    // write into log
                    $this->log->write();

                    if ($last_uri == '' || $_SESSION['user']['role_id'] == 3) {
                        switch ($_SESSION['user']['role_id']) {
                            case 2:
                                header("location: /manager");
                                exit();
                            case 1:
                                header("location: /sales");
                                exit();
                            case 3:
                                header("location: /manager/super");
                                exit();
                            default:
                                header("location: /register");
                                exit();
                        }
                    } else {
                        header("location: " . $last_uri);
                        exit();
                    }
                }
            } else {
                header("location: /register");
                exit;
            }
        } catch (\Exception $exception) {
            $this->misc->log('Register' . __METHOD__, $exception);
        }
    }

    /**
     * show registration form
     */
    public function show_registration()
    {
        try {
            if (!isset($_SESSION['csrf'])) {
                $key = sha1(microtime());
                $_SESSION['csrf'] = $key;
            } else {
                $key = $_SESSION['csrf'];
            }

            $sql = $this->dbConn->prepare("SELECT * FROM currency where status = ? order by name");
            $sql->execute(array(1));
            $count = $sql->rowCount();

            $currencies = [];
            if ($count > 0) {
                while ($row = $sql->fetch()) {
                    $currencies[] = [
                        'id' => $row['id'],
                        'name' => $row['name'],
                        'symbol' => mb_convert_encoding($row['symbol'], 'UTF-8', 'HTML-ENTITIES'),
                    ];
                }
            }

            $sql_industry = $this->dbConn->prepare("SELECT * FROM company_industries order by id");
            $sql_industry->execute();
            $count = $sql_industry->rowCount();

            $industries = [];
            if ($count > 0) {
                while ($row = $sql_industry->fetch()) {
                    $industries[] = $row;
                }
            }

            $sql_reason = $this->dbConn->prepare("SELECT * FROM reason_to_join order by id");
            $sql_reason->execute();
            $count = $sql_reason->rowCount();

            $reasons = [];
            if ($count > 0) {
                while ($row = $sql_reason->fetch()) {
                    $reasons[] = $row;
                }
            }

            $response = $this->twig->render('register.html.twig', ['currencies' => $currencies, 'industries' => $industries, 'reasons' => $reasons, 'key' => $key]);

            return $response;
        } catch (\Exception $exception) {
            $this->misc->log('Register' . __METHOD__, $exception);
        }
    }

    /**
     * process new registration
     */
    public function do_registration()
    {
        try {
            if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['csrf'])
                && isset($_POST['csrf']) && $_SESSION['csrf'] == $_POST['csrf']) {
                // username and password sent from form
                $company_name = $_POST['companyName'];
                // $company_type = $_POST['companyType'];
                // if($company_type == 0) {
                //   $role = 1;
                // } else {
                //   $role = 2;
                // }
                $role = MANAGER_ROLE; // by default when company registration happening for sales manager
                $ee_personal_code = null;

                $first_name = $_POST['firstName'];
                $last_name = $_POST['lastName'];
                $email = $_POST['inputEmail'];
                $password = $_POST['inputPassword'];
                // not required as already validated via jquery
                $conf_password = $_POST['confirmPassword'];
                $currency = $_POST['currency'];
                $reason = $_POST['reason'];
                $industry = $_POST['industry'];
                $referral_code = $_POST['referral_code'];
                $login_with_code = isset($_POST['login_with_code']) && $_POST['login_with_code'] == 'on'
                ? 1 : 0;

                // later need to change to email confirmation to activate
                $status = PENDING_USER;
                $code = $this->misc->rand_number(10);

                $sql = $this->dbConn->prepare("SELECT * FROM registrations WHERE email = ? limit 1");
                $sql->execute(array($email));
                $count = $sql->rowCount();

                if ($count == 0) {
                    $p_salt = $this->misc->rand_string(20);
                    $site_salt = SECRET; // from .env
                    $salted_hash = hash('sha256', $password . $site_salt . $p_salt);

                    // preparing activation link for activation
                    $activation_url_part = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/activation/" . base64_encode($email) . "/";
                    // generate invitation code
                    $activation_link = $activation_url_part . $salted_hash;

                    // later need to add address
                    $sql_insert = $this->dbConn->prepare("insert into registrations(company_name, email, address_id, currency_id, reason_id, industry_id, activation_link) value(?, ?, ?, ?, ?, ?, ?)");
                    $value = $sql_insert->execute(array($company_name, $email, 0, $currency, $reason, $industry, $activation_link));

                    if ($value === true) {
                        $company_id = $this->dbConn->lastInsertId();

                        // insert into companies table
                        // later need to implement referral
                        if ($referral_code != null) {

                            // same amount of limit need to add to whoes referral_code using
                            // later it must be moved after first login of this user only
                            $sql_referral_code = $this->dbConn->prepare("SELECT * FROM users where referral_code = ?");
                            $sql_referral_code->execute(array($referral_code));
                            $count_referral_code = $sql_referral_code->rowCount();
                            if ($count_referral_code > 0) {
                                // update the quota whoes referral code used by this new registration
                                $contacts_limit = (CONTACTS_LIMIT + ADD_REFERRAL_CONTACTS_LIMIT);
                                $deals_limit = (DEALS_LIMIT + ADD_REFERRAL_DEALS_LIMIT);

                                while ($row_referral_code = $sql_referral_code->fetch()) {

                                    $sql_update_companies = $this->dbConn->prepare("update companies set contacts_limit = contacts_limit + ?, deals_limit = deals_limit + ? where registration_id = ?");
                                    $sql_update_companies->execute(array(ADD_REFERRAL_CONTACTS_LIMIT, ADD_REFERRAL_DEALS_LIMIT, $row_referral_code['registration_id']));

                                }
                                // if not match the referral code
                            } else {
                                $contacts_limit = CONTACTS_LIMIT;
                                $deals_limit = DEALS_LIMIT;
                            }

                        } elseif (isset($_POST['ee_personal_code']) && $_POST['ee_personal_code'] != '') {
                            $ee_personal_code = $_POST['ee_personal_code'];
                            // for e-residents of estonia
                            $contacts_limit = ERESIDENT_CONTACTS_LIMIT;
                            $deals_limit = ERESIDENT_DEALS_LIMIT;
                        } else {
                            $contacts_limit = CONTACTS_LIMIT;
                            $deals_limit = DEALS_LIMIT;
                        }

                        // insert for new registration
                        $sql_insert_companies = $this->dbConn->prepare("insert into companies(registration_id, contacts_limit, deals_limit, special_support, sales_rep_limit) value(?, ?, ?, ?, ?)");
                        $sql_insert_companies->execute(array($company_id, $contacts_limit, $deals_limit, SALES_SUPPORT, SALES_REP_LIMIT));

                        // preparation for mail to activate the account
                        // send mail after update the values from template
                        $sql_template = $this->dbConn->prepare("select * from mails where module = ? limit 1");
                        $sql_template->execute(array('register'));
                        $count_template = $sql_template->rowCount();

                        // // preparing activation link for activation
                        // $activation_url_part = (isset($_SERVER['HTTPS'])
                        // && $_SERVER['HTTPS'] === 'on' ? "https" : "http") .
                        // "://$_SERVER[HTTP_HOST]/activation/" . base64_encode($email) . "/";
                        // // generate invitation code
                        // $activation_link = $activation_url_part . $salted_hash;

                        if ($count_template > 0) {
                            while ($row_template = $sql_template->fetch()) {
                                $body = str_ireplace('{{FIRST_NAME}}', $first_name, $row_template['body']);
                                $body = str_ireplace('{{COMPANY}}', '<b>' . $company_name . '</b>', $body);
                                $body = str_ireplace('{{ACTIVATION_LINK}}', $activation_link, $body);
                                $body = str_ireplace('{{SalesCRM}}', APP_NAME, $body);
                                $body = str_ireplace('{{http://www.salescrm.com}}', WEBSITE_LINK, $body);
                                $body = str_ireplace('{{www.salescrm.com}}', WEBSITE, $body);
                                $body = str_ireplace('{{USERNAME}}', '<b>' . $email . '</b>', $body);
                                $body = str_ireplace('{{PASSWORD}}', '********', $body);
                                $body = str_ireplace('{{ENCODED_EMAIL}}', base64_encode($email), $body);
                                $subject = $row_template['subject'] . ' to ' . APP_NAME;
                            }
                        }

                        $to = [
                            'email' => $email,
                            'name' => $first_name,
                        ];

                        $reply = [
                            'email' => MAIL_FROM_EMAIL,
                            'name' => MAIL_FROM_NAME,
                        ];

                        $reply_to = [
                            'email' => MAIL_REPLY_TO_EMAIL,
                            'name' => MAIL_REPLY_TO_NAME,
                        ];

                        // sending mail and saving data
                        if ($this->misc->send_mail($to, $reply, $reply_to, $subject, $body)) {
                            $sql_insert_user = $this->dbConn->prepare("insert into users(registration_id,
                            Name_First, Name_Last, Email, Password, User_Roles, User_Status, psalt,
                            referral_code, ee_personal_code, login_with_code)
                            value(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $value_user = $sql_insert_user->execute(array($company_id, $first_name,
                                $last_name, $email, $salted_hash, $role, $status, $p_salt, $code,
                                $ee_personal_code, $login_with_code));
                            $new_user_id = $this->dbConn->lastInsertId();

                            if ($value_user === true) {
                                // insert into user_access_tokens
                                $sql_insert_access_tokens = $this->dbConn->prepare("insert into user_access_tokens
								 (user_id)
								 value
								 (?)");
                                $sql_insert_access_tokens->execute(array($new_user_id));

                                $_POST = false;
                                header("location: /thanks_registration");
                                exit;
                            }
                        }
                    }
                } else {
                    $error = 'Company already exist with this email. Kindly check the details in registraion form.';
                    $response = $this->twig->render('error.html.twig', ['error' => $error]);

                    return $response;
                }
            } else {
                $error = 'Form has been tampered. Or try after refreshing the page.';
                $response = $this->twig->render('error.html.twig', ['error' => $error]);

                return $response;
            }
        } catch (\Exception $exception) {
            $this->misc->log('Register' . __METHOD__, $exception);
        }
    }

    /**
     * this will show thanks page after registration sucessfully done
     *
     * @return void
     */
    public function thanks_registration()
    {
        try {
            // checking session
            $this->misc->check_session();

            $response = $this->twig->render('thanks_registration.html.twig');

            return $response;
        } catch (\Exception $exception) {
            $this->misc->log('Register' . __METHOD__, $exception);
        }
    }

    /**
     * show forgot password form
     */
    public function forgot_password()
    {
        try {
            if (!isset($_SESSION['csrf'])) {
                $key = sha1(microtime());
                $_SESSION['csrf'] = $key;
            } else {
                $key = $_SESSION['csrf'];
            }

            $response = $this->twig->render('forgot_password.html.twig', ['key' => $key]);

            return $response;
        } catch (\Exception $exception) {
            $this->misc->log('Register' . __METHOD__, $exception);
        }
    }

    /**
     * this post call will process forgot password
     */
    public function forgot_password_process()
    {
        try {
            if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['csrf']) && $_SESSION['csrf'] == $_POST['csrf']) {
                $email = $_POST['inputEmail'];

                // checking that email with valid active user or not
                $sql = $this->dbConn->prepare("SELECT * FROM users WHERE Email = ? and User_Status = ? limit 1");
                $sql->execute(array($email, ACTIVE_USER));
                $count = $sql->rowCount();

                if ($count > 0) {
                    $p_salt = $this->misc->rand_string(20);
                    $code = $this->misc->rand_string(20);
                    $password = $code;
                    $site_salt = SECRET; // from .env
                    $salted_hash = hash('sha256', $password . $site_salt . $p_salt);

                    while ($row = $sql->fetch()) {
                        $first_name = $row['Name_First'];
                        $id = $row['id'];
                    }

                    // preparation for mail to change the password
                    // send mail after update the values from template
                    $sql_template = $this->dbConn->prepare("select * from mails where module = ? limit 1");
                    $sql_template->execute(array('forgot-password'));
                    $count_template = $sql_template->rowCount();

                    // preparing activation link for activation
                    $activation_url_part = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/change_password/" . base64_encode($email) . "/";
                    // generate invitation code
                    $activation_link = $activation_url_part . $salted_hash;

                    if ($count_template > 0) {
                        while ($row_template = $sql_template->fetch()) {
                            $body = str_ireplace('{{FIRST_NAME}}', $first_name, $row_template['body']);
                            $body = str_ireplace('{{ACTIVATION_LINK}}', $activation_link, $body);
                            $body = str_ireplace('{{SalesCRM}}', APP_NAME, $body);
                            $body = str_ireplace('{{http://www.salescrm.com}}', WEBSITE_LINK, $body);
                            $body = str_ireplace('{{www.salescrm.com}}', WEBSITE, $body);
                            $body = str_ireplace('{{ENCODED_EMAIL}}', base64_encode($email), $body);
                            $subject = $row_template['subject'] . ' for account in ' . APP_NAME;
                        }
                    }

                    $to = [
                        'email' => $email,
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

                    // sending mail and saving data
                    if ($this->misc->send_mail($to, $from, $reply, $subject, $body)) {
                        $sql_update_password = $this->dbConn->prepare("update users set Password = ?, psalt = ?, invitation_code = ? where id = ?");
                        $value_user = $sql_update_password->execute(array($salted_hash, $p_salt, $code, $id));

                        if ($value_user === true) {
                            $_POST = false;
                            header("location: /thanks_forgot_password");
                            exit;
                        }
                    } else {
                        header("location: /forgot_password");
                        exit;
                    }
                } else {
                    $_POST = false;
                    header("location: /forgot-password?error=Email does not exist or not a active user. Kindly enter correct email.");
                    exit;
                }
            }
        } catch (\Exception $exception) {
            $this->misc->log('Register' . __METHOD__, $exception);
        }
    }

    /**
     * show change password option
     */
    public function change_password()
    {
        try {

            if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_REQUEST['vars']['email']) && $_REQUEST['vars']['email'] != '' && isset($_REQUEST['vars']['code']) && $_REQUEST['vars']['code'] != '' && strlen($_REQUEST['vars']['code']) == 64) {
                // decrypt the token
                // var_dump($_GET);exit;

                $email = base64_decode($_SESSION['query_params']['email']);
                $activation_code = $_SESSION['query_params']['code'];

                // validate token
                $sql = $this->dbConn->prepare("SELECT * FROM users
                    where Email = ? and Password =  ? and User_Status = ? limit 1");

                $sql->execute(array($email, $activation_code, ACTIVE_USER));
                $count = $sql->rowCount();

                if ($count > 0) {
                    while ($row = $sql->fetch()) {
                        $salted_hash_db = $row['Password'];
                        // $p_salt_db = $row['psalt'];
                        // $invitation_code_db = $row['invitation_code'];
                        // echo $salted_hash_db . ' ------- ' . $activation_code;

                        // password change
                        // update status and show the form for enter new password
                        if (!isset($_SESSION['csrf'])) {
                            $key = sha1(microtime());
                            $_SESSION['csrf'] = $key;
                        } else {
                            $key = $_SESSION['csrf'];
                        }

                        $response = $this->twig->render('change_password.html.twig', ['key' => $key, 'id' => $row['id']]);

                        return $response;
                    }

                } else {
                    $error = 'Not a valid link. Kindly contact your manager to request for support.';

                    $response = $this->twig->render('error.html.twig', ['error' => $error]);

                    return $response;
                }
            }
        } catch (\Exception $exception) {
            $this->misc->log('Register' . __METHOD__, $exception);
        }
    }

    /**
     * process change password
     */
    public function change_password_process()
    {
        try {

            if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['csrf']) && $_SESSION['csrf'] == $_POST['csrf']) {
                // inpput validation not requied as done by jquery
                $password = $_POST['inputPassword'];
                $id = $_POST['user_id'];

                $p_salt = $this->misc->rand_string(20);
                $site_salt = SECRET; // from .env
                $salted_hash = hash('sha256', $password . $site_salt . $p_salt);

                $sql_insert_user = $this->dbConn->prepare("update users set Password = ?, psalt = ? where id = ?");
                $value_user = $sql_insert_user->execute(array($salted_hash, $p_salt, $id));

                if ($value_user === true) {

                    // setcookie('logged_in', base64_encode($id), time() + COOKIE_SET_TIME, "/");
                    header("location: /thanks_change_password");
                    exit;

                } else {
                    $error = 'Sorry your Password could not be changed. Kindly contact your Admin or our support team.';
                    $_POST = false;
                    $response = $this->twig->render('error.html.twig', ['error' => $error]);

                    return $response;
                }
            }
        } catch (\Exception $exception) {
            $this->misc->log('Register' . __METHOD__, $exception);
        }
    }

    /**
     * [thanks_chage_password description]
     * @return [type] [description]
     */
    public function thanks_change_password()
    {
        try {

            $response = $this->twig->render('thanks_change_password.html.twig');

            return $response;
        } catch (\Exception $exception) {
            $this->misc->log('Register' . __METHOD__, $exception);
        }
    }

    /**
     * show thanks to change password
     */
    public function thanks_forgot_password()
    {
        try {
            $response = $this->twig->render('thanks_forgot_password.html.twig');

            return $response;
        } catch (\Exception $exception) {
            $this->misc->log('Register' . __METHOD__, $exception);
        }
    }

    /**
     * unsubscription
     */
    public function unsubscribe()
    {
        try {
            if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_SESSION['query_params']['email']) && $_SESSION['query_params']['email'] != '') {
                // decrypt the token
                // var_dump($_GET); exit;

                $email = base64_decode($_SESSION['query_params']['email']);

                // validate token
                $sql = $this->dbConn->prepare("update users
                    set unsubscribe = ? where Email = ? and unsubscribe = ?");

                $result = $sql->execute(array(UNSUBSCRIBE, $email, SUBSCRIBE));

                if ($result) {
                    $response = $this->twig->render('thanks_unsubscription.html.twig');

                } else {
                    $error = 'Not a valid link. Kindly contact your manager to request for support.';

                    $response = $this->twig->render('error.html.twig', ['error' => $error]);
                }

                return $response;
            }

        } catch (\Exception $exception) {
            $this->misc->log('Register' . __METHOD__, $exception);
        }
    }
}
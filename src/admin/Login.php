<?php

declare (strict_types = 1);

namespace App\admin;

use App\admin\inc\Log;
use App\admin\inc\Misc;
use App\ConnectDb;
use Google_Client;

class Login
{
    private $twig;
    private $dbConn;
    private $misc;
    private $key;
    private $log;
    private $google_auth_url;
    private $google_auth_redirect_url;

    public function __construct(
        $twig = null
    ) {
        $this->twig = $twig;
        $this->dbConn = ConnectDb::getConnection();
        $this->misc = new Misc();
        $this->log = new Log();

        // google sso login
        if (
            isset($_SERVER['HTTPS']) &&
            ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1) ||
            isset($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
            $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https'
        ) {
            $protocol = 'https://';
        } else {
            $protocol = 'http://';
        }

        // for sso login
        $this->google_auth_redirect_url = $protocol . $_SERVER['HTTP_HOST'] . GOOGLE_OAUTH_SSO_CLIENT_REDIRECT_URL;
        $this->google_auth_url = 'https://accounts.google.com/o/oauth2/auth?scope=' . urlencode('https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/userinfo.email') . '&redirect_uri=' . urlencode($this->google_auth_redirect_url) . '&response_type=code&client_id=' . GOOGLE_OAUTH_SSO_CLIENT_ID . '&access_type=online';

        // checking session
        if (isset($_SESSION['user'])) {
            $this->misc->check_session();
        }

        // normal login all the time
        if (!isset($_SESSION['csrf'])) {
            $this->key = sha1(microtime());
            $_SESSION['csrf'] = $this->key;
        }
    }

    /**
     * this method will show the login form
     * @return [type] [description]
     */
    public function __invoke()
    {
        try {
            // this required as two login form dynamically show to take email pass and take code
            // and as both form already created so session get change at new will not match
            if (!isset($_COOKIE['csrf'])) {
                // $csrf = sha1(microtime());
                setcookie('csrf', $_SESSION['csrf'], time() + 300);
            } else {
                $csrf = $_COOKIE['csrf'];
            }

            $logged_in = isset($_COOKIE['logged_in']) ? $_COOKIE['logged_in'] : null;

            // if already logged in then directly transfered
            if (isset($logged_in) && strlen($logged_in) <= 64) {
                $id = base64_decode($logged_in);

                // if session already not set
                if (!isset($_SESSION['user']['id'])) {
                    // need to login into session
                    $by = [
                        'id' => $id,
                    ];
                    // calling pvt fucntion to login process
                    $this->login($by);

                    // $sql = $this->dbConn->prepare("SELECT u.*, rg.id as rgid, r.id as roleid, r.role, s.status, c.code, rg.company_name, rg.industry_id, rg.type, rg.address_id, rg.currency_id, rg.reason_id, rg.referral_registration_id, c.symbol, cm.contacts_limit, cm.deals_limit, cm.sales_rep_limit, cm.special_support FROM users u
                    //                inner join roles r on r.id = u.User_Roles
                    //                inner join user_status s on s.id = u.User_Status
                    //                inner join registrations rg on rg.id = u.registration_id
                    //                left join currency c on c.id = rg.currency_id
                    //                inner join companies cm on cm.registration_id = rg.id
                    //                group by rgid, u.id, cm.id
                    //                having u.id = ?
                    //                limit 1");

                    // $sql->execute(array($id));
                    // $count = $sql->rowCount();

                    // if ($count > 0) {
                    //     while ($row = $sql->fetch()) {
                    //         $status = $row['status'];

                    //         if ($status == 'active') {
                    //             // get the totals nos for header
                    //             $login_obj = new Login();
                    //             $header_totals = $login_obj->calculateTotals($row['id'], $row['registration_id'], $row['roleid'], $this->dbConn);
                    //             //create a cryptographically secure token.
                    //             $user_token = bin2hex(openssl_random_pseudo_bytes(24));
                    //             //assign the token to a session variable.
                    //             $_SESSION['user_token'] = $user_token;
                    //             if (!isset($_SESSION['user']) or empty($_SESSION['user'])) {
                    //                 $_SESSION['user'] = [];
                    //             }

                    //             // referral code is not there then update
                    //             if ($row['referral_code'] == '') {
                    //                 $row['referral_code'] = $this->misc->rand_number(10);
                    //             }

                    //             // update last logged in time
                    //             $sql_logged_in = $this->dbConn->prepare('update users set last_logged_in = ?, referral_code = ? where id = ?');
                    //             $sql_logged_in->execute(array(date('Y-m-d H:i:s'), $row['referral_code'], $row['id']));

                    //             $user = [
                    //                 'email' => $row['Email'],
                    //                 'id' => $row['id'],
                    //                 'title' => $row['Name_Title'],
                    //                 'first_name' => $row['Name_First'],
                    //                 'middle_name' => $row['Name_Middle'],
                    //                 'last_name' => $row['Name_Last'],
                    //                 'role_id' => $row['User_Roles'],
                    //                 'role' => $row['role'],
                    //                 'registration_id' => $row['registration_id'],
                    //                 'company_name' => str_replace(' ', '', ucwords($row['company_name'])),
                    //                 'currency_code' => $row['code'],
                    //                 'currency_symbol' => $row['symbol'],
                    //                 'header_totals' => $header_totals,
                    //                 'deals_limit' => $row['deals_limit'],
                    //                 'contacts_limit' => $row['contacts_limit'],
                    //                 'sales_rep_limit' => $row['sales_rep_limit'],
                    //                 'special_support' => ($row['special_support'] == 1 ? true : false),
                    //                 'referral_code' => $row['referral_code'],
                    //             ];

                    //             $_SESSION['user'] = $user;

                    //             // write into log
                    //             $this->log->write();

                    //             // get user's last location
                    //             $last_uri = '';
                    //             $result_last_uri = $this->log->get_last_location();

                    //             // write into log
                    //             $this->log->write();

                    //             if ($last_uri == '' || $_SESSION['user']['role_id'] == 3) {
                    //                 switch ($_SESSION['user']['role_id']) {
                    //                 case 2:
                    //                     header("location: /manager");
                    //                     exit();
                    //                 case 1:
                    //                     header("location: /sales");
                    //                     exit();
                    //                 case 3:
                    //                     header("location: /manager/super");
                    //                     exit();
                    //                 default:
                    //                     header("location: /register");
                    //                     exit();
                    //                 }
                    //             } else {
                    //                 header("location: " . $last_uri);
                    //                 exit();
                    //             }

                    //         } else {
                    //             header("location: /login?error=Invalid_email_or_password_._Or_not_activated_kindly_check_your_email_and_click_on_activation_link.");
                    //             exit;
                    //         }
                    //     }

                    // } else {
                    //     // no account found
                    //     // delete cookies
                    //     setcookie('logged_in', "", time() - 3600);
                    //     header('location: /register');
                    //     exit;
                    // }

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
                $error = isset($_GET['error']) ? str_replace('_', ' ', $_GET['error']) : null;
                $email = isset($_COOKIE["username"]) ? $_COOKIE["username"] : null;
                $password = isset($_COOKIE["password"]) ? $_COOKIE["password"] : null;
                $message = isset($_SESSION['message']) ? $_SESSION['message'] : null;

                $response = $this->twig->render('login.html.twig', ['key' => $_SESSION['csrf'], 'error' => $error, 'password' => $password, 'email' => $email, 'btn_login' => 'Login', 'btn_login_main' => 'Login', 'error' => $message, 'google_auth_url' => $this->google_auth_url]);

                return $response;
            }
        } catch (\Exception $exception) {
            $this->misc->log('Login' . __METHOD__, $exception);
        }
    }

    /**
     * this will ajax process sending login code
     */
    public function send_login_code()
    {
        try {
            // read csrf cookie reading
            $csrf = isset($_COOKIE['csrf']) ? $_COOKIE['csrf'] : '';
            if ($_SERVER["REQUEST_METHOD"] == "POST" && (($csrf == $_POST['csrf']) || (isset($_SESSION['csrf']) && $_SESSION['csrf'] == $_POST['csrf']))) {

                // username and password sent from form
                $email = $myusername = $_POST['inputEmail'];
                $password = $_POST['inputPassword'];

                // remember username and password for 1 hour
                if (!empty($_POST["remember"])) {
                    setcookie("username", $myusername, time() + COOKIE_SET_TIME, "/");
                    setcookie("password", $password, time() + COOKIE_SET_TIME, "/");
                } else {
                    setcookie("username", "");
                    setcookie("password", "");
                }

                // only active user can login
                // $sql = $this->dbConn->prepare("SELECT u.*, rg.id as rgid, r.id as roleid, r.role, s.status,
                // c.code, rg.company_name, rg.industry_id, rg.type, rg.address_id, rg.currency_id,
                // rg.reason_id, rg.referral_registration_id, c.symbol FROM users u
                //     inner join roles r on r.id = u.User_Roles
                //     inner join user_status s on s.id = u.User_Status
                //     inner join registrations rg on rg.id = u.registration_id
                //     left join currency c on c.id = rg.currency_id
                //     group by rgid, u.id
                //     having u.Email = ? and u.User_Status = ? limit 1");
                $sql = $this->dbConn->prepare("SELECT u.*, rg.id as rgid,
                r.id as roleid, r.role, s.status, c.code,
                rg.company_name, rg.industry_id, rg.type, rg.address_id,
                rg.currency_id, rg.reason_id, rg.referral_registration_id,
                c.symbol, cm.contacts_limit, cm.deals_limit, cm.sales_rep_limit,
                t.google_calendar_token, t.google_gmail_token, t.google_login_token, t.email as gmail,
                 cm.special_support FROM users u
                inner join roles r on r.id = u.User_Roles
                inner join user_status s on s.id = u.User_Status
                inner join registrations rg on rg.id = u.registration_id
                left join currency c on c.id = rg.currency_id
                inner join companies cm on cm.registration_id = rg.id
                inner join user_access_tokens t on t.user_id = u.id
                group by rgid, u.id, cm.id
                having u.Email = ? and u.User_Status = ? limit 1");
                $sql->execute(array($myusername, 1));
                $count = $sql->rowCount();

                if ($count > 0) {
                    while ($row = $sql->fetch()) {
                        $status = $row['status'];
                        // only who wanted to login with verification code
                        if ($status == 'active' && $row['login_with_code'] == 1) {
                            $p = $row['Password'];
                            $p_salt = $row['psalt'];
                            $site_salt = SECRET; // from .env

                            // calculating with hash and salt
                            $salted_hash = hash('sha256', $password . $site_salt . $p_salt);

                            if ($p == $salted_hash) {
                                //create a cryptographically secure token.
                                $user_token = bin2hex(openssl_random_pseudo_bytes(24));
                                //assign the token to a session variable.
                                $_SESSION['user_token'] = $user_token;

                                // save code to DB
                                $time_check = date("Y-m-d H:i:s", strtotime('-' . LOGIN_CODE_EXPIRE . 'minutes'));
                                // check already saved code is expire or not
                                if ($time_check > $row['created_at']) {
                                    $code = $this->misc->rand_number(6);
                                    $timestamp = date("Y-m-d H:i:s");
                                    $sql_insert = $this->dbConn->prepare("update users set invitation_code = ?, created_at = ? where id = ?");
                                    $value = $sql_insert->execute(array($code, $timestamp, $row['id']));

                                    if ($value === true) {
                                        // send email with code
                                        // preparation for mail to activate the account
                                        // send mail after update the values from template
                                        $sql_template = $this->dbConn->prepare("select * from mails where module = ? limit 1");
                                        $sql_template->execute(array('login-code'));
                                        $count_template = $sql_template->rowCount();

                                        if ($count_template > 0) {
                                            while ($row_template = $sql_template->fetch()) {
                                                $body = str_ireplace(
                                                    '{{VERIFICATION_CODE}}',
                                                    $code,
                                                    $row_template['body']
                                                );
                                                $body = str_ireplace(
                                                    '{{SalesCRM}}',
                                                    APP_NAME,
                                                    $body
                                                );
                                                $body = str_ireplace(
                                                    '{{http://www.salescrm.com}}',
                                                    WEBSITE_LINK,
                                                    $body
                                                );
                                                $body = str_ireplace('{{www.salescrm.com}}', WEBSITE, $body);
                                                $body = str_ireplace('{{ENCODED_EMAIL}}', base64_encode($email), $body);
                                                $body = str_ireplace(
                                                    '{{COMPANY_EMAIL}}',
                                                    COMPANY_EMAIL,
                                                    $body
                                                );
                                                $body = str_ireplace(
                                                    '{{LOGIN_CODE_EXPIRE}}',
                                                    LOGIN_CODE_EXPIRE,
                                                    $body
                                                );

                                                $subject = $row_template['subject'] . ' for Login on ' . APP_NAME . ' verifiction code is ' . $code;
                                            }
                                        }

                                        $to = [
                                            'email' => $row['Email'],
                                            'name' => $row['Name_First'],
                                        ];

                                        $reply = [
                                            'email' => MAIL_FROM_EMAIL,
                                            'name' => MAIL_FROM_NAME,
                                        ];

                                        $reply_to = [
                                            'email' => MAIL_REPLY_TO_EMAIL,
                                            'name' => MAIL_REPLY_TO_NAME,
                                        ];

                                        //for localhost do not send mail
                                        preg_match('/localhost/', $_SERVER['HTTP_HOST'], $match);
                                        if (in_array('localhost', $match)) {
                                            //$this->misc->send_mail($to, $reply, $reply_to, $subject, $body);
                                            echo '200';
                                            exit;
                                        } else {
                                            //sending mail and saving data
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
                                    echo '201';
                                    exit;
                                }
                            } else {
                                echo '409';
                                exit;
                            }
                        } elseif ($status == 'active' && $row['login_with_code'] == 0) {
                            // direct login without verification code as user do not asked for 2FA
                            $p = $row['Password'];
                            $p_salt = $row['psalt'];
                            $site_salt = SECRET; // from .env

                            // calculating with hash and salt
                            $salted_hash = hash('sha256', $password . $site_salt . $p_salt);

                            if ($p == $salted_hash) {
                                // get the totals nos for header
                                $header_totals = $this->calculateTotals($row['id'], $row['registration_id'], $row['roleid'], $this->dbConn);

                                //create a cryptographically secure token.
                                $user_token = bin2hex(openssl_random_pseudo_bytes(24));
                                //assign the token to a session variable.
                                $_SESSION['user_token'] = $user_token;
                                if (!isset($_SESSION['user']) or empty($_SESSION['user'])) {
                                    $_SESSION['user'] = [];
                                }
                                // for continue login
                                setcookie("logged_in", base64_encode(strval($row['id'])), time() + COOKIE_SET_TIME, "/");

                                // referral code is not there then update
                                if ($row['referral_code'] == '') {
                                    $row['referral_code'] = $this->misc->rand_number(10);
                                }

                                // update last logged in time
                                $sql_logged_in = $this->dbConn->prepare('update users
                                set last_logged_in = ?, referral_code = ? where id = ?');
                                $sql_logged_in->execute(array(
                                    date('Y-m-d H:i:s'), $row['referral_code'],
                                    $row['id'],
                                ));

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

                                $this->log = new Log();

                                // get user's last location
                                $last_uri = '';
                                $result_last_uri = $this->log->get_last_location();
                                // not for super admin
                                if ($_SESSION['user']['role_id'] != 3 && !empty($result_last_uri)) {
                                    $last_uri = $result_last_uri['request_uri'];
                                }

                                // write into log
                                $this->log->write();
                                // if ($last_uri == '' || $_SESSION['user']['role_id'] == 3) {
                                //     switch ($_SESSION['user']['role_id']) {
                                //         case 2:
                                //             header("location: /manager");
                                //             exit();
                                //         case 1:
                                //             header("location: /sales");
                                //             exit();
                                //         case 3:
                                //             header("location: /manager/super");
                                //             exit();
                                //         default:
                                //             header("location: /register");
                                //             exit();
                                //     }
                                // } else {
                                //     header("location: " . $last_uri);
                                //     exit();
                                // }

                                echo '202';
                                exit;
                            } else {
                                echo '409';
                                exit;
                            }
                        }
                    }
                } else {
                    echo '409';
                    exit;
                }
            }
        } catch (\Exception $exception) {
            $this->misc->log('Login' . __METHOD__, $exception);
        }
    }

    /**
     * this will process login and prepare sessions
     */
    public function do_login()
    {
        try {

            $csrf = isset($_COOKIE['csrf']) ? $_COOKIE['csrf'] : '';
            if ($_SERVER["REQUEST_METHOD"] == "POST" && $csrf == $_POST['csrf']) {
                // setcookie('csrf', '', time() - 3600);
                // username and password sent from form
                $myusername = $_POST['inputEmail'];
                $password = $_POST['inputPassword'];
                $code = $_POST['verification_code'];

                // remember username and password for 1 day
                if (!empty($_POST["remember"])) {
                    setcookie("username", $myusername, time() + COOKIE_SET_TIME, "/");
                    setcookie("password", $password, time() + COOKIE_SET_TIME, "/");
                } else {
                    setcookie("username", "");
                    setcookie("password", "");
                }

                // only active user can login
                $sql = $this->dbConn->prepare("SELECT u.*, rg.id as rgid, r.id as roleid, r.role, s.status, c.code, rg.company_name, rg.industry_id, rg.type, rg.address_id, rg.currency_id, rg.reason_id, rg.referral_registration_id, c.symbol, cm.contacts_limit, cm.deals_limit, cm.sales_rep_limit,
                t.google_calendar_token, t.google_gmail_token, t.google_login_token, t.email as gmail,
                cm.special_support FROM users u
                    inner join roles r on r.id = u.User_Roles
                    inner join user_status s on s.id = u.User_Status
                    inner join registrations rg on rg.id = u.registration_id
                    left join currency c on c.id = rg.currency_id
                    inner join companies cm on cm.registration_id = rg.id
                    inner join user_access_tokens t on t.user_id = u.id
                    group by rgid, u.id, cm.id
                    having u.Email = ? and u.User_Status = ?
                    and u.invitation_code = ? and created_at >= (NOW() - INTERVAL " . LOGIN_CODE_EXPIRE . " MINUTE)
                    limit 1");

                $sql->execute(array($myusername, 1, $code));
                $count = $sql->rowCount();

                if ($count > 0) {
                    while ($row = $sql->fetch()) {
                        $status = $row['status'];

                        if ($status == 'active') {
                            $p = $row['Password'];
                            $p_salt = $row['psalt'];
                            $site_salt = SECRET; // from config.php

                            // calculating with hash and salt
                            $salted_hash = hash('sha256', $password . $site_salt . $p_salt);

                            if ($p == $salted_hash) {
                                // get the totals nos for header
                                $header_totals = $this->calculateTotals($row['id'], $row['registration_id'], $row['roleid'], $this->dbConn);

                                //create a cryptographically secure token.
                                $user_token = bin2hex(openssl_random_pseudo_bytes(24));
                                //assign the token to a session variable.
                                $_SESSION['user_token'] = $user_token;
                                if (!isset($_SESSION['user']) or empty($_SESSION['user'])) {
                                    $_SESSION['user'] = [];
                                }
                                // for continue login
                                setcookie("logged_in", base64_encode($row['id']), time() + COOKIE_SET_TIME, "/");

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

                                $this->log = new Log();

                                // get user's last location
                                $last_uri = '';
                                $result_last_uri = $this->log->get_last_location();
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
                            } else {
                                header("location: /login?error=Invalid_email_or_password_._Or_not_activated_kindly_check_your_email_and_click_on_activation_link.");
                                exit;
                            }
                        }
                    }
                } else {
                    header("location: /login?error=Invalid_email_or_password_._Or_not_activated_kindly_check_your_email_and_click_on_activation_link._or_Login_code_is_>_" . LOGIN_CODE_EXPIRE . "_minute_old.");
                    exit;
                }
            } else {
                header("location: /login?error=Invalid_email_or_password_._Or_not_activated_kindly_check_your_email_and_click_on_activation_link._or_Login_code_is_>_" . LOGIN_CODE_EXPIRE . "_minute_old.");
                exit;
            }
        } catch (\Exception $exception) {
            $this->misc->log('Login' . __METHOD__, $exception);
        }
    }

    /**
     * after registration or sales rep need activation
     */
    public function do_activation()
    {
        try {
            if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['csrf']) && $_SESSION['csrf'] == $_POST['csrf'] && isset($_POST['activate']) && $_POST['activate'] == 1) {
                /**
                 * Array ( [csrf] => 9dae52750f9ba4dff63d82a67f293def06182e5f [activate] => 1 [firstName] => Rik [lastName] => Jan [inputEmail] => dhiraj.patra@gmail.com [inputPassword] => 12345678 [confirmPassword] => 12345678 )
                 */

                $first_name = $_POST['firstName'];
                $last_name = $_POST['lastName'];
                $email = $_POST['inputEmail'];
                $password = $_POST['inputPassword'];
                // not required as already validated via jquery
                $conf_password = $_POST['confirmPassword'];
                $status = ACTIVE_USER;

                $p_salt = $this->misc->rand_string(20);
                $site_salt = SECRET; // from config.php
                $salted_hash = hash('sha256', $password . $site_salt . $p_salt);

                // update user data
                $sql_update_user = $this->dbConn->prepare("update users set Name_First = ?, Name_Last = ?, Password = ?, User_Status = ?, psalt = ? where email = ?");
                $value_user = $sql_update_user->execute(array($first_name, $last_name, $salted_hash, $status, $p_salt, $email));

                if ($value_user === true) {
                    $sql = $this->dbConn->prepare("SELECT u.*, rg.id as rgid, r.id as roleid, r.role, s.status, c.code, rg.company_name, rg.industry_id, rg.type, rg.address_id, rg.currency_id, rg.reason_id, rg.referral_registration_id, c.symbol, cm.contacts_limit, cm.deals_limit, cm.sales_rep_limit, cm.special_support FROM users u
                      inner join roles r on r.id = u.User_Roles
                      inner join user_status s on s.id = u.User_Status
                      inner join registrations rg on rg.id = u.registration_id
                      inner join currency c on c.id = rg.currency_id
                      inner join companies cm on cm.registration_id = rg.id
                      group by rgid, u.id
                      having u.Email = ? and u.User_Status = ? limit 1");

                    $sql->execute(array($email, 1));
                    $count = $sql->rowCount();

                    if ($count > 0) {
                        while ($row = $sql->fetch()) {
                            $status = $row['status'];

                            if ($status == 'active') {
                                // get the totals nos for header
                                $header_totals = $this->calculateTotals($row['id'], $row['registration_id'], $row['roleid'], $this->dbConn);

                                //create a cryptographically secure token.
                                $user_token = bin2hex(openssl_random_pseudo_bytes(24));
                                //assign the token to a session variable.
                                $_SESSION['user_token'] = $user_token;

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
                                    'company_name' => $row['company_name'],
                                    'currency_code' => $row['code'],
                                    'currency_symbol' => $row['symbol'],
                                    'header_totals' => $header_totals,
                                    'deals_limit' => $row['deals_limit'],
                                    'contacts_limit' => $row['contacts_limit'],
                                    'sales_rep_limit' => $row['sales_rep_limit'],
                                    'special_support' => ($row['special_support'] == 1 ? true : false),
                                ];

                                $_SESSION['user'] = $user;

                                $this->log = new Log();
                                // remove expiry log for this user
                                $this->log->remove();
                                // write into log
                                $this->log->write();

                                header("location: /sales");
                                exit;
                            }
                        }
                    } else {
                        echo 'Your activation fail. Kindly try again or contact your sales manager.';
                        // later have to make DB based custom error log
                        error_log('Activation fail for ' . $email);
                        exit;
                    }
                }
            }
        } catch (\Exception $exception) {
            $this->misc->log('Login' . __METHOD__, $exception);
        }
    }

    /**
     * when main manager user comes from invitation email link
     * here login form with details will be prepared after all verification done
     */
    public function prepare_activation()
    {
        try {
            if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_SESSION['query_params']['email']) && $_SESSION['query_params']['email'] != '' && isset($_SESSION['query_params']['code']) && $_SESSION['query_params']['code'] != '' && strlen($_SESSION['query_params']['code']) == 64) {

                // decrypt the token
                $email = base64_decode($_SESSION['query_params']['email']);
                $activation_code = $_SESSION['query_params']['code'];
                $activate = true;

                $first_name = null;
                $last_name = null;

                // validate token
                $sql = $this->dbConn->prepare("SELECT u.*, rg.id as rgid, r.id as roleid, r.role, s.status, c.code, rg.company_name, rg.industry_id, rg.type, rg.address_id, rg.currency_id, rg.reason_id, rg.referral_registration_id, c.symbol, cm.contacts_limit, cm.deals_limit, cm.sales_rep_limit, cm.special_support FROM users u
                    inner join roles r on r.id = u.User_Roles
                    inner join user_status s on s.id = u.User_Status
                    inner join registrations rg on rg.id = u.registration_id
                    left join currency c on c.id = rg.currency_id
                    inner join companies cm on cm.registration_id = rg.id
                    group by rgid, u.id
                    having u.Email = ? and u.User_Status = ? limit 1");

                $sql->execute(array($email, PENDING_USER));
                $count = $sql->rowCount();

                if ($count > 0) {
                    while ($row = $sql->fetch()) {
                        $salted_hash_db = $row['Password'];
                        // $p_salt_db = $row['psalt'];
                        // $invitation_code_db = $row['invitation_code'];
                        // echo $salted_hash_db . ' ------- ' . $activation_code;

                        // first time login with activation for sales rep
                        if ($salted_hash_db == $activation_code && $row['User_Roles'] == SALES_REP_ROLE) {

                            // update status and show the form for enter new password
                            $this->key = sha1(microtime());
                            $_SESSION['csrf'] = $this->key;

                            if ($row['Name_First'] != '') {
                                $first_name = $row['Name_First'];
                            }

                            if ($row['Name_Last'] != '') {
                                $last_name = $row['Name_Last'];
                            }

                            // update user table to activate user to login
                            $status = ACTIVE_USER; // activated

                            // direct login of this user
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
                            $sql_logged_in = $this->dbConn->prepare('update users set User_Status = ?, last_logged_in = ?, referral_code = ? where id = ?');
                            $sql_logged_in->execute(array($status, date('Y-m-d H:i:s'), $row['referral_code'], $row['id']));

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
                            ];

                            $_SESSION['user'] = $user;

                            // write into log
                            $this->log->write();

                            // get user's last location
                            $last_uri = '';
                            $result_last_uri = $this->log->get_last_location();

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

                            // after registration first login with activation for sales manager or company owner
                        } elseif ($salted_hash_db == $activation_code && $row['User_Roles'] == MANAGER_ROLE) {
                            // update user table to activate user to login
                            $status = ACTIVE_USER; // activated
                            $sql_update_user = $this->dbConn->prepare("update users set User_Status = ? where email = ?");
                            $value_user = $sql_update_user->execute(array($status, $email));

                            if ($value_user === true) {
                                // check whether user has pipeline already
                                $sql_check_pipeline = $this->dbConn->prepare("select * from user_pipeline where user_id = ?");
                                $sql_check_pipeline->execute(array($row['id']));
                                $count_check_pipeline = $sql_check_pipeline->rowCount();

                                if ($count_check_pipeline == 0) {
                                    // fetch default pipeline stages and positions
                                    $sql_pipeline = $this->dbConn->prepare("select * from default_pipelines where industry = ? order by position");
                                    $sql_pipeline->execute(array($row['industry_id']));
                                    $count_pipeline = $sql_pipeline->rowCount();

                                    if ($count_pipeline > 0) {
                                        // create user pipeline
                                        while ($row_pipeline = $sql_pipeline->fetch()) {
                                            $sql_insert_user_pipeline = $this->dbConn->prepare("insert into user_pipeline(user_id, deal_stage, position, registration_id) value(?, ?, ?, ?)");
                                            $value_user = $sql_insert_user_pipeline->execute(array($row['id'], $row_pipeline['stage'], $row_pipeline['position'], $row['registration_id']));
                                        }
                                    }
                                }
                            }

                            // $response = $this->twig->render('login.html.twig', ['key' => $this->key, 'first_name' => $first_name, 'last_name' => $last_name, 'email' => $email, 'activate' => $activate, 'btn_login' => 'Login']);

                            // return $response;

                            $this->key = sha1(microtime());
                            $_SESSION['csrf'] = $this->key;
                            $_POST = false;
                            $_SESSION['message'] = 'Welcome you have been successfully activated to Esy CRM. Now you can login with your credentials. Check your previous email for username and password.';
                            setcookie("username", $email);
                            setcookie("password", $salted_hash_db);

                            header("location: /login");
                            exit;
                        }
                    }
                } else {
                    $_POST = false;
                    $response = $this->twig->render('error_registration.html.twig');
                    return $response;
                }
            } else {
                $_POST = false;
                $response = $this->twig->render('error_registration.html.twig');
                return $response;
            }
        } catch (\Exception $exception) {
            $this->misc->log('Login' . __METHOD__, $exception);
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

    /**
     * to logout
     */
    public function do_logout()
    {
        try {
            // session_unset();
            // session_destroy();
            // //Redirect them back to the home page or something.
            // header('Location: /');
            // exit;

            //Get the token from the query string.
            $queryStrToken = isset($_GET['token']) ? $_GET['token'] : '';

            //If the token in the query string matches the token in the user's
            //session, then we can destroy the session and log them out.
            if (hash_equals($_SESSION['user_token'], $queryStrToken)) {
                $this->log = new Log();
                // write into log
                $this->log->write();

                //Token is correct. Destroy the session.
                session_unset();
                session_destroy();

                // destroy logged_in cokkie
                setcookie('logged_in', '', time() - 3600);

                //Redirect them back to the home page or something.
                header('Location: /login');
                exit;
            }
        } catch (\Exception $exception) {
            $this->misc->log('Login' . __METHOD__, $exception);
        }
    }

    /**
     * this will process the login and create session as per parameter provided
     * @param  [type] $by [description]
     * @return [type]     [description]
     */
    private function login($by)
    {
        try {

            if (isset($by['email']) && $by['email'] != '') {
                $having = ' u.Email ';
                $value = $by['email'];
            } elseif (isset($by['id']) && $by['id']) {
                $having = ' u.id ';
                $value = $by['id'];
            } else {
                header("location: /login?error=Invalid_email.");
                exit;
            }

            // need to login into session
            $sql = $this->dbConn->prepare("SELECT u.*, rg.id as rgid, r.id as roleid, r.role, s.status, c.code, rg.company_name, rg.industry_id, rg.type, rg.address_id, rg.currency_id, rg.reason_id, rg.referral_registration_id, c.symbol, cm.contacts_limit, cm.deals_limit, cm.sales_rep_limit, cm.special_support FROM users u
                inner join roles r on r.id = u.User_Roles
                inner join user_status s on s.id = u.User_Status
                inner join registrations rg on rg.id = u.registration_id
                left join currency c on c.id = rg.currency_id
                inner join companies cm on cm.registration_id = rg.id
                group by rgid, u.id, cm.id
                having " . $having . " = ?
                limit 1");

            $sql->execute(array($value));
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
                        ];

                        $_SESSION['user'] = $user;

                        // write into log
                        $this->log->write();

                        // for continue login
                        setcookie("logged_in", base64_encode(strval($row['id'])), time() + COOKIE_SET_TIME, "/");

                        // get user's last location
                        $last_uri = '';
                        $result_last_uri = $this->log->get_last_location();

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
                    } else {
                        header("location: /login?error=Invalid_email_or_password_.
						_Or_not_activated_kindly_check_your_email_and_click_on_activation_link.");
                        exit;
                    }
                }
            } else {
                header("location: /login?error=Invalid_email.");
                exit;
            }
        } catch (\Exception $exception) {
            $this->misc->log('Login' . __METHOD__, $exception);
        }
    }

    /**
     * Google SSO login
     *
     * Google_Service_Oauth2_Userinfoplus Object
     *    (
     *        [internal_gapi_mappings:protected] => Array
     *            (
     *                [familyName] => family_name
     *                [givenName] => given_name
     *                [verifiedEmail] => verified_email
     *            )
     *
     **            [email] => dhiraj.patra@gmail.com
     *            [familyName] => Patra
     *            [gender] =>
     *            [givenName] => Dhiraj
     *            [hd] =>
     **            [id] => 117305352517568057882
     *            [link] =>
     *            [locale] => en-GB
     *            [name] => Dhiraj Patra
     *            [picture] => https://lh3.googleusercontent.com/a-/AAuE7mBpwx6ULqlNytHP8DX4T1pa_3qlllefYmsRpY2CKQ
     *            [verifiedEmail] => 1
     *            [modelData:protected] => Array
     *                (
     *                    [verified_email] => 1
     *                    [given_name] => Dhiraj
     *                    [family_name] => Patra
     *                )
     *
     *            [processed:protected] => Array
     *                (
     *                )
     *
     *        )
     */
    public function do_google_sso_login()
    {
        try {
            // init configuration
            $clientID = GOOGLE_OAUTH_SSO_CLIENT_ID;
            $clientSecret = GOOGLE_OAUTH_SSO_CLIENT_SECRET;

            // create Client Request to access Google API
            $client = new Google_Client();
            // $client->setAuthConfig('/credentials_google_sso_login.json');
            $client->setClientId($clientID);
            $client->setClientSecret($clientSecret);
            $client->setRedirectUri($this->google_auth_redirect_url);
            $client->addScope("email");
            $client->addScope("profile");

            // authenticate code from Google OAuth Flow
            if (isset($_GET['code'])) {
                $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
                $client->setAccessToken($token);

                // get profile info
                $google_oauth = new \Google_Service_Oauth2($client);
                $google_account_info = $google_oauth->userinfo->get();
                // p($google_account_info); exit;
                $email = $google_account_info->email;
                $name = $google_account_info->name;

                // need to login into session
                $by = [
                    'email' => $email,
                ];
                // calling pvt fucntion to login process
                $this->login($by);

                // now you can use this profile info to create account in your website and make user logged in.
            } else {
                header("location: /login?error=Invalid_email.");
                exit;
            }
        } catch (\Exception $exception) {
            $this->misc->log('Login' . __METHOD__, $exception);
        }
    }
}
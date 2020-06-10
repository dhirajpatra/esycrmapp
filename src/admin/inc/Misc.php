<?php

declare (strict_types = 1);

namespace App\admin\inc;

use App\admin\inc\PhpMailerCrm;
use App\admin\inc\SendgridMailer;
use Monolog\Handler\FirePHPHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class Misc
{
    private $logger;
    private $loggedin_user_id;
    private $phpMailerObj;
    private $sendGridMailerObj;

    public function __construct()
    {

        $this->loggedin_user_id = isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : null;

        // mailer
        $this->phpMailerObj = new PhpMailerCrm();
        $this->sendGridMailerObj = new SendgridMailer();

        // Create the logger
        $log_file_name = date('dmY') . '_logger';
        $this->logger = new Logger($log_file_name);

        // Now add some handlers
        $this->logger->pushHandler(new StreamHandler(__DIR__ . '/../../../logs/' . $log_file_name . '.log', Logger::DEBUG));
        $this->logger->pushHandler(new FirePHPHandler());
    }

    /**
     * generate random string
     */
    public function rand_string($length)
    {
        $str = "";
        $chars = "subinsblogabcdefghijklmanopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_@#$";
        $size = strlen($chars);
        for ($i = 0; $i < $length; $i++) {
            $str .= $chars[rand(0, $size - 1)];
        }
        return $str;
    }

    /**
     * generate random number
     */
    public function rand_number($length)
    {
        $str = "";
        $chars = "0123456789";
        $size = strlen($chars);
        for ($i = 0; $i < $length; $i++) {
            $str .= $chars[rand(0, $size - 1)];
        }
        return $str;
    }

    //For backward compatibility with the hash_equals function.
    //The hash_equals function was released in PHP 5.6.0.
    //It allows us to perform a timing attack safe string comparison.
    public function hash_equals($str1, $str2)
    {
        if (strlen($str1) != strlen($str2)) {
            return false;
        } else {
            $res = $str1 ^ $str2;
            $ret = 0;
            for ($i = strlen($res) - 1; $i >= 0; $i--) {
                $ret |= ord($res[$i]);
            }
            return !$ret;
        }
    }

    /**
     * this function will set all .env values as a getenv
     */
    public function set_env()
    {
        $file = fopen("../src/.env", "r");
        while (!feof($file)) {
            $line = trim(fgets($file));
            if ($line != '') {
                $line = explode('=', $line);
                $line[1] = str_replace("'", "", $line[1]);
                putenv("$line[0]=$line[1]");
                if (!defined($line[0])) {
                    define($line[0], $line[1]);
                }
            }
        }
        fclose($file);

        // IP based session
        $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'];
        !defined('USER_IP') ? define('USER_IP', $_SESSION['ip']) : false;
        $IP = getenv("REMOTE_ADDR");
        !defined('IP') ? define('IP', $IP) : '';

        return true;
    }

    /**
     * it will check session
     */
    public function check_session()
    {
        // $actual_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

        if (!isset($_SESSION['user']) && ($_SERVER['REQUEST_URI'] != '/' && $_SERVER['REQUEST_URI'] != '/login' && $_SERVER['REQUEST_URI'] != '/register') && $_SERVER['REQUEST_URI'] != '/send_login_code' && $_SERVER['REQUEST_URI'] != '/forgot_password' && $_SERVER['REQUEST_URI'] != '/change_password' && $_SERVER['REQUEST_URI'] != '/activation' && $_SERVER['REQUEST_URI'] != '/thanks_registration' && $_SERVER['REQUEST_URI'] != '/thanks_forgot_password' && $_SERVER['REQUEST_URI'] != '/manager/get_chart_data_for_dashboard' && $_SERVER['REQUEST_URI'] != '/sales/get_chart_data_for_dashboard') {
            // file_put_contents('log.txt', "\n" . date('d-m-Y H:i:s') . ': ' . $_SERVER['REQUEST_URI'] . json_encode($_SESSION) . json_encode($_POST), FILE_APPEND);

            header("location: /login");
            exit;
        }

        if (isset($_SESSION['user']['id']) && ($_SERVER['REQUEST_URI'] == '/login' || $_SERVER['REQUEST_URI'] == '/register')) {
            if ($_SESSION['user']['role_id'] == SUPER_ADMIN_ROLE) {
                header('location: /manager/super');
                exit;
            } elseif ($_SESSION['user']['role_id'] == MANAGER_ROLE) {
                header('location: /manager');
                exit;
            } elseif ($_SESSION['user']['role_id'] == SALES_REP_ROLE) {
                header('location: /sales');
                exit;
            }
        }

        $uris = explode('/', $_SERVER['REQUEST_URI']);
        // to check logout uri
        $uri = $uris;
        $last = array_pop($uri);
        preg_match('/logout/', $last, $matches);

        // if not logout
        if (empty($matches)) {
            if (!empty($_SESSION['user']) && $_SESSION['user']['role_id'] != SUPER_ADMIN_ROLE && $_SESSION['user']['role_id'] != MANAGER_ROLE && (in_array('manager', $uris))) {
                header('Location: /sales');
                exit;
            }
        }

        return false;
    }

    /**
     * this will write into error log
     */
    public function log($from, $exception)
    {

        $this->logger->info('From: ' . $from, array('exception' => $exception), ['user_id' => $this->loggedin_user_id]);
    }

/**
 * this will send mail via mailer applicaiton and SMTP using for the application
 * @param  [type] $to       [description]
 * @param  [type] $reply    [description]
 * @param  [type] $reply_to [description]
 * @param  [type] $subject  [description]
 * @param  [type] $body     [description]
 * @return [type]           [description]
 */
    public function send_mail($to, $reply, $reply_to, $subject, $body)
    {
        try {

            switch (MAIL_SMTP) {
                case 'sendgrid':
                    $this->sendGridMailerObj->send($to, $reply, $reply_to, $subject, $body);
                    break;

                case 'gmail':
                    $this->phpMailerObj->send($to, $reply, $reply_to, $subject, $body);
                    break;

                default:
                    $this->phpMailerObj->send($to, $reply, $reply_to, $subject, $body);
                    break;
            }

            return true;
        } catch (\Exception $exception) {
            $this->log('Login' . __METHOD__, $exception);
        }
    }
}
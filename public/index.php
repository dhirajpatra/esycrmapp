<?php

declare (strict_types = 1);

// Tell PHP that we're using UTF-8 strings until the end of the script
mb_internal_encoding('UTF-8');
ini_set('default_charset', 'utf-8');
// Tell PHP that we'll be outputting UTF-8 to the browser
mb_http_output('UTF-8');

// each client should remember their session id for EXACTLY 1 day
ini_set('session.gc_maxlifetime', '86400');
ini_set('session.gc_divisor', '1');
ini_set('session.gc_probability', '1');
ini_set('session.cookie_lifetime', '0');
session_set_cookie_params(86400);
// ini_set('session.save_path', '../tmp');
ini_set('session.cookie_httponly', '1');
ini_set('session.use_only_cookies', '1');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) {
    ini_set('session.cookie_secure', '1'); // only for https
}
// start application session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set("Asia/Calcutta");

# need to chage as per the host and domain
if (!defined('HOST')) {
    define('HOST', null);
}
$host = null !== HOST ? HOST : 'esycrmapp.lan';
preg_match('/' . $host . '/', $_SERVER['HTTP_HOST'], $match);
if (in_array($host, $match)) {
    // only for dev
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    error_reporting(0);
}

$log_file = $_SERVER['DOCUMENT_ROOT'] . '/../log.txt';

// // access log for test
// if (date('Y-m-d') > date('Y-m-d', filemtime($log_file))) {
//     file_put_contents($log_file, "\n" . date('d-m-Y H:i:s') . ': URI: ' . $_SERVER['REQUEST_URI'] . "\t SESSION: " . json_encode($_SESSION) . "\t POST: " . json_encode($_POST) . "\t GET: " . json_encode($_GET));
// } else {
//     file_put_contents($log_file, "\n" . str_repeat("--", 60), FILE_APPEND);
//     file_put_contents($log_file, "\n" . date('d-m-Y H:i:s') . ': URI: ' . $_SERVER['REQUEST_URI'] . "\t SESSION: " . json_encode($_SESSION) . "\t POST: " . json_encode($_POST) . "\t GET: " . json_encode($_GET), FILE_APPEND);
// }

use App\admin\inc\Conf;
use App\Classes;
use App\Routing;
use Middlewares\FastRoute;
use Middlewares\RequestHandler;
use Narrowspark\HttpEmitter\SapiEmitter;
// use Zend\Diactoros\Response;
use Relay\Relay;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Zend\Diactoros\ServerRequestFactory;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$conf = new Conf();

// twig template loader
$loader = new FilesystemLoader(dirname(__DIR__) . '/src/templates');
$twig = new Environment($loader);

$container = Classes::registerInContainer();

$route = new Routing();
$routes = $route->routes();

$middlewareQueue[] = new FastRoute($routes);
$middlewareQueue[] = new RequestHandler($container);

/** @noinspection PhpUnhandledExceptionInspection */
$requestHandler = new Relay($middlewareQueue);
$response = $requestHandler->handle(ServerRequestFactory::fromGlobals());

$emitter = new SapiEmitter();
/** @noinspection PhpVoidFunctionResultUsedInspection */
return $emitter->emit($response);

/**
 * this will print for testing
 * @return [type] [description]
 */
function p($arr)
{
    echo '<pre>';
    print_r($arr);
    echo '</pre>';

    // write into log
    // $log_file = '../log.txt';
    // file_put_contents($log_file, "\n" . date('d-m-Y H:i:s') . $arr);
}
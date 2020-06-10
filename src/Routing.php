<?php

declare (strict_types = 1);

namespace App;

use App\admin\Login;
use App\admin\manager\Manager;
use App\admin\manager\Superadmin;
use App\admin\Register;
use App\admin\sales\Sales;
use App\admin\Search;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use function FastRoute\simpleDispatcher;

class Routing
{
    private $routes = null;

    public function __construct()
    {}

    // add all routes
    public function routes()
    {
        $this->routes = simpleDispatcher(function (RouteCollector $r) {

            // only for not logged in user
            if (!isset($_SESSION['user']['id'])) {
                // if someone open a browser after a while
                $r->get('/', Register::class);
                $r->get('/register', [Register::class, 'show_registration']);
                $r->post('/register', [Register::class, 'do_registration']);
                $r->get('/forgot-password', [Register::class, 'forgot_password']);
                $r->post('/forgot-password', [Register::class, 'forgot_password_process']);
                $r->get('/change_password/{email}/{code}', [Register::class, 'change_password']);
                $r->post('/change_password', [Register::class, 'change_password_process']);
                $r->get('/thanks_forgot_password', [Register::class, 'thanks_forgot_password']);
                $r->get('/thanks_change_password', [Register::class, 'thanks_change_password']);
                $r->get('/registration', Sales::class);
                $r->get('/thanks_registration', [Register::class, 'thanks_registration']);
                $r->get('/activation/{email}/{code}', [Login::class, 'prepare_activation']);
                $r->post('/activation', [Login::class, 'do_activation']);
                $r->post('/send_login_code', [Login::class, 'send_login_code']);
                $r->get('/login[/{error}]', Login::class);
                $r->post('/login', [Login::class, 'do_login']);
                $r->get('/unsubscribe/{email}', [Register::class, 'unsubscribe']);
                $r->get('/google_sso_login', [Login::class, 'do_google_sso_login']);
            }
            // only for logged in user
            if (isset($_SESSION['user']['id'])) {
                // if someone open a browser after a while
                $r->get('/', Sales::class);
                $r->get('/logout', [Login::class, 'do_logout']);
                $r->get('/manager', Manager::class);
                $r->get('/manager/salesrep', [Manager::class, 'salesrep']);
                $r->get('/manager/salesrep/add', [Manager::class, 'add_salesrep']);
                $r->post('/manager/salesrep/add', [Manager::class, 'add_salesrep_process']);
                $r->post('/manager/analytics', [Manager::class, 'get_analytics_date_range']);
                $r->get('/sales', Sales::class);
                $r->get('/sales/deals', [Sales::class, 'deals']);
                $r->post('/sales/deals', [Sales::class, 'add_deal']);
                $r->post('/sales/edit_deal', [Sales::class, 'edit_deal']);
                $r->post('/sales/todo', [Sales::class, 'add_todo']);
                $r->post('/sales/move', [Sales::class, 'move_deal']);
                $r->post('/sales/dealwon', [Sales::class, 'deal_won']);
                $r->post('/sales/deallost', [Sales::class, 'deal_lost']);
                $r->post('/sales/get_deal_details', [Sales::class, 'get_deal_details']);
                $r->get('/sales/contacts', [Sales::class, 'contacts']);
                $r->post('/sales/contacts', [Sales::class, 'add_contact']);
                $r->post('/sales/contact', [Sales::class, 'edit_contact']);
                $r->post('/sales/contact_lookup', [Sales::class, 'contact_lookup']);
                $r->get('/sales/analytics', [Sales::class, 'analytics']);
                $r->post('/sales/analytics', [Sales::class, 'get_analytics_date_range']);
                $r->get('/sales/customer_won', [Sales::class, 'customer_won']);
                $r->post('/sales/add_comment', [Sales::class, 'add_comment']);
                $r->post('/sales/edit_comment', [Sales::class, 'edit_comment']);
                $r->post('/sales/get_note_comments', [Sales::class, 'get_note_comments']);
                $r->post('/sales/contacts_csv_upload', [Sales::class, 'contacts_csv_upload']);
                $r->post('/sales/show_notification', [Sales::class, 'show_notification']);
                $r->post('/manager/show_notification', [Manager::class, 'show_notification']);
                $r->post('/manager/settings', [Manager::class, 'pipeline_settings']);
                $r->get('/sales/connect_google_calendar', [Sales::class, 'connect_google_calendar']);
                $r->get('/sales/connect_gmail', [Sales::class, 'connect_gmail']);
                $r->post('/sales/send_task_email', [Sales::class, 'send_task_email']);
                $r->post('/sales/get_task_details', [Sales::class, 'get_task_details']);
                $r->post('/sales/edit_task', [Sales::class, 'edit_task']);
                $r->post('/sales/delete_task', [Sales::class, 'delete_task']);
                $r->post('/sales/get_deals', [Sales::class, 'get_deals']);
                $r->post('/sales/change_password', [Sales::class, 'change_password']);
                $r->post('/sales/update_login_with_code', [Sales::class, 'update_login_with_code']);
                $r->get('/manager/super', Superadmin::class);
                $r->get('/manager/super/cache', [Superadmin::class, 'cache_clean']);
                $r->get('/manager/super/companies', [Superadmin::class, 'companies']);
                $r->get('/manager/super/mails', [Superadmin::class, 'mails']);
                $r->get('/manager/super/bulk/mails', [Superadmin::class, 'bulk_mails']);
                $r->post('/manager/super/bulk/mails', [Superadmin::class, 'send_bulk_mails']);
                $r->post('/manager/super/mail', [Superadmin::class, 'add_mail']);
                $r->post('/manager/super/update_mail', [Superadmin::class, 'update_mail']);
                $r->post('/manager/super/support', [Superadmin::class, 'supports']);
                $r->post('/manager/super/status_update_company', [Superadmin::class, 'status_update_company']);
                $r->post('/manager/super/analytics', [Superadmin::class, 'get_analytics_date_range']);
                $r->post('/manager/super/get_chart_data_for_dashboard', [Superadmin::class,
                    'get_chart_data_for_dashboard']);
                $r->get('/search/{query:\w+}', Search::class);
            }

            // for chat preparation in front it need to call at the time of loading
            $r->post('/sales/get_chart_data_for_dashboard', [Sales::class, 'get_chart_data_for_dashboard']);
            $r->post('/manager/get_chart_data_for_dashboard', [Manager::class, 'get_chart_data_for_dashboard']);

        });

        // Fetch method and URI from somewhere
        $httpMethod = $_SERVER['REQUEST_METHOD'];
        $uri = $_SERVER['REQUEST_URI'];

        // Strip query string (?foo=bar) and decode URI
        if (false !== $pos = strpos($uri, '?')) {
            $uri = substr($uri, 0, $pos);
        }
        $uri = rawurldecode($uri);

        $routeInfo = $this->routes->dispatch($httpMethod, $uri);

        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:

                $logged_in = isset($_COOKIE['logged_in']) ? $_COOKIE['logged_in'] : null;

                // if already logged in then directly transfered
                if (isset($logged_in) && strlen($logged_in) <= 64 && isset($_SESSION['user'])) {
                    header('location: /sales');
                } else {
                    session_destroy();
                    header('location: /404.html');
                }
                exit;
            case Dispatcher::METHOD_NOT_ALLOWED:
                $allowedMethods = $routeInfo[1];

                break;
            case Dispatcher::FOUND:
                $handler = $routeInfo[1];
                $vars = $routeInfo[2];

                $_REQUEST['vars'] = $vars;
                $_SESSION['query_params'] = $vars;

                break;
        }

        return $this->routes;
    }
}
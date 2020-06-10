<?php

declare (strict_types = 1);

namespace App\admin\inc;

use App\admin\inc\Misc;

/**
 * this class for conf settings
 */
class Conf
{
    /**
     * set all getenv
     */
    public function __construct()
    {
        try {
            $misc = new Misc();
            $misc->set_env();

            // setting all getenv as constant
            // $envs = getenv();
            // foreach ($envs as $key => $val) {
            //     if (!defined($key)) {
            //         define($key, $val);
            //     }
            // }

        } catch (\Throwable $th) {
            //throw $th;
        }
    }

    /**
     * get siteturl details
     */
    private function siteURL()
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $domainName = $_SERVER['HTTP_HOST'] . '/';
        return $protocol . $domainName;
    }
}

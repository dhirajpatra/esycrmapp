<?php

declare (strict_types = 1);

namespace App;

use App\admin\inc\Misc;
use App\admin\inc\PhpMailerCrm;
use App\admin\inc\SendgridMailer;
use App\admin\Login;
use App\admin\manager\Manager;
use App\admin\manager\Superadmin;
use App\admin\Register;
use App\admin\sales\Sales;
use App\admin\Search;
use DI\ContainerBuilder;
use function DI\create;
use Psr\Container\ContainerInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class Classes
{
    private static $containerBuilder = null;
    private static $container = null;
    private static $twig = null;

    public function __construct()
    {

    }

    public static function setContainerBuilder()
    {
        self::$containerBuilder = new ContainerBuilder();
        self::$containerBuilder->useAutowiring(true);
        self::$containerBuilder->useAnnotations(true);
    }

    public static function setContainer($container)
    {
        self::$container = $container;
    }

    public static function setTwig($twig)
    {
        self::$twig = $twig;
    }

    /**
     * create container of all classes
     */
    public static function registerInContainer(): ContainerInterface
    {
        if (self::$container == null) {

            self::setContainerBuilder();

            // twig template loader
            $loader = new FilesystemLoader(dirname(__DIR__) . '/src/templates');
            self::setTwig(new Environment($loader));

            self::$containerBuilder->addDefinitions([
                Register::class => create(Register::class)
                    ->constructor(self::$twig),
                Login::class => create(Login::class)
                    ->constructor(self::$twig),
                PhpMailerCrm::class => create(PhpMailerCrm::class)
                    ->constructor(),
                SendgridMailer::class => create(SendgridMailer::class)
                    ->constructor(),
                Manager::class => create(Manager::class)
                    ->constructor(self::$twig),
                Sales::class => create(Sales::class)
                    ->constructor(self::$twig),
                Misc::class => create(Misc::class)
                    ->constructor(),
                Superadmin::class => create(Superadmin::class)
                    ->constructor(self::$twig),
                Search::class => create(Search::class)
                    ->constructor(self::$twig),
            ]);

            /** @noinspection PhpUnhandledExceptionInspection */
            self::setContainer(self::$containerBuilder->build());
        }

        return self::$container;
    }
}

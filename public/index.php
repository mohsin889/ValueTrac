<?php

/*
 * BOOTSTRAPPING
 */

error_reporting(E_ERROR | E_PARSE);

require __DIR__ . '/../vendor/autoload.php';

spl_autoload_register(function ($class) {
    $file = __DIR__ . '/../' . str_replace('\\', '/', $class) . '.php';

    if (file_exists($file)) include $file;
});

use DI\Container;
use Slim\Factory\AppFactory;
use app\middleware\Middlewares;
use exceptions\Exceptions;
use config\Routes;
use Dotenv\Dotenv;

/*
 * CONFIG SETTINGS
 */

$dotenv = Dotenv::create(__DIR__ . '/../');
$dotenv->load();

session_cache_limiter(false);
session_start();

$container = new Container();
AppFactory::setContainer($container);

$app = AppFactory::create();

Exceptions::register($app);
Middlewares::register($app);
Routes::register($app);

$app->run();
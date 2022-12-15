<?php


namespace app\middleware;

use app\middleware\before\JSONParsedBodyMiddleware;
use app\middleware\before\CustomAuthMiddleware;
use Slim\App;
use Tuupola\Http\Factory\ResponseFactory;
use Tuupola\Middleware\JwtAuthentication;


class Middlewares
{
    public static function register(App &$app) {
        $app->add(new JSONParsedBodyMiddleware());
    }
}
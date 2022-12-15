<?php

namespace config;

use app\controllers\ValueTracController;
use app\controllers\HomebaseController;
use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class Routes
{
    public static function register(App &$app) {
        $app->post('/homebase/webhook', HomebaseController::class.':handle');

        $app->any('/webhook/', ValueTracController::class.':webhook');

        $app->get('/[{path:.*}]', function( Request $request, Response $response, $path = null)
        {
            $response->getBody()
                ->write(json_encode(["ERROR" => key_exists('path',$path) ? "path '{$path["path"]}' not found" : "path '/' not found"],
                    JSON_UNESCAPED_SLASHES));

            return $response->withHeader('Content-Type', 'application/json')
                ->withStatus(404);
        });
    }
}



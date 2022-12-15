<?php


namespace exceptions;

use Slim\App;

class Exceptions
{
    public static function register(App &$app)
    {
        $callableResolver = $app->getCallableResolver();
        $responseFactory = $app->getResponseFactory();

        $errorHandler = new HttpErrorHandler($callableResolver, $responseFactory);

        $errorMiddleware = $app->addErrorMiddleware(getenv('DISPLAY_ERROR_DETAILS'), false, false);
        $errorMiddleware->setDefaultErrorHandler($errorHandler);
    }
}
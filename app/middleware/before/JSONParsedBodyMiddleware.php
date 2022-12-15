<?php


namespace app\middleware\before;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class JSONParsedBodyMiddleware
{
    public function __invoke(Request $request, RequestHandler $handler)
    {
        if (empty($request->getParsedBody()) &&
            in_array('application/json', $request->getHeader('Content-Type'))) {
            $request = $request->withParsedBody(json_decode($request->getBody()));
        }

        return $handler->handle($request);
    }
}
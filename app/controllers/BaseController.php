<?php


namespace app\controllers;

use app\factories\PdoFactory;
use Psr\Http\Message\ResponseInterface as Response;

class BaseController
{
    protected $pdo;

    public function __construct()
    {
        $this->pdo = PdoFactory::createValueTracPdo();
    }

    /**
     * @param Response $response, array $payload, $httpCode
     * converts response into json readable response
    */
    public function json(Response $response, $payload, $httpCode = 200)
    {
        $status = [
            'status' => $httpCode === 200 ? 'OK' : 'error'
        ];

        $response->getBody()->write(json_encode(array_merge($status, $payload),JSON_UNESCAPED_SLASHES));

        return $response->withHeader('Content-Type', 'application/json')
            ->withStatus($httpCode);
    }
}
<?php

namespace app\services;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Psr\Http\Message\ResponseInterface as Response;


class BaseService
{
    public function __construct() {
    }

    protected function isHttpSuccessful(Response $response) {
        return $response->getStatusCode() == 200;
    }

    protected function responseJsonExtractor(Response $response) {
        $response->getBody()->rewind();
        $res = json_decode($response->getBody()->getContents());
        if(!isset($res))
        {
            $res = json_decode($response->getBody()->__toString());
        }
        return isset($res->data) ? $res->data : $res;
    }

    protected function responseDocumentExtractor(Response $response) {
        return $response->getBody()->getContents();
    }

    protected function throwClientException(ClientException $exception)
    {
        throw new \Exception($exception->getResponse()->getBody()->getContents());
    }

    protected function throwServerException(ServerException $exception)
    {
        throw new \Exception($exception->getResponse()->getBody()->getContents());
    }
}
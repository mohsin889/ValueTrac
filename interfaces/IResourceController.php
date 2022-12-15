<?php


namespace interfaces;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

interface IResourceController
{
    public function get(Request $request, Response $response) : Response;
    public function fetch(Request $request, Response $response, array $args) : Response;
    public function create(Request $request, Response $response) : Response;
    public function update(Request $request, Response $response, array $args) : Response;
    public function delete(Request $request, Response $response, array $args) : Response;
}
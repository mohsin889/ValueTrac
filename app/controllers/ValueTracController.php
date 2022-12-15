<?php

namespace app\controllers;


use app\CommonMethods\HttpCall;
use app\factories\ValueTracFactory;
use app\models\response\ActiveResponse;
use app\services\ValueTracService;
use app\services\WebhookService;
use app\factories\LoggerFactory;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ValueTracController extends BaseController
{
    protected $valueTracService;
    protected $log;

    public function __construct()
    {
        parent::__construct();
        $this->valueTracService = new ValueTracService($this->pdo);
        $this->log = LoggerFactory::createLogger('ValueTracController');
    }

    public function webhook(Request $request, Response $response, array $args)
    {
        $webhook = new WebhookService();
        $body = $request->getParsedBody();
        $webhook->log->info("Webhook Event: ".json_encode($body));
        $webhook->handle(ValueTracFactory::generateEvent($body));

        return $this->json($response,["Status" => 1, "Message" => "Notification Received"]);
    }
}
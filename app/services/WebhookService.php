<?php

namespace app\services;

const NEW_ORDER = 'GetOrder';
const ORDER_STATUS = 'GetOrderStatus';
const DOCUMENT = 'GetDocument';
const NOTE = 'GetNote';

use app\factories\LoggerFactory;
use app\factories\PdoFactory;
use app\models\response\Event;

class WebhookService
{
    public $log;
    private $orderService;

    public function __construct(){
        $this->log = LoggerFactory::createLogger('WebhookService');

        $valueTracPdo = PdoFactory::createValueTracPdo();

        $homebasePdo = PdoFactory::createHomeBasePdo();

        $this->orderService = new OrderService($valueTracPdo, $homebasePdo);
    }

    public function handle(Event $event)
    {
        switch($event->type) {
            case NEW_ORDER:
                $this->orderService->createOrder($event);
                break;
            case NOTE:
                $this->orderService->addNewNote($event);
                break;
            case DOCUMENT:
                $this->orderService->addNewDocument($event);
                break;
            case ORDER_STATUS:
                $this->orderService->cancelOrder($event);
                break;
        }
    }
}
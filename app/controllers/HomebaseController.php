<?php

namespace app\controllers;

use app\factories\LoggerFactory;
use app\services\ValueTracService;
use app\utility\ObjectExt;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class HomebaseController extends BaseController
{
    private $log;
    private $appShield;

    public function __construct()
    {
        parent::__construct();

        $this->log = LoggerFactory::createLogger('HomebaseController');
        $this->appShield = new ValueTracService($this->pdo);
    }

    public function handle(Request $request, Response $response, array $args)
    {
        $body = $request->getParsedBody();
        $this->log->info("Homebase Event: ".json_encode($body));
        $results = $this->pdo->query("SELECT * FROM orders WHERE homebase_order_id = :orderId", [":orderId" => $body->data->orderId]);

        if (empty($results)) {
            throw new \Exception("No order by ID ({$body->data->orderId}) was found.");
        }

        $order = $results[0];

        $result = null;

        switch ($body->event) {
            case 'UploadDocument':
                if (ObjectExt::hasValidProperty($body->data, 'appraisal')) {
                    $files['pdfFile'] = $body->data->appraisal;
                }

                if (ObjectExt::hasValidProperty($body->data, 'xml')) {
                    $files['xmlFile'] = $body->data->xml;
                }

                if (ObjectExt::hasValidProperty($body->data, 'invoice')) {
                    $files['invoiceFile'] = $body->data->invoice;
                }

                if (ObjectExt::hasValidProperty($body->data, 'airCert')) {
                    $files['airCertFile'] = $body->data->airCert;
                }

                $result = $this->appShield->uploadDocument($order->appraisal_shield_id,$files);
                break;
            case 'InspectionSet':
                $result = $this->appShield->setInspection($order->appraisal_shield_id, $body->data->inspection_date);
                break;

            case 'AddComment':
                $result = $this->appShield->addComment($order->appraisal_shield_id, $body->data->comment);
                break;
            case 'DeadlineSet':
                $result = $this->appShield->setDeadline($order->appraisal_shield_id, $body->data->deadline, $body->data->reason);
                break;
        }

        return $this->json($response, [
            'data' => $result
        ]);
    }
}
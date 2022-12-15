<?php

namespace app\services;

use app\CommonMethods\HttpCall;
use app\factories\LoggerFactory;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class ValueTracService extends BaseService
{
    // APPRAISAL SHIELD ENV VARIABLES
    protected $baseUri;
    protected $clientKey;
    protected $username;
    protected $password;
    protected $token;
    protected $apiKey;
    protected $options;

    // GUZZLE
    protected $client;

    // PDO
    protected $pdo;

    private $log;

    public function __construct(Pdo $pdo)
    {
        parent::__construct();

        $this->baseUri = getenv('APPRAISAL_SHIELD_URL');
        $this->clientKey = getenv('APPRAISAL_SHIELD_API');
        $this->username = getenv('APPRAISAL_SHIELD_USER_ID');
        $this->password = getenv('APPRAISAL_SHIELD_PASSWORD');

        $this->pdo = $pdo;

        $this->log = LoggerFactory::createLogger('AppraisalShieldService');

        $this->client = $this->initializeGuzzleClient();
    }

    private function initializeGuzzleClient(): Client
    {
        $baseUri = $this->baseUri.'/'.$this->clientKey.'/';
        $this->options = ['base_uri' => $baseUri, 'headers' => ['Content-Type' => 'application/json']];

        if(!$_SESSION['Appraisal_Shield_APIKEY'] || ($_SESSION['Appraisal_Shield_Time'] < (new \DateTime())) || ($_SESSION['Appraisal_Shield_Time']->diff(new \DateTime())->format('%H') <= 1))
        {
            (new HttpCall())->login();
        }

        $this->apiKey = $_SESSION['Appraisal_Shield_APIKEY'];

        return new Client($this->options);
    }

    public function getOrder($orderId)
    {
        try{
            $response = $this->client->get('GetOrder/'.$this->apiKey.'/'.$orderId);
        } catch (ClientException $exception) {
            $this->log->error('Error Getting Order: Code: '. $exception->getCode() . 'Message:' . $exception->getMessage());
            $this->throwClientException($exception);
        }

        if(!$this->isHttpSuccessful($response)) {
            return null;
        }

        $response = $this->responseJsonExtractor($response);

        return $response;
    }

    public function acceptOrder($orderId)
    {
        try{
            $payload = [
                'apiKey' => $this->apiKey,
                'encryptedAppraisalID' => $orderId,
                'orderResponseID' => '1'
            ];

            $response = $this->client->post("orderResponse", [
                'body' => json_encode($payload)
            ]);
        } catch (ClientException $exception) {
            $this->log->error('Error accepting order: Code: '. $exception->getCode() . 'Message:' . $exception->getMessage());
            $this->throwClientException($exception);
        }

        if (!$this->isHttpSuccessful($response)) {
            return null;
        }

        return $this->responseJsonExtractor($response);
    }

    public function uploadDocument($orderId, $files)
    {
        try {
            if (isset($files['pdfFile'])) {
                $payload = [
                    'encryptedAppraisalID' => $orderId,
                    'apiKey' => $this->apiKey,
                    'documentTypeID' => '2',
                    'fileName' => 'Appraisal.pdf',
                    'fileData' => $files['pdfFile']
                ];

                $resp = $this->uploadDocumentApiCall($payload);
            }

            if (isset($files['xmlFile'])) {
                $payload = [
                    'encryptedAppraisalID' => $orderId,
                    'apiKey' => $this->apiKey,
                    'documentTypeID' => '2',
                    'fileName' => 'Appraisal.xml',
                    'fileData' => $files['xmlFile']
                ];

                $resp = $this->uploadDocumentApiCall($payload);
            }

            if (isset($files['invoiceFile'])) {
                $payload = [
                    'encryptedAppraisalID' => $orderId,
                    'apiKey' => $this->apiKey,
                    'documentTypeID' => '4',
                    'fileName' => 'Invoice.pdf',
                    'fileData' => $files['invoiceFile']
                ];

                $resp = $this->uploadDocumentApiCall($payload);
            }

            if (isset($files['airCertFile'])) {
                $payload = [
                    'encryptedAppraisalID' => $orderId,
                    'apiKey' => $this->apiKey,
                    'documentTypeID' => '1',
                    'fileName' => $files['airCertFile']->file_name,
                    'fileData' => $files['airCertFile']->file
                ];

                $resp = $this->uploadDocumentApiCall($payload);
            }
        } catch (ClientException $ex) {
            $this->log->error('Error Uploading Document: Code: '. $ex->getCode() . 'Message:' . $ex->getMessage());
            $this->throwClientException($ex);
        }

        return $resp;
    }

    public function uploadDocumentApiCall($payload)
    {
        try{
            $response = $this->client->post("document", [
                'body' => json_encode($payload)
            ]);
        } catch (ClientException $exception) {
            $this->throwClientException($exception);
        }

        if (!$this->isHttpSuccessful($response)) {
            return null;
        }

        return $this->responseJsonExtractor($response);
    }

    public function setInspection($orderId, $inspectionDate)
    {
        $payload = [
            'encryptedAppraisalID' => $orderId,
            'apiKey' => $this->apiKey,
            'inspectionDate'=>date("d/m/Y", strtotime($inspectionDate))
        ];

        try{
            $response = $this->client->post("scheduleInspection", [
                'body' => json_encode($payload)
            ]);
        } catch (ClientException $exception) {

            $this->throwClientException($exception);
        }

        if (!$this->isHttpSuccessful($response)) {
            return null;
        }

        return $this->responseJsonExtractor($response);
    }

    public function addComment($orderId,$comment)
    {
        $payload = [
            'encryptedAppraisalID' => $orderId,
            'apiKey' => $this->apiKey,
            'noteString' => $comment,
        ];

        try {
            $response = $this->client->post('notes', [
                'body' => json_encode($payload)
            ]);
        } catch (ClientException $exception) {
            $this->log->error('Error adding comment: '. $exception->getCode() . 'Message:' . $exception->getMessage());
            $this->throwClientException($exception);
        }

        if (!$this->isHttpSuccessful($response)) {
            return null;
        }

        return $this->responseJsonExtractor($response);

    }

    public function setDeadline($orderId, $deadline, $reason) {
        $date = Carbon::parse($deadline)->format('d/m/Y');
        $payload = [
            "encryptedAppraisalId" => $orderId,
            "apiKey" => $this->apiKey,
            "dueDateUpdate" => $date,
            "feeUpdateMessage" => $reason
        ];

        try {
            $response = $this->client->post('deliveryFeeUpdate',[
                'body' => json_encode($payload)
            ]);
        } catch (ClientException $ex) {
            $this->log->error('Error updating Deadline: '. $ex->getCode() . 'Message:' . $ex->getMessage());
            $this->throwClientException($ex);
        }

        if (!$this->isHttpSuccessful($response)) {
            return null;
        }

        return $this->responseJsonExtractor($response);
    }

    public function getDocument($orderId, $documentId)
    {
        try{
            $payload = [
                'apiKey' => $this->apiKey,
                'encryptedAppraisalID' => $orderId,
                'encryptedDocumentID' => $documentId
            ];

            $response = $this->client->get('document', [
                'body' => json_encode($payload)
            ]);
        } catch (ClientException $exception) {
            $this->log->error('Error Getting Document: Code: '. $exception->getCode() . 'Message:' . $exception->getMessage());
            $this->throwClientException($exception);
        }

        if(!$this->isHttpSuccessful($response)) {
            return null;
        }

        $response = $this->responseJsonExtractor($response);
        return is_null($response) ? null : $response->fileString;
    }
}
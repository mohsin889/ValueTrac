<?php


namespace app\CommonMethods;

use app\services\BaseService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class HttpCall extends BaseService
{
    private $url, $api, $clientKey, $userID, $password;
    private $URL;
    private $client;
    private $authCount = 0;

    function __construct()
    {
        $this->url = getenv('APPRAISAL_SHIELD_URL');
        $this->api = getenv('APPRAISAL_SHIELD_API');
        $this->clientKey = getenv('APPRAISAL_SHIELD_CLIENT_KEY');
        $this->userID = getenv('APPRAISAL_SHIELD_USER_ID');
        $this->password = getenv('APPRAISAL_SHIELD_PASSWORD');

        $this->URL =  $this->url.'/'.$this->api.'/';
    }

    /**
     * Login to AppraisalShield Account
    */
    public function login()
    {
        start:

        $options = ['base_uri' => $this->URL.'/login', 'headers' => ['Content-Type' => 'application/json']];

        $payload = [
            'clientKey' => $this->clientKey,
            'userID' => $this->userID,
            'password' => $this->password

        ];

        $this->client = new Client($options);

        try{
            $response = $this->client->get($this->URL.'login', [
                'body' => json_encode($payload)
            ]);
        } catch (ClientException $ex) {
            $this->throwClientException($ex);
        }

        if($response->getStatusCode() == 200)
        {
            $resp = $this->responseJsonExtractor($response);

            $_SESSION['Appraisal_Shield_APIKEY'] = $resp->APIKEY;
            $_SESSION['Appraisal_Shield_Time'] = (new \DateTime())->add(new \DateInterval('P1D'));
        }

        else if($response->getStatusCode() == 401)
        {
            if($this->authCount < 3)
            {
                $this->authCount++;
                $_SESSION['Appraisal_Shield_APIKEY'] = null;
                goto start;
            }
            else
            {
                $message = 'number of auth call exceeded from 3 times. </br> Http Response :: '.json_encode($obj);
                (new CommonMethods())->send_mail('dev@homebaseamc.net','NO. OF AUTH CALL EXCEEDED',$message);
            }
        }
    }
}
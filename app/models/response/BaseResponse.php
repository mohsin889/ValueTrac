<?php


namespace app\models\response;


class BaseResponse implements \JsonSerializable
{

    public $MESSAGE;
    public $SUCCESS;
    public $STATUSCODE;

    public function __construct()
    {
        $this->MESSAGE = null;
        $this->SUCCESS = null;
        $this->STATUSCODE = null;
    }

    public function populateResponse($response)
    {
        $this->MESSAGE = $response['MESSAGE'];
        $this->SUCCESS = $response['SUCCESS'];
        $this->STATUSCODE = $response['STATUSCODE'];
    }

    public function jsonSerialize()
    {
        $parameters = [];
        foreach ($this as $key => $value) {
            $parameters[$key] = $value;
        }

        return $parameters;
    }
}
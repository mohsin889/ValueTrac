<?php

namespace app\models\response;


class ActiveResponse extends BaseResponse
{
    public $ACTIVEORDERS;

    public function __construct()
    {
        parent::__construct();

        $this->ACTIVEORDERS = null;
    }


    public function populateResponse($response)
    {
        parent::populateResponse($response);

        $this->ACTIVEORDERS = $response['ACTIVEORDERS'];
    }
}
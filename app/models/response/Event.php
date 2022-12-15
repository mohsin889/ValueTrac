<?php

namespace app\models\response;

use Carbon\Carbon;

class Event
{
    public $type;
    public $data;
    public $time;
    public $raw;

    public function __construct()
    {
        $this->type = null;
        $this->data = new EventData();
        $this->time = Carbon::now();
        $this->raw = null;
    }
}
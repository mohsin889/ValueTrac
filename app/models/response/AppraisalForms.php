<?php

namespace app\models\response;

class AppraisalForms
{
    public $form;
    public $appraisal_form_id;

    public function __construct()
    {
        $this->form = null;
        $this->appraisal_form_id = null;
    }
}
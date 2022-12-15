<?php

namespace app\factories;

use app\models\response\AppraisalForms;
use app\models\response\Event;
use app\models\response\Order;

class ValueTracFactory
{
    public static function isValid($data, $key)
    {
        if (is_array($data)) return array_key_exists($key, $data);

        if(is_object($data)) return property_exists($data, $key);

        return false;
    }

    public static function getValue($data, $key, $defauktValue = null)
    {
        if(self::isValid($data, $key)) {
            if (is_array($data)) return $data[$key];

            if (is_object($data)) return $data->{$key};
        }

        return $defauktValue;
    }

    public static function generateEvent($data): Event
    {
        $event = new Event();
        $event->time = self::getValue($data, 'ProcessDate');
        $event->type = self::getValue($data, 'Type');
        $event->data->platformId = self::getValue($data, 'PlatformId');
        $event->data->Id = self::getValue($data, 'Id');
        $event->data->type = self::getValue($data, 'Type');
        $event->data->returnMethod = self::getValue($data, 'ReturnMethod');
        $event->data->processDate = self::getValue($data, 'ProcessDate');

        return $event;
    }

    public static function generateOrder($data) :Order
    {
        $order = new Order();
        $order->id = self::getValue($data, 'OrderId');
        $order->property_street = self::getValue($data,'SUBJECTADDRESS');
        $order->property_city = self::getValue($data,'SUBJECTCITY');
        $order->property_state = self::getValue($data,'SUBJECTSTATE');
        $order->property_zip = self::getValue($data,'SUBJECTZIPCODE');
        $order->property_county = self::getValue($data,'SUBJECTCOUNTY');
        $order->property_type = self::getValue($data,'SUBJECTPROPERTYTYPE');
        $order->due_date = self::getValue($data,'DATEREQUIRED');
        $order->lender_id = self::getValue($data,'LENDERID');
        $order->lender_name = self::getValue($data,'LENDERNAME');
        $order->loan_number = self::getValue($data,'LoanNumber');
        $order->loan_type = self::getValue($data,'LOANTYPE');
        $order->appraisal_type = self::getValue($data,'LOANPURPOSE');
        $order->agency_case_number = self::getValue($data,'CASENUMBER');

        $order->borrower_name = self::getValue($data,'BORROWER');
        $order->borrower_email = self::getValue($data,'BORROWEREMAIL');
        $order->borrower_phone = self::getValue($data,'BORROWERPHONE');
        $order->borrower_cell = self:: getValue($data,'BORROWERCELL');
        $order->co_borrower_name = self::getValue($data,'COBORROWER');
        $order->co_borrower_email = self::getValue($data,'COBORROWEREMAIL');
        $order->co_borrower_phone = self::getValue($data,'COBORROWERPHONE');
        $order->co_borrower_cell = self::getValue($data,'COBORROWERCELL');

        $order->special_instructions = self::getValue($data,'SPECIALINSTRUCTIONS');

        if(self::isValid($data, 'Products')) {
            foreach ($data->Products as $product)
            {
                $order->products = [];
                array_push($order->products, self::generateProduct($product));
            }

        }

        return $order;
    }

    public function generateProduct($data): AppraisalForms
    {
        $appraisalForm = new AppraisalForms();
        $appraisalForm->appraisal_form_id = self::getValue($data, 'APPRAISALFORMID');
        $appraisalForm->form = self::getValue($data, 'FORM');

        return $appraisalForm;
    }
}
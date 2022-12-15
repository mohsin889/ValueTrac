<?php

namespace app\models\response;

class Order
{
    public $id;
    public $property_street;
    public $property_city;
    public $property_state;
    public $property_zip;
    public $property_county;
    public $property_type;
    public $due_date;
    public $lender_id;
    public $lender_name;
    public $loan_number; // Unique ID for the Loan
    public $loan_type; // Conventional, FHA, USDA-RHS, and VA
    public $appraisal_type; // maps to purchase type - Refinance, Purchase, Construction, Other
    public $consumers;
    public $special_instructions;
    public $agency_case_number;

    public $borrower_name;
    public $borrower_email;
    public $borrower_phone;
    public $borrower_cell;
    public $co_borrower_name;
    public $co_borrower_email;
    public $co_borrower_phone;
    public $co_borrower_cell;

//    public $appraisal_forms;

    public function __construct()
    {
        $this->id = null;
        $this->property_street = null;
        $this->property_city = null;
        $this->property_state = null;
        $this->property_zip = null;
        $this->property_county = null;
        $this->property_type = null;
        $this->due_date = null;
        $this->lender_id = null;
        $this->lender_name = null;
        $this->loan_number = null;
        $this->loan_type = null;
        $this->appraisal_type = null;
        $this->consumers = null;
        $this->special_instructions = null;
        $this->agency_case_number = null;
        $this->borrower_name = null;
        $this->borrower_email = null;
        $this->borrower_phone = null;
        $this->borrower_cell = null;
        $this->co_borrower_name = null;
        $this->co_borrower_email = null;
        $this->co_borrower_phone = null;
        $this->co_borrower_cell = null;
//        $this->appraisal_forms = new AppraisalForms();
    }
}
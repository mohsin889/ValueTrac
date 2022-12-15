<?php

namespace app\utility;

class ObjectCompare
{
    public static function compareOrderObjects($object1, $object2)
    {
        $removedFields = [];
        $differentFields = [];

        $object1 = json_decode($object1);
        $object2 = json_decode($object2);

        foreach ($object1 as $key => $value)
        {
            if(gettype($value) == 'array')
            {
                foreach ($value as $keyArray1 => $valueArray1)
                {
                    if(gettype($valueArray1) == 'object')
                    {
                        foreach ($valueArray1 as $keyArray2 => $valueArray2)
                        {
                            if(property_exists($object2->$key[$keyArray1], $keyArray2))
                            {
                                if($valueArray2 != $object2->$key[$keyArray1]->$keyArray2)
                                {
                                    $differentFields[$keyArray2] = $object2->$key[$keyArray1]->$keyArray2;
                                }
                            }
                            else
                            {
                                $removedFields[$keyArray2] = $valueArray2;
                            }
                        }
                    }
                }
            }
            else if (gettype($value) == 'object')
            {
                foreach ($value as $keyObject1 => $valueObject1)
                {
                    if(gettype($valueObject1) == 'array')
                    {
                        foreach ($valueObject1 as $keyObject2 => $valueObject2)
                        {
                            if(gettype($valueObject2) == 'object')
                            {
                                foreach ($valueObject2 as $keyObject3 => $valueObject3)
                                {
                                    if(property_exists($object2->$key->$keyObject1[$keyObject2], $keyObject3))
                                    {
                                        if($valueObject3 != $object2->$key->$keyObject1[$keyObject2]->$keyObject3)
                                        {
                                            $differentFields[$keyObject3] = $object2->$key->$keyObject1[$keyObject2]->$keyObject3;
                                        }
                                    }
                                    else
                                    {
                                        $removedFields[$keyObject3] = $valueObject3;
                                    }
                                }
                            }
                        }
                    }
                    else
                    {
                        if(property_exists($object2->$key, $keyObject1))
                        {
                            if($valueObject1 != $object2->$key->$keyObject1)
                            {
                                $differentFields[$keyObject1] = $object2->$key->$keyObject1;
                            }
                        }
                        else
                        {
                            $removedFields[$keyObject1] = $valueObject1;
                        }
                    }
                }
            }
            else {
                if(property_exists($object2,$key))
                {
                    if($value != $object2->$key)
                    {
                        $differentFields[$key] = $object2->$key;
                    }
                }
                else
                {
                    $removedFields[$key] = $value;
                }
            }
        }

        return $differentFields;
    }

    // This method is used to compare just the Order forms
    /**
     * this function gets the change in products in an order
     * @param $object1 object contains old object
     * @param $object2 object contains new object
     * @return object
     */
    public static function compareOrderForms($object1, $object2)
    {
        $addedForm = [];
        $removedForm = [];
        $feeChanged = [];
        $oldForms = [];
        $newForms = [];

        $object1 = json_decode($object1);
        $object2 = json_decode($object2);
        $obj1 = $object1->products;
        $obj2 = $object2->products;

        foreach ($obj1 as $value)
        {
            $oldForms[$value->appraisal_form_id] = $value->form;
        }

        foreach ($obj2 as $value)
        {
            $newForms[$value->appraisal_form_id] = $value->form;
        }

        foreach ($oldForms as $key1 => $value1)
        {
            if(array_key_exists($key1, $newForms))
            {
                if($value1 != $newForms[$key1])
                {
                    $feeChanged[$key1] = $newForms[$key1];
                }
            }
            else
            {
                array_push($removedForm,$key1);
            }
        }

        foreach ($newForms as $key1 => $value1)
        {
            if(array_key_exists($key1, $oldForms)) { }
            else
            {
                $addedForm[$key1] = $value1;
            }
        }

        $response = (object) [
            'additionalForms' => $addedForm,
            'removedForms' => $removedForm,
            'feeChanged' => $feeChanged
        ];

        return $response;
    }
}
<?php

namespace app\utility;

class ObjectExt
{
    public static function hasValidProperty($var, $property) {
        return property_exists($var,$property) && !empty($var->{$property});
    }
    public static function toObject($object): object
    {
        if(gettype($object) == 'string')
            return json_decode($object);
        if(gettype($object) == 'array')
            return json_decode(json_encode($object));
        else
            return $object;
    }
}
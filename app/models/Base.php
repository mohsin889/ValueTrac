<?php


namespace app\models;


class Base implements \JsonSerializable
{
    public function jsonSerialize()
    {
        $parameters = [];
        foreach ($this as $key => $value) {
            $parameters[$key] = $value;
        }

        return $parameters;
    }
}
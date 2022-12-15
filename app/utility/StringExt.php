<?php

namespace app\utility;

class StringExt
{
    public static function minify($str) {
        return preg_replace('/\s+/', '', strtolower($str));
    }
}
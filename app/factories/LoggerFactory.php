<?php


namespace app\factories;


use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class LoggerFactory
{
    public static function createLogger($name = "Default") : Logger
    {
        $logPath = __DIR__."/../../logs/{$name}.log";
        $log = new Logger($name.'Logger');
        $log->pushHandler(new StreamHandler($logPath));

        return $log;
    }
}
<?php

namespace app\factories;

use app\models\PdoConnection;
use app\services\Pdo;

class PdoFactory
{
    public static function createValueTracPdo(): Pdo
    {
        return new Pdo(new PdoConnection([
            'host' => getenv('DB_HOST'),
            'dbName' => getenv('DB_NAME'),
            'user' => getenv('DB_USER'),
            'pass' => getenv('DB_PASS'),
            'port' => getenv('DB_PORT')
        ]));
    }

    public static function createHomeBasePdo(): Pdo
    {
        return new Pdo(new PdoConnection([
            'host' => getenv('DB_HBAM_HOST'),
            'dbName' => getenv('DB_HBAM_DATABASE'),
            'user' => getenv('DB_HBAM_USERNAME'),
            'pass' => getenv('DB_HBAM_PASSWORD'),
            'port' => getenv('DB_HBAM_PORT')
        ]));
    }
}
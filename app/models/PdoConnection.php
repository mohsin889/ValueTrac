<?php

namespace app\models;

class PdoConnection
{
    public $host;
    public $port;
    public $dbName;
    public $user;
    public $pass;

    public function __construct(array $config)
    {
        $this->host = isset($config['host']) ? $config['host'] : null;
        $this->port = isset($config['port']) ? $config['port'] : 3306;
        $this->dbName = isset($config['dbName']) ? $config['dbName'] : null;
        $this->user = isset($config['user']) ? $config['user'] : null;
        $this->pass = isset($config['pass']) ? $config['pass'] : null;
    }
}
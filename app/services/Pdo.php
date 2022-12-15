<?php

namespace app\services;

use app\factories\LoggerFactory;
use app\models\PdoConnection;

class Pdo
{
    private $pdo;
    private $stmt;
    private $log;

    public function __construct(PdoConnection $conn)
    {
        $this->pdo = new \PDO("mysql:host={$conn->host};dbname={$conn->dbName};port={$conn->port}", $conn->user, $conn->pass);
        $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_OBJ);
        $this->log = LoggerFactory::createLogger('Pdo');
    }

    public function __destruct()
    {
        $this->pdo = null;
    }

    private function getDataType($var)
    {
        return is_numeric($var) && !is_float($var) ? \PDO::PARAM_INT : \PDO::PARAM_STR;
    }

    public function query(string $sql, array $bindings = null)
    {
        $stmt = $this->pdo->prepare($sql);

        if (strpos($sql,'?') === false) {
            if (!is_null($bindings)) {
                foreach ($bindings as $binding => &$value) {
                    $stmt->bindParam($binding, $value, $this->getDataType($value));
                }
            }

            $response = $stmt->execute();
        } else {
            foreach ($bindings as $idx => $binding) {
                $stmt->bindValue(($idx+1), $binding, $this->getDataType($binding));
            }

            $response = $stmt->execute();
        }

        $results = $stmt->fetchAll();

        $this->stmt = $stmt;

        if ($results === false) {
            return false;
        }

        return $results;

        /*if (!empty($results)) {
            return $results;
        }

        return $response;*/
    }

    public function getLastError(): ?array
    {
        return $this->pdo->errorInfo();
    }

    public function getLastStatement()
    {
        return $this->stmt;
    }

    public function falsey($result)
    {
        if (!$result) {
            throw new \Exception("MySQL encountered a client error. SQLERROR: " . json_encode(['error' => $this->getLastError()]));
        }

        return boolval($result);
    }
}
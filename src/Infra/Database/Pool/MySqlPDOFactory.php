<?php

namespace Infra\Database\Pool;

use PDO;

final readonly class MySqlPDOFactory implements IPDOFactory
{
    public function __construct(
        private string $host,
        private int $port,
        private string $dbname,
        private string $username,
        private string $password,
        private array $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    ) {
    }

    public function create(): PDO
    {
        $dsn = "mysql:host=$this->host;port=$this->port;dbname=$this->dbname;charset=utf8mb4";
        return new PDO($dsn, $this->username, $this->password, $this->options);
    }
}
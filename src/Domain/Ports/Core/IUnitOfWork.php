<?php

namespace Domain\Ports\Core;

use PDO;
use Shared\Exceptions\Result;

interface IUnitOfWork
{
    public function begin(): Result;

    public function commit(): Result;

    public function rollback(): Result;

    /**
     * @return Result<PDO>
     */
    public function getTransaction(): Result;
}
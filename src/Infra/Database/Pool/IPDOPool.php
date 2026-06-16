<?php

namespace Infra\Database\Pool;

use PDO;
use Shared\Exceptions\Result;

interface IPDOPool
{
    /**
     * @return Result<PDO>
     */
    public function get(): Result;

    /**
     * @param  PDO  $pdo
     * @return Result<null>
     */
    public function put(PDO $pdo): Result;
}
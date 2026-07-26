<?php

namespace Infra\Database\Interno;

use PDO;

readonly class PdoTransactionContext
{
    public function __construct(
        public PDO $pdo
    ) {
    }
}
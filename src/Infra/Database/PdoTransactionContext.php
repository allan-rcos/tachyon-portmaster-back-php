<?php

namespace Infra\Database;

use PDO;

readonly class PdoTransactionContext
{
    public function __construct(
        public PDO $pdo
    ) {
    }
}
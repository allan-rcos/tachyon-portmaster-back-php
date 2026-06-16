<?php

namespace Infra\Database\Pool;

use PDO;

class PDONode
{
    public float $lastActiveTime;

    public function __construct(
        public readonly PDO $pdo
    ) {
        $this->touch();
    }

    public function touch(): void
    {
        $this->lastActiveTime = microtime(true);
    }
}
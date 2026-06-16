<?php

namespace Infra\Database\Pool;

use PDO;

readonly class PDOLeaseToken
{
    public float $leasedAt;

    public function __construct(
        public PDO $pdo
    ) {
        $this->leasedAt = microtime(true);
    }

    public function isExpired(float $maxLeaseTime): bool
    {
        return (microtime(true) - $this->leasedAt) > $maxLeaseTime;
    }
}
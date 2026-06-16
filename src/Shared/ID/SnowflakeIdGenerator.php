<?php

namespace Shared\ID;

use Domain\Ports\Core\IIntIdGenerator;
use Ds\Map;
use OpenSwoole\Coroutine;
use RuntimeException;
use Shared\Exceptions\LeafContext;

class SnowflakeIdGenerator implements IIntIdGenerator
{
    private const int CLUSTER_ID_BITS = 5;
    private const int SERVER_ID_BITS = 5;
    private const int SEQUENCE_BITS = 12;

    private const int MAX_CLUSTER_ID = -1 ^ (-1 << self::CLUSTER_ID_BITS); // 31
    private const int MAX_SERVER_ID = -1 ^ (-1 << self::SERVER_ID_BITS); // 31
    private const int SEQUENCE_MASK = -1 ^ (-1 << self::SEQUENCE_BITS); // 4095

    private const int SERVER_ID_SHIFT = self::SEQUENCE_BITS;
    private const int CLUSTER_ID_SHIFT = self::SEQUENCE_BITS + self::SERVER_ID_BITS;
    private const int TIMESTAMP_LEFT_SHIFT = self::SEQUENCE_BITS + self::SERVER_ID_BITS + self::CLUSTER_ID_BITS;

    private int $sequence = 0;
    private int $lastTimestamp = -1;

    private function __construct(
        private readonly int $clusterId,
        private readonly int $serverId,
        private readonly int $epoch = 1704067200000,
    ) {
    }

    public static function create(
        int $clusterId,
        int $serverId,
        int $epoch = 1704067200000,
    ): self|LeafContext {
        $details = new Map();
        if ($clusterId > self::MAX_CLUSTER_ID || $clusterId < 0) {
            $details->put($clusterId,
                "Cluster ID deve estar entre 0 e ".self::MAX_CLUSTER_ID);
        }
        if ($serverId > self::MAX_SERVER_ID || $serverId < 0) {
            $details->put($serverId,
                "Server ID deve estar entre 0 e ".self::MAX_SERVER_ID);
        }
        if (!$details->isEmpty()) {
            return new LeafContext(
                "Configuração de ID inválida.",
                $details,
                400
            );
        }
        unset($details);
        return new self($clusterId, $serverId, $epoch);
    }

    public function generate(): int
    {
        $timestamp = $this->currentTimestamp();

        if ($timestamp < $this->lastTimestamp) {
            throw new RuntimeException(
                sprintf("Relógio retrocedeu. Geração de ID bloqueada por %d milissegundos.",
                    $this->lastTimestamp - $timestamp)
            );
        }

        if ($this->lastTimestamp === $timestamp) {
            $this->sequence = ($this->sequence + 1) & self::SEQUENCE_MASK;

            if ($this->sequence === 0) {
                $timestamp = $this->waitNextMillis($this->lastTimestamp);
            }
        } else {
            $this->sequence = 0;
        }

        $this->lastTimestamp = $timestamp;

        return (($timestamp - $this->epoch) << self::TIMESTAMP_LEFT_SHIFT)
            | ($this->clusterId << self::CLUSTER_ID_SHIFT)
            | ($this->serverId << self::SERVER_ID_SHIFT)
            | $this->sequence;
    }

    private function currentTimestamp(): int
    {
        return (int) floor(microtime(true) * 1000);
    }

    private function waitNextMillis(int $lastTimestamp): int
    {
        $timestamp = $this->currentTimestamp();
        while ($timestamp <= $lastTimestamp) {
            if (Coroutine::getCid() > -1) {
                Coroutine::sleep(0.001);
            } else {
                usleep(1000);
            }
            $timestamp = $this->currentTimestamp();
        }
        return $timestamp;
    }
}
<?php

declare(strict_types=1);

namespace Domain\ID\Interno;

use Domain\ID\Base62;
use Domain\ID\IDatabaseIdGenerator;
use Ds\Map;
use OpenSwoole\Coroutine;
use RuntimeException;
use Shared\Exceptions\LeafContext;

/**
 * Snowflake id generator.
 *
 * Computes a 63-bit time-ordered integer (timestamp | cluster | server |
 * sequence) and returns it **compacted to base62** — the string form ids take
 * across every layer. Infra is the only place that decodes it back to the
 * integer BIGINT via {@see Base62::decode()}.
 *
 * Not `readonly`: the sequence counter and last-timestamp watermark are mutated
 * on every {@see generate()} — that state is what keeps ids unique inside a
 * millisecond, so each worker must own exactly one instance.
 */
final class SnowflakeIdGenerator implements IDatabaseIdGenerator
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

    public function generate(): string
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

        $id = (($timestamp - $this->epoch) << self::TIMESTAMP_LEFT_SHIFT)
            | ($this->clusterId << self::CLUSTER_ID_SHIFT)
            | ($this->serverId << self::SERVER_ID_SHIFT)
            | $this->sequence;

        return Base62::encode($id);
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
                // The OpenSwoole ide-helper stub types $seconds as int, but the
                // runtime accepts fractional seconds (1ms yield here).
                Coroutine::sleep(0.001); // @phpstan-ignore argument.type
            } else {
                usleep(1000);
            }
            $timestamp = $this->currentTimestamp();
        }
        return $timestamp;
    }
}

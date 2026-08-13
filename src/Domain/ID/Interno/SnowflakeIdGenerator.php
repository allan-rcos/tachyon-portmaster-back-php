<?php

/**
 * Snowflake Id Generator.
 *
 * @category Domain
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Domain\ID\Interno;

use Domain\ID\Base62;
use Domain\ID\IDatabaseIdGenerator;
use Ds\Map;
use OpenSwoole\Coroutine;
use RuntimeException;
use Shared\Exceptions\LeafContext;

/**
 * Time-ordered primary keys, from a Snowflake.
 *
 * Packs a 63-bit integer as `timestamp | cluster | server | sequence` and
 * returns it Base62-encoded — the string form ids take across every layer. Infra
 * is the only place that decodes it back to the BIGINT it stores.
 *
 * The monotonic timestamp prefix is the reason this flavour exists: it keeps the
 * primary-key index append-only, so inserts never split a page in the middle.
 *
 * **One instance per worker, and not `readonly`.** {@see $sequence} and
 * {@see $lastTimestamp} are mutated on every call, and they are what keep two
 * ids minted in the same millisecond apart. Two instances sharing a server id
 * would collide. The worker id is passed in as the server id, which is what
 * makes four workers safe without coordinating.
 *
 * @see IDatabaseIdGenerator The contract, and when to choose this flavour.
 * @see Base62 The encoding applied on the way out.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final class SnowflakeIdGenerator implements IDatabaseIdGenerator
{
    /**
     * @var int Bits reserved for the cluster id.
     */
    private const int CLUSTER_ID_BITS = 5;

    /**
     * @var int Bits reserved for the server id — the worker number.
     */
    private const int SERVER_ID_BITS = 5;

    /**
     * @var int Bits reserved for the per-millisecond counter. Twelve caps a
     *          single generator at 4096 ids per millisecond, past which
     *          {@see waitNextMillis()} stalls until the clock advances.
     */
    private const int SEQUENCE_BITS = 12;

    /**
     * @var int Highest valid cluster id (31).
     */
    private const int MAX_CLUSTER_ID = -1 ^ (-1 << self::CLUSTER_ID_BITS); // 31

    /**
     * @var int Highest valid server id (31) — so at most 32 workers.
     */
    private const int MAX_SERVER_ID = -1 ^ (-1 << self::SERVER_ID_BITS); // 31

    /**
     * @var int Wraps the sequence counter back to zero (4095).
     */
    private const int SEQUENCE_MASK = -1 ^ (-1 << self::SEQUENCE_BITS); // 4095

    /**
     * @var int Left shift placing the server id above the sequence.
     */
    private const int SERVER_ID_SHIFT = self::SEQUENCE_BITS;

    /**
     * @var int Left shift placing the cluster id above the server id.
     */
    private const int CLUSTER_ID_SHIFT = self::SEQUENCE_BITS + self::SERVER_ID_BITS;

    /**
     * @var int Left shift placing the timestamp in the high bits, which is what
     *          makes the id sort by time.
     */
    private const int TIMESTAMP_LEFT_SHIFT = self::SEQUENCE_BITS + self::SERVER_ID_BITS + self::CLUSTER_ID_BITS;

    /**
     * @var int Ids minted so far within {@see $lastTimestamp}. Reset whenever
     *          the clock advances.
     */
    private int $sequence = 0;

    /**
     * @var int Millisecond the last id was minted in; -1 before the first.
     *          Compared against the clock to detect it going backwards.
     */
    private int $lastTimestamp = -1;

    /**
     * Private so an instance can only come from {@see create()}, which is what
     * validates the ids — a generator built with an out-of-range server id would
     * silently corrupt the bit layout.
     *
     * @param  int  $clusterId  Deployment identifier, 0 to {@see MAX_CLUSTER_ID}.
     * @param  int  $serverId  Worker identifier, 0 to {@see MAX_SERVER_ID}.
     * @param  int  $epoch  Milliseconds the timestamp counts from. Moving it
     *                      forward buys years of headroom; changing it after
     *                      ids exist breaks their ordering.
     *
     * @copyright 2026 Tachyon
     */
    private function __construct(
        private readonly int $clusterId,
        private readonly int $serverId,
        private readonly int $epoch = 1704067200000,
    ) {
    }

    /**
     * Builds a generator, or explains why the configuration is unusable.
     *
     * Returns a {@see LeafContext} rather than a {@see \Shared\Exceptions\Result}
     * because this runs in the composition root, before the error registry any
     * `Result` id would index into is useful.
     *
     * @param  int  $clusterId  Deployment identifier, 0 to {@see MAX_CLUSTER_ID}.
     * @param  int  $serverId  Worker identifier, 0 to {@see MAX_SERVER_ID}.
     * @param  int  $epoch  Milliseconds the timestamp counts from.
     * @return self|LeafContext The generator, or a 400 context naming every id
     *                          that was out of range.
     *
     * @copyright 2026 Tachyon
     */
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

    /**
     * Mints the next id.
     *
     * Throws on a backwards clock instead of minting anyway. An id issued during
     * the rewind would duplicate one already handed out, and a duplicate primary
     * key is unrecoverable — refusing to serve is the lesser failure.
     *
     * @return string The 63-bit id, Base62-encoded.
     *
     * @throws RuntimeException When the system clock has moved backwards.
     *
     * @copyright 2026 Tachyon
     */
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

    /**
     * The wall clock in milliseconds.
     *
     * @return int Milliseconds since the Unix epoch.
     *
     * @copyright 2026 Tachyon
     */
    private function currentTimestamp(): int
    {
        return (int) floor(microtime(true) * 1000);
    }

    /**
     * Blocks until the clock passes the given millisecond.
     *
     * Reached only when 4096 ids were minted inside one millisecond. Yields to
     * the scheduler when inside a coroutine so the other requests on this worker
     * keep running; falls back to `usleep()` at boot and in tests, where there is
     * no scheduler to yield to.
     *
     * @param  int  $lastTimestamp  Millisecond the sequence was exhausted in.
     * @return int The first millisecond after it.
     *
     * @copyright 2026 Tachyon
     */
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

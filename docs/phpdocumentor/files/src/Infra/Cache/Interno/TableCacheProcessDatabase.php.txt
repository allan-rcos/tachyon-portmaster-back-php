<?php

/**
 * Table Cache Process Database.
 *
 * @category Infrastructure
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Infra\Cache\Interno;

use Domain\Security\IIndexHasher;
use Ds\Map;
use Infra\Cache\CacheProcessDatabaseConfig;
use Infra\Cache\CacheProcessEntryConfig;
use Infra\Cache\ICacheProcessDatabase;
use Infra\Config\CacheLimits;
use Infra\Logging\ILogger;
use OpenSwoole\Table;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;
use Throwable;

/**
 * {@see ICacheProcessDatabase} over the two shared `OpenSwoole\Table`s.
 *
 * The tables are allocated before the fork, so a write here is visible to every
 * worker the instant it lands — which is the whole reason the cache left
 * MariaDB. A `get` is a shared-memory read at roughly 0.4 µs.
 *
 * `index` holds the logical key and the expiry; `store` holds the payload. The
 * split exists for the operations that *iterate*, since iterating a table copies
 * every row into a PHP array. Keys are hashed because a table key may only be 63
 * bytes and the extension truncates silently past that.
 *
 * **Every failure is reported, none is swallowed.** A miss is a 404, a key or a
 * value the store cannot address is a 422, and a store that broke is a 500.
 * Deciding that a miss simply means "ask the database instead" belongs to the
 * use case, not here.
 *
 * @see ICacheProcessDatabase The contract this implements.
 * @uses Table Two of them, read and written directly.
 * @uses IIndexHasher Turns the logical key into the table key.
 * @uses ILogger Records the failures that are not the caller's doing.
 * @see OpenSwooleCacheProcessAdapter What allocates the tables and builds these.
 * @see CacheProcessSweeper What reclaims what expires here.
 * @see docs/adr/0011-cache-em-processo-openswoole.md Why the cache is shaped this way.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class TableCacheProcessDatabase implements ICacheProcessDatabase
{
    /**
     * @var string Separates the database's key from the entry's suffix.
     *
     * Load-bearing: without it a {@see clean()} over the whole `marker` database
     * would also drop `marker-group`, one key being a prefix of the other.
     */
    private const string DELIMITER = ':';

    /**
     * @var ILogger Channelled copy, so these lines are attributable to the cache
     *              rather than to whichever read was passing through it.
     */
    private ILogger $logger;

    /**
     * @param  Table  $index  Logical key and expiry, keyed by digest. The only
     *                        one iterated.
     * @param  Table  $store  Payload, keyed by the same digest.
     * @param  int  $payloadBytes  Declared width of `store.payload`. Passed in
     *                             because a table cannot be asked how wide its
     *                             columns are, and a value serialising past it
     *                             has to be declined rather than silently cut.
     * @param  CacheProcessDatabaseConfig  $config  Which slice this addresses,
     *                                              and its default TTL.
     * @param  IIndexHasher  $hasher  Digests the logical key into the table key.
     * @param  ILogger  $logger  Rebound to this database's channel; the injected
     *                           instance is not kept.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private Table $index,
        private Table $store,
        private int $payloadBytes,
        private CacheProcessDatabaseConfig $config,
        private IIndexHasher $hasher,
        ILogger $logger,
    ) {
        $this->logger = $logger->withChannel('cache-process-'.$this->config->key);
    }

    /**
     * {@inheritDoc}
     *
     * @param  CacheProcessEntryConfig|null  $entry  Which entry; `null` addresses
     *                                               the database's bare key.
     * @return Result<mixed> The stored value. A 404 when there is nothing live
     *                       under the key, a 422 when the key is too long to have
     *                       been stored, and a 500 when the store holds something
     *                       it cannot read back.
     *
     * @copyright 2026 Tachyon
     */
    public function get(?CacheProcessEntryConfig $entry = null): Result
    {
        $logical = $this->logical($entry);
        if ($logical === null) {
            return $this->keyTooLong($entry->suffix ?? '');
        }

        $digest = $this->hasher->hash($logical);

        $row = $this->index->get($digest);
        if (!is_array($row)) {
            return $this->absent($logical, 'is not cached');
        }

        // A digest resolving to another key cannot realistically happen; the
        // alternative to checking is serving one query's page for another's.
        if (($row['logical'] ?? null) !== $logical) {
            return $this->absent($logical, 'collided with another key');
        }

        $expires = $row['expires'] ?? CacheLimits::TTL_FOREVER;
        if (is_int($expires) && $expires !== CacheLimits::TTL_FOREVER && $expires <= time()) {
            // Filtered on read, not deleted, so correctness never depends on when
            // the sweeper last ran. The sentinel is matched exactly, matching how
            // {@see put()} writes it.
            return $this->absent($logical, 'has expired');
        }

        $payload = $this->store->get($digest, 'payload');
        if (!is_string($payload)) {
            return $this->broken($logical, 'the payload is missing from the store');
        }

        try {
            $value = @igbinary_unserialize($payload);
        } catch (Throwable $e) {
            return $this->broken($logical, $e->getMessage());
        }

        // igbinary answers null for bytes it cannot read, which is what an entry
        // written by an earlier deploy looks like.
        return $value === null
            ? $this->broken($logical, 'the stored bytes are in an older format')
            : Result::success($value);
    }

    /**
     * {@inheritDoc}
     *
     * @param  mixed  $value  The object to keep, as the caller holds it.
     * @param  CacheProcessEntryConfig|null  $entry  Which entry, and for how
     *                                               long; `null` uses the
     *                                               database's key and TTL.
     * @return Result<null> Void once stored. A 422 when the key or the value is
     *                      wider than the store can hold, and a 500 when the
     *                      write itself failed.
     *
     * @copyright 2026 Tachyon
     */
    public function put(mixed $value, ?CacheProcessEntryConfig $entry = null): Result
    {
        $logical = $this->logical($entry);
        if ($logical === null) {
            return $this->keyTooLong($entry->suffix ?? '');
        }

        try {
            $payload = @igbinary_serialize($value);
        } catch (Throwable $e) {
            return $this->broken($logical, $e->getMessage());
        }

        if (!is_string($payload)) {
            return $this->broken($logical, 'the value could not be serialized');
        }

        // Declining is what keeps a `?limit=100000` from occupying an entry
        // nothing will read again.
        if (strlen($payload) > $this->payloadBytes) {
            return $this->tooWide($logical, strlen($payload));
        }

        $ttl = $entry->ttlSeconds ?? $this->config->ttlSeconds;
        $digest = $this->hasher->hash($logical);

        try {
            // Store first, index second: a reader landing in between finds no
            // index entry and recomputes, where the other order would have it
            // find an index entry pointing at a payload that is not there yet.
            $stored = $this->store->set($digest, ['payload' => $payload]);
            $indexed = $this->index->set($digest, [
                'logical' => $logical,
                // Matched exactly, never as a threshold: reading it as "anything
                // not positive" would turn a negative TTL into an entry that
                // never expires — for a marker, a revocation that never revokes.
                'expires' => $ttl === CacheLimits::TTL_FOREVER ? CacheLimits::TTL_FOREVER : time() + $ttl,
            ]);
        } catch (Throwable $e) {
            // `Table::set()` throws on a value wider than its column rather than
            // truncating. Both widths are guarded above; this is the belt.
            return $this->abandon($digest, $logical, $e->getMessage());
        }

        return $stored && $indexed
            ? Result::void()
            : $this->abandon($digest, $logical, 'the cache is full');
    }

    /**
     * {@inheritDoc}
     *
     * Iterates `index`, never `store`, collecting the digests to drop and
     * deleting them afterwards — collecting first keeps the iteration from being
     * mutated underneath itself while another worker writes.
     *
     * @param  CacheProcessEntryConfig|null  $entry  The prefix to drop under;
     *                                               `null` drops the whole
     *                                               database.
     * @return Result<null> Void; a full scan of shared memory has nothing to
     *                      fail at.
     *
     * @copyright 2026 Tachyon
     */
    public function clean(?CacheProcessEntryConfig $entry = null): Result
    {
        $prefix = $this->config->key.self::DELIMITER.($entry->suffix ?? '');

        /** @var list<string> $doomed */
        $doomed = [];

        foreach ($this->index as $digest => $row) {
            if (!is_string($digest) || !is_array($row)) {
                continue;
            }

            $logical = $row['logical'] ?? null;
            if (is_string($logical) && str_starts_with($logical, $prefix)) {
                $doomed[] = $digest;
            }
        }

        foreach ($doomed as $digest) {
            $this->index->del($digest);
            $this->store->del($digest);
        }

        return Result::void();
    }

    /**
     * Reports that nothing live is filed under the key.
     *
     * A 404 rather than an empty success, and not logged: the caller asked for
     * something that is not there, which is the ordinary outcome on a cold cache
     * and says nothing about the store's health. What to do about it — recompute,
     * or give up — is the caller's decision to make.
     *
     * @param  string  $logical  The key that was asked for.
     * @param  string  $reason  How it came to be absent, phrased to follow the
     *                          key.
     * @return Result<never> Always a 404 failure.
     *
     * @copyright 2026 Tachyon
     */
    private function absent(string $logical, string $reason): Result
    {
        return Result::failure(Leaf::newError(new LeafContext(
            message: "The cached entry $logical ".$reason,
            details: new Map(['key' => $logical]),
            code: 404,
        )));
    }

    /**
     * Reports a key longer than the store can address.
     *
     * A 422 rather than a 404: the entry is not missing, it could never have been
     * written. Refused rather than truncated, because a cut search term would
     * make two different queries share one entry.
     *
     * @param  string  $suffix  What was asked for, for the error's details.
     * @return Result<never> Always a 422 failure.
     *
     * @copyright 2026 Tachyon
     */
    private function keyTooLong(string $suffix): Result
    {
        return Result::failure(Leaf::newError(new LeafContext(
            message: 'The cache key is longer than '.CacheLimits::LOGICAL_KEY_MAX_BYTES.' bytes',
            details: new Map(['database' => $this->config->key, 'suffix' => $suffix]),
            code: 422,
        )));
    }

    /**
     * Reports a value wider than the payload column.
     *
     * A 422 for the same reason as {@see keyTooLong()}: the caller handed over
     * something this store cannot hold, which is a fact about the request rather
     * than a fault in the store.
     *
     * @param  string  $logical  The key it would have been stored under.
     * @param  int  $bytes  What it serialised to.
     * @return Result<never> Always a 422 failure.
     *
     * @copyright 2026 Tachyon
     */
    private function tooWide(string $logical, int $bytes): Result
    {
        return Result::failure(Leaf::newError(new LeafContext(
            message: "The value is $bytes bytes, wider than the $this->payloadBytes the cache holds",
            details: new Map(['key' => $logical]),
            code: 422,
        )));
    }

    /**
     * Reports a store that could not do its job, and logs it.
     *
     * The one family worth a log line, and the reason it is worth one: a cache
     * that has quietly stopped working looks exactly like a cache that is
     * working, and the only difference visible from outside is the load. Warning
     * rather than error because every caller can carry on without it.
     *
     * @param  string  $logical  The key in play.
     * @param  string  $reason  What went wrong.
     * @return Result<never> Always a 500 failure.
     *
     * @copyright 2026 Tachyon
     */
    private function broken(string $logical, string $reason): Result
    {
        $context = new LeafContext(
            message: 'An error occurred while trying to reach the cached entry',
            details: new Map(['key' => $logical, 'error' => $reason]),
            code: 500,
        );
        $this->logger->warn($context->message, ($context->details?->toArray() ?? []));

        return Result::failure(Leaf::newError($context));
    }

    /**
     * Undoes a half-written entry and reports it.
     *
     * Both tables are cleared because an entry that made it into one and not the
     * other is worse than no entry at all: {@see get()} would find an index row
     * whose payload is missing, and answer a 500 where it could have answered a
     * 404.
     *
     * @param  string  $digest  The key to clear from both tables.
     * @param  string  $logical  What was being stored, for the error's details.
     * @param  string  $reason  Why it could not be.
     * @return Result<never> Always a 500 failure.
     *
     * @copyright 2026 Tachyon
     */
    private function abandon(string $digest, string $logical, string $reason): Result
    {
        $this->store->del($digest);
        $this->index->del($digest);

        return $this->broken($logical, $reason);
    }

    /**
     * The full key an operation addresses, or `null` when it is too long.
     *
     * @param  CacheProcessEntryConfig|null  $entry  The operation's own config.
     * @return string|null The logical key, or `null` past
     *                     {@see CacheLimits::LOGICAL_KEY_MAX_BYTES}.
     *
     * @copyright 2026 Tachyon
     */
    private function logical(?CacheProcessEntryConfig $entry): ?string
    {
        $logical = $this->config->key.self::DELIMITER.($entry->suffix ?? '');

        return strlen($logical) > CacheLimits::LOGICAL_KEY_MAX_BYTES ? null : $logical;
    }
}

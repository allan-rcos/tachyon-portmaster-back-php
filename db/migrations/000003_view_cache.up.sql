-- The read-side cache: one row per cached query result.
--
-- ENGINE=MEMORY for the same reason as 000002_metadata_and_markers.up.sql, and
-- recorded in docs/adr/0010-read-cache-in-a-memory-table.md. The short
-- version: the object graph is built inside WorkerStart, i.e. after OpenSwoole
-- forks, so an OpenSwoole\Table would be one table per worker and an
-- invalidation on worker 2 would be invisible to worker 3 — the same bug ADR
-- 0002 records for the metadata registries. A row here is visible to every
-- worker, and to a second instance, the moment it is written.
--
-- What matters when reading the DDL below:
--
--   * MEMORY does not support BLOB or TEXT, so the payload is a VARBINARY of a
--     fixed maximum width. MEMORY also pads every VARBINARY to its declared
--     width, so a row costs ~16.6 KB whatever it holds. These widths and
--     SqlViewCacheRepository::MAX_PAYLOAD_BYTES / MAX_KEY_BYTES must agree: the
--     repository silently declines to cache anything that does not fit, which
--     is what keeps a `?limit=100000` from attempting a row the column cannot
--     hold.
--
--     16384 is measured, not guessed. The widest view is
--     ContainerSummaryListView, whose items nest a manifest and up to
--     RECENT_LOGS telemetry entries; a default page of 20 containers carrying
--     eight cargo lines each serialises to ~11.3 KB under igbinary. Every other
--     view is far smaller — a 20-item product page is ~1.6 KB. Widening this
--     column costs RAM on every row because of the padding above, so it buys
--     headroom for the one view that needs it and stops there.
--   * CHARSET=binary, and VARBINARY rather than VARCHAR: the key is ASCII and
--     the payload is igbinary output. Under utf8mb4 the declared width would be
--     multiplied by four before the padding above was even applied.
--   * the BTREE index on cache_group is not redundant with the primary key.
--     MEMORY indexes default to HASH, and a HASH over (cache_group, cache_key)
--     cannot serve a lookup by cache_group alone — which is exactly what
--     invalidation does.
--   * expires_at is a UTC instant, like every DATETIME in this schema. The
--     server runs on UTC and every connection sets its session time zone to UTC,
--     so the NOW() the repository computes the expiry from and the NOW() a read
--     compares against are the same clock. See docs/database.md.
--   * MEMORY is non-transactional: a write here survives a ROLLBACK. Nothing in
--     this table participates in a business invariant. Invalidation runs after
--     the commit it follows, and a cache that loses an entry only recomputes.
--   * MEMORY takes table-level locks, which is why reads filter on expires_at
--     rather than deleting what they find expired, exactly as markers do. It is
--     also why a write here does not sweep, unlike a marker write: a cache write
--     happens on every miss, and scanning the table each time would cost more
--     than the read it is saving.
--   * MEMORY has no LRU eviction. When the table fills, the INSERT fails with
--     error 1114 and the repository logs it and moves on — a cache that cannot
--     grow is still a cache.
--
-- The server must run with a max_heap_table_size large enough to hold the
-- working set; the default 16 MB fills at roughly 970 rows. The dev compose
-- stack and the integration harness both pass --max-heap-table-size=256M, which
-- is about 15,000 rows — above the 10,000 the Rust implementation caps its Moka
-- cache at, and the reason that capacity is not also a constant here: the
-- engine enforces it, and error 1114 is how it says so.
--
-- Every statement survives being applied twice, for the reason given in
-- 000001_initial_schema.up.sql.

CREATE TABLE IF NOT EXISTS view_cache (
    cache_group  VARBINARY(32)     NOT NULL,
    cache_key    VARBINARY(191)    NOT NULL,
    payload      VARBINARY(16384)  NOT NULL,
    expires_at   DATETIME          NOT NULL,
    PRIMARY KEY (cache_group, cache_key),
    KEY idx_view_cache_group (cache_group) USING BTREE,
    KEY idx_view_cache_expires_at (expires_at) USING BTREE
) ENGINE=MEMORY DEFAULT CHARSET=binary;

-- The only thing that reclaims an expired row. Unlike markers, writes here do
-- not sweep — see above — so this event is not a backstop but the whole
-- mechanism. Reads already filter on expires_at, so a row it has not collected
-- yet costs RAM and never correctness.
--
-- Every minute rather than the markers' hour: entries live 30 seconds, so an
-- hourly purge would leave the table holding an hour of dead pages.
--
-- Requires the server to run with event_scheduler=ON (the dev compose stack and
-- the integration harness both pass --event-scheduler=ON).
CREATE EVENT IF NOT EXISTS purge_expired_view_cache
    ON SCHEDULE EVERY 1 MINUTE
    DO DELETE FROM view_cache WHERE expires_at <= NOW();

-- Reverses 000004_drop_memory_tables.up.sql by putting the ENGINE=MEMORY tables
-- back exactly as 000002 and 000003 created them.
--
-- A true inverse, which the test harness depends on: it resets by dropping every
-- table and re-migrating, so a `down` that left the schema short of what 000003
-- had built would break the next `up`.
--
-- It restores the *definitions* and nothing else. There is no data to bring
-- back: MEMORY tables are emptied by a server restart anyway, and by the time
-- this runs the application has been writing to its own cache instead. Rolling
-- back past this point means also deploying the release that reads these tables,
-- which will refill the registries at its next WorkerStart.
--
-- The two servers flags those releases needed are back in scope as well:
-- `--event-scheduler=ON` for the purges below, and `--max-heap-table-size=256M`
-- for view_cache. Neither is set by the current compose stack or harness — see
-- docs/adr/0011-cache-em-processo-openswoole.md.
--
-- Every statement survives being applied twice, for the reason given in
-- 000001_initial_schema.up.sql.

CREATE TABLE IF NOT EXISTS permissions (
    id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug  VARCHAR(64)  NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_permissions_slug (slug)
) ENGINE=MEMORY DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marker_groups (
    id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug  VARCHAR(64)  NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_marker_groups_slug (slug)
) ENGINE=MEMORY DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS markers (
    group_id    INT UNSIGNED NOT NULL,
    hash_key    CHAR(16)     NOT NULL,
    flag        TINYINT(1)   NOT NULL,
    expires_at  DATETIME     NOT NULL,
    PRIMARY KEY (group_id, hash_key),
    KEY idx_markers_expires_at (expires_at)
) ENGINE=MEMORY DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS view_cache (
    cache_group  VARBINARY(32)     NOT NULL,
    cache_key    VARBINARY(191)    NOT NULL,
    payload      VARBINARY(16384)  NOT NULL,
    expires_at   DATETIME          NOT NULL,
    PRIMARY KEY (cache_group, cache_key),
    KEY idx_view_cache_group (cache_group) USING BTREE,
    KEY idx_view_cache_expires_at (expires_at) USING BTREE
) ENGINE=MEMORY DEFAULT CHARSET=binary;

CREATE EVENT IF NOT EXISTS purge_expired_markers
    ON SCHEDULE EVERY 1 HOUR
    DO DELETE FROM markers WHERE expires_at <= NOW();

CREATE EVENT IF NOT EXISTS purge_expired_view_cache
    ON SCHEDULE EVERY 1 MINUTE
    DO DELETE FROM view_cache WHERE expires_at <= NOW();

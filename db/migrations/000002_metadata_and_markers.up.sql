-- Runtime tables that live in RAM: system metadata and markers.
--
-- ENGINE=MEMORY on purpose — the trade-offs it buys and costs are recorded in
-- docs/adr/0003-engine-memory-for-runtime-tables.md. What matters when reading
-- the DDL below:
--
--   * a MariaDB restart empties these tables but keeps their definitions. The
--     application re-registers its metadata at the next WorkerStart, and that
--     registration is idempotent by slug (SqlMetadataRegistry::register), so a
--     restart of either side alone is a no-op rather than a conflict.
--   * MEMORY is non-transactional: writes here survive a ROLLBACK. Nothing in
--     this file participates in a business invariant, so there is nothing to
--     undo.
--   * MEMORY takes table-level locks, which is why markers are swept on write
--     and filtered (never deleted) on read.
--
-- `id` auto-increments rather than being computed as count()+1 in PHP: with four
-- workers registering concurrently, a client-side counter is a race. Slug and id
-- are the whole row — MEMORY pads every VARCHAR to its declared width, so a
-- label column costs far more than the data it annotates on a table read during
-- every request. VARCHAR(64) is the ceiling the TableModules validate against.
--
-- Every statement survives being applied twice, for the reason given in
-- 000001_initial_schema.up.sql.

CREATE TABLE IF NOT EXISTS permissions (
    id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug  VARCHAR(64)  NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_permissions_slug (slug)
) ENGINE=MEMORY DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS telemetry_events (
    id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug  VARCHAR(64)  NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_telemetry_events_slug (slug)
) ENGINE=MEMORY DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marker_groups (
    id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug  VARCHAR(64)  NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_marker_groups_slug (slug)
) ENGINE=MEMORY DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Markers: one boolean flag per (group, key), where the key is an xxh64 digest
-- of a value the application never stores in the clear.
--
-- No foreign key to marker_groups: MEMORY does not support them. The group is
-- registered at boot before any marker can reference it, so the constraint is
-- upheld by construction rather than by the engine.
CREATE TABLE IF NOT EXISTS markers (
    group_id    INT UNSIGNED NOT NULL,
    hash_key    CHAR(16)     NOT NULL,
    flag        TINYINT(1)   NOT NULL,
    expires_at  DATETIME     NOT NULL,
    PRIMARY KEY (group_id, hash_key),
    KEY idx_markers_expires_at (expires_at)
) ENGINE=MEMORY DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backstop for the sweep that runs on every marker write. That sweep only fires
-- when something is written, so a quiet period would otherwise let expired rows
-- sit in RAM indefinitely. Reads already filter on expires_at, so this event is
-- about reclaiming memory, never about correctness.
--
-- Requires the server to run with event_scheduler=ON (the dev compose stack and
-- the integration harness both pass --event-scheduler=ON).
CREATE EVENT IF NOT EXISTS purge_expired_markers
    ON SCHEDULE EVERY 1 HOUR
    DO DELETE FROM markers WHERE expires_at <= NOW();

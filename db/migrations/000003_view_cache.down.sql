-- Reverses 000003_view_cache.up.sql.

DROP EVENT IF EXISTS purge_expired_view_cache;

DROP TABLE IF EXISTS view_cache;

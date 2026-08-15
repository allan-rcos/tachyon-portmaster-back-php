-- Retires the four ENGINE=MEMORY tables. The cache now lives in the API
-- process; see docs/adr/0011-cache-em-processo-openswoole.md.
--
-- 000002 and 000003 are deliberately left in place rather than deleted. A
-- migration that has been applied to a running database is history: removing the
-- file would leave `schema_migrations` naming a version golang-migrate can no
-- longer find, and an operator rolling forward from an older deployment would
-- skip straight from 000001 to a DROP of tables that were never created. So the
-- tables are still created there and dropped here, and a fresh database pays
-- three redundant DDL statements once at boot.
--
-- The events go before the tables they read: an EVENT whose table has been
-- dropped is not an error in MariaDB, it simply fails every time it fires, and
-- leaving that behind would put a recurring error in the log of a database that
-- is working correctly.
--
-- Nothing is migrated across. Every one of these tables was either rebuilt from
-- code at the next WorkerStart (permissions, marker_groups), bounded by a TTL
-- (markers), or recomputable from the durable tables (view_cache) — which is the
-- same property that made them safe to keep in RAM in the first place. The one
-- consequence a deploy will notice is that refresh tokens revoked before it are
-- valid again after it, exactly as they would have been across a MariaDB
-- restart.
--
-- Every statement survives being applied twice, for the reason given in
-- 000001_initial_schema.up.sql.

DROP EVENT IF EXISTS purge_expired_view_cache;
DROP EVENT IF EXISTS purge_expired_markers;

DROP TABLE IF EXISTS view_cache;
DROP TABLE IF EXISTS markers;
DROP TABLE IF EXISTS marker_groups;
DROP TABLE IF EXISTS permissions;

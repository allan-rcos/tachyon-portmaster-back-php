-- Reverses 000002_metadata_and_markers.up.sql.

DROP EVENT IF EXISTS purge_expired_markers;

DROP TABLE IF EXISTS markers;
DROP TABLE IF EXISTS marker_groups;
DROP TABLE IF EXISTS permissions;

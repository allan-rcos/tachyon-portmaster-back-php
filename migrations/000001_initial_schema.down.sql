-- Reverses 000001_initial_schema.up.sql, dropping tables in FK-dependency order.

DROP TABLE IF EXISTS telemetry_logs;
DROP TABLE IF EXISTS container_items;
DROP TABLE IF EXISTS containers;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS user_roles;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;

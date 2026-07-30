-- Development seed data — applied ONLY by the dev docker-compose stack, after
-- migrations. Never loaded by the integration tests or CI (they build their own
-- data through factories and POST /setup).
--
-- **No user or role here.** Bootstrapping is `POST /setup`, which creates the
-- first user together with a role holding every registered permission:
--
--   curl -X POST localhost:8000/setup -H 'Content-Type: application/json' \
--        -d '{"name":"Admin","email":"admin@portmaster.local","password":"Portmaster1"}'
--
-- That path is the one a real deployment uses, so the dev stack uses it too.
-- Seeding a user in SQL instead would mean carrying a pre-computed argon2id hash
-- and a hand-copied list of permission slugs in this file — and that list had
-- already drifted three slugs behind the code before anyone noticed, precisely
-- because nothing exercised it.
--
-- Ids are fixed small Snowflakes here for readability; the app Base62-encodes
-- them at the edge.
--
-- **Idempotent.** The compose `seed` service runs on every `docker compose up`,
-- while the `db_data` volume survives everything short of `down -v` — so the
-- second start always finds these rows already there. As plain INSERTs this
-- file failed on the duplicate primary key, and because `app` waits on
-- `seed: service_completed_successfully`, a failed seed meant the API never
-- started at all. Re-seeding a running deployment is the normal case, not an
-- error case, so every statement here must tolerate it.
--
-- ON DUPLICATE KEY UPDATE rather than INSERT IGNORE: re-seeding should bring a
-- row back to the value declared below, not silently accept whatever a
-- developer left in the table. Both survive a re-run; only this one converges.

INSERT INTO products (id, name, density, risk_class, search_name) VALUES
    (1, 'Liquid Nitrogen', 0.807, 'class-2-gases', 'liquid nitrogen'),
    (2, 'Sodium Hydroxide', 2.13, 'class-8-corrosive-substances', 'sodium hydroxide')
ON DUPLICATE KEY UPDATE
    name        = VALUES(name),
    density     = VALUES(density),
    risk_class  = VALUES(risk_class),
    search_name = VALUES(search_name);

-- The cargo of the two seeded containers is cleared before they are rewritten.
-- current_weight is a denormalised sum of container_items.weight, so restoring
-- the declared weight without also dropping the rows behind it would leave a
-- container claiming 0 kg while still holding cargo — the one inconsistency
-- this file is able to create and no endpoint would ever repair.
--
-- Scoped to ids 1 and 2: containers a developer created through the API are not
-- this file's business. telemetry_logs is deliberately left alone; it is an
-- append-only history, and an old entry does not contradict the row below.
DELETE FROM container_items WHERE container_id IN (1, 2);

INSERT INTO containers (id, code, current_weight, max_capacity, status, search_code) VALUES
    (1, 'CT-0001', 0, 1000, 'empty', 'ct-0001'),
    (2, 'CT-0002', 250, 1000, 'loading', 'ct-0002')
ON DUPLICATE KEY UPDATE
    code           = VALUES(code),
    current_weight = VALUES(current_weight),
    max_capacity   = VALUES(max_capacity),
    status         = VALUES(status),
    search_code    = VALUES(search_code);

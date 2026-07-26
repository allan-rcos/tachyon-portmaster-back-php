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

INSERT INTO products (id, name, density, risk_class, search_name) VALUES
    (1, 'Liquid Nitrogen', 0.807, 'class-2-gases', 'liquid nitrogen'),
    (2, 'Sodium Hydroxide', 2.13, 'class-8-corrosive-substances', 'sodium hydroxide');

INSERT INTO containers (id, code, current_weight, max_capacity, status, search_code) VALUES
    (1, 'CT-0001', 0, 1000, 'empty', 'ct-0001'),
    (2, 'CT-0002', 250, 1000, 'loading', 'ct-0002');

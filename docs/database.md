# Database

MariaDB 11, reached through `pdo_mysql` over a pooled connection. Schema and
seed data live in [`db/`](../db); the operational reference is
[`db/README.md`](../db/README.md), and this page covers the design behind it.

## Two kinds of table

**Durable (`ENGINE=InnoDB`)** — `roles`, `users`, `user_roles`, `products`,
`containers`, `container_items`, `telemetry_logs`. Business data, transactional,
survives a restart.

**Runtime (`ENGINE=MEMORY`)** — `permissions`, `marker_groups`, `markers`,
`view_cache`. Everything here is either rebuilt from the code on every boot,
bounded by a TTL, or recomputable from the durable tables, so durability buys
nothing and the round-trip cost is what matters — these are read on the
authorization path of every request. The consequences are real and deliberate;
see [ADR 0003](adr/0003-engine-memory-for-runtime-tables.md) and, for the cache,
[ADR 0010](adr/0010-read-cache-in-a-memory-table.md).

`view_cache` is in RAM for a second reason on top of speed. The object graph is
built inside `WorkerStart`, i.e. after OpenSwoole forks, so a cache held in
process would be one cache per worker and a write handled by one worker would
leave the others serving pages it had already invalidated. A row in a shared
table is visible to every worker — and to a second instance — the moment it is
written.

The one that catches people: **MEMORY is not transactional**. A `ROLLBACK` will
not undo a marker write. Nothing in those tables participates in a business
invariant, so there is nothing to undo — but do not put anything there that
does.

## Every datetime is UTC

Without exception, and enforced in four places rather than trusted:

| Where | How |
|---|---|
| PHP's clock | `date.timezone=UTC` in the image, and `date_default_timezone_set('UTC')` at boot so a run outside the image matches |
| Every connection | `PDO::MYSQL_ATTR_INIT_COMMAND` pins the session zone, in `PDOConfigFactory` — once per connection, reconnects included |
| The server | `--default-time-zone=+00:00` in the dev stack and the test harness |
| What leaves the API | `Shared\Time\Utc` renders the stored value as `2026-08-13T14:32:05Z` |

The first three are what make `NOW()` mean the same instant to the database and
to PHP. That matters because `NOW()` is not decoration: `markers` and
`view_cache` compute their `expires_at` from it and compare against it, so a
connection inheriting a local zone would expire entries early or late by the
offset — and `telemetry_logs.timestamp` is stamped from it too.

The fourth is a separate problem. MariaDB renders a `DATETIME` as
`2026-08-13 14:32:05`, which names no zone, so a client has to be told out of
band which one it is — and being told out of band is how a datetime gets
misread. The one datetime that reaches a client, the telemetry timestamp, is
therefore rendered with its `Z`.

Nothing converts *into* UTC, because no request carries a datetime. A field that
one day arrives from a client is a parser to add to `Shared\Time\Utc`, not a
zone to guess at.

## Ids

Application-generated, never `AUTO_INCREMENT`, except `telemetry_logs` and the
MEMORY registries. Stored as `BIGINT UNSIGNED`; Base62-encoded by the API layer,
so a client only ever sees an opaque string.

Snowflake ids take the worker id as their machine id, which is what makes four
workers generating concurrently safe without coordination.

The MEMORY registries *do* auto-increment, and for the opposite reason: their id
is a registry index, and computing it as `count() + 1` in PHP with four workers
registering at once is a race.

## Denormalised columns

Two, both deliberate:

- `containers.current_weight` — the sum of `container_items.weight`. Kept
  current by the manifest use cases inside the same transaction as the item
  write. This is why the dev seed clears `container_items` for the containers it
  rewrites: restoring the declared weight without dropping the rows behind it
  would leave a container claiming 0 kg while still holding cargo.
- `products.search_name`, `containers.search_code` — the normalised
  `LIKE`-filter key, written by `Infra\Text\SearchKey`. Filtering on the
  original column would mean a function call per row and no index.

## Transactions

`IUnitOfWork` opens the boundary; `IPdoTransaction` hands the enlisted `PDO` to
whoever needs it. The shape every write use case follows:

```php
$begin = $this->unitOfWork->begin();
if (!$begin->isSuccess()) { return Result::failure($begin->getErrorId()); }

// ... any failure from here on:
$this->unitOfWork->rollback();
return Result::failure($step->getErrorId());

$commit = $this->unitOfWork->commit();
```

Failures are returned as values rather than thrown precisely so the rollback
cannot be skipped by an exception unwinding past it.

**Two things deliberately do not enlist.** The read side
(`SqlQueryRepository`) and the boot-time registries (`SqlMetadataRegistry`)
lease a connection from `IPDOPool` directly: a read needs no boundary, and
registration runs at `WorkerStart` where there is no request and so no boundary
to join.

## Connection pool

`OpenSwoolePDOClientPool` holds a coroutine-safe pool per worker. `get()`
returns a `Result` — a lease can fail, and callers check it — and every path
that takes one returns it in a `finally`.

## Migrations

golang-migrate, applied by the `migrate` service in the dev stack and directly
by the Go test harness. Numbered pairs, `up` and `down` both required:

```
db/migrations/
├── 000001_initial_schema.up.sql
├── 000001_initial_schema.down.sql
├── 000002_metadata_and_markers.up.sql
├── 000002_metadata_and_markers.down.sql
├── 000003_view_cache.up.sql
└── 000003_view_cache.down.sql
```

Every statement is written to survive being applied twice — `CREATE TABLE IF NOT
EXISTS`, `DROP TABLE IF EXISTS`, `CREATE EVENT IF NOT EXISTS`. `schema_migrations`
already prevents a double apply on the normal path; this protects the abnormal
one, where an operator pipes a file straight into `mariadb` mid-incident and
gets "table already exists" instead of a repaired database.

## Seeds

`db/seeds/dev.sql`, applied only by the dev compose stack. Two rules:

**It creates no user or role.** Bootstrapping is `POST /setup`, which creates
the first user together with a role holding every *registered* permission. A
SQL-seeded user would mean a pre-computed argon2id hash and a hand-copied
permission list in the file — and that list had already drifted three slugs
behind the code before anyone noticed, precisely because nothing exercised it.
See [ADR 0004](adr/0004-setup-endpoint-instead-of-a-seeded-user.md).

**It is idempotent.** The compose `seed` service runs on every
`docker compose up` while the `db_data` volume survives anything short of
`down -v`, so re-seeding a populated database is the normal case. As plain
`INSERT`s it failed on the duplicate primary key, and because `app` waits on
`seed: service_completed_successfully`, a failed seed meant the API never
started. Statements use `ON DUPLICATE KEY UPDATE` rather than `INSERT IGNORE`:
re-seeding should bring a row back to the declared value, not silently accept
whatever a developer left there.

## Markers

A marker is one boolean flag per `(group, key)` with a TTL, where the key is an
xxh64 digest of a value never stored in the clear. Refresh tokens use one: the
marker is what makes a consumed token stop working, since the token itself
remains validly signed and unexpired.

Because MEMORY takes table-level locks, reads *filter* on `expires_at` rather
than deleting what they find expired, and the sweep happens on write — where the
lock is already held — plus hourly via a MariaDB `EVENT`. That event needs the
server started with `--event-scheduler=ON`, which both the dev stack and the
test harness pass.

# Database

MariaDB 11, reached through `pdo_mysql` over a pooled connection. Schema and
seed data live in [`db/`](../db); the operational reference is
[`db/README.md`](../db/README.md), and this page covers the design behind it.

## One kind of table

**`ENGINE=InnoDB`, all of them** — `roles`, `users`, `user_roles`, `products`,
`containers`, `container_items`, `telemetry_logs`. Business data, transactional,
survives a restart.

There used to be a second kind. `permissions`, `marker_groups`, `markers` and
`view_cache` were `ENGINE=MEMORY`: rebuilt from code on every boot, bounded by a
TTL, or recomputable from the durable tables, so durability bought nothing and
the round trip was what mattered. They were in MariaDB rather than in the
application only because the object graph is built inside `WorkerStart`, after
OpenSwoole forks, which would have made an in-process cache one cache per worker.

Allocating the shared memory *before* the fork removes that constraint, and all
four moved into the API process — see
[ADR 0011](adr/0011-cache-em-processo-openswoole.md). **Nothing that wants to
live in RAM belongs in a table here any more**; it belongs behind
`Infra\Cache\ICacheProcessDatabase`. What is left in this database is durable by
definition, so the rule for a new table is simply InnoDB.

Migrations 000002 and 000003 still create the four and 000004 drops them again,
because an applied migration is history and is corrected by a new one rather than
edited. The schema a running system ends up with holds none of them.

## Every datetime is UTC

Without exception, and enforced in four places rather than trusted:

| Where | How |
|---|---|
| PHP's clock | `date.timezone=UTC` in the image, and `date_default_timezone_set('UTC')` at boot so a run outside the image matches |
| Every connection | `PDO::MYSQL_ATTR_INIT_COMMAND` pins the session zone, in `PDOConfigFactory` — once per connection, reconnects included |
| The server | `--default-time-zone=+00:00` in the dev stack and the test harness |
| What leaves the API | `Shared\Time\Utc` renders the stored value as `2026-08-13T14:32:05Z` |

The first three are what make `NOW()` mean the same instant to the database and
to PHP. That matters because `NOW()` is not decoration: `telemetry_logs.timestamp`
is stamped from it, and a connection inheriting a local zone would record every
event off by the offset. Expiry no longer depends on it — the cache keeps its own
clock in the API process now — which narrows the blast radius without making the
setting optional.

The fourth is a separate problem. MariaDB renders a `DATETIME` as
`2026-08-13 14:32:05`, which names no zone, so a client has to be told out of
band which one it is — and being told out of band is how a datetime gets
misread. The one datetime that reaches a client, the telemetry timestamp, is
therefore rendered with its `Z`.

Nothing converts *into* UTC, because no request carries a datetime. A field that
one day arrives from a client is a parser to add to `Shared\Time\Utc`, not a
zone to guess at.

## Ids

Application-generated, never `AUTO_INCREMENT`, except `telemetry_logs`. Stored as
`BIGINT UNSIGNED`; Base62-encoded by the API layer, so a client only ever sees an
opaque string.

Snowflake ids take the worker id as their machine id, which is what makes four
workers generating concurrently safe without coordination.

The permission and marker-group registries number their entries `count() + 1`,
which used to be an `AUTO_INCREMENT` because four workers racing a PHP counter
would collide. They agree without one now for a different reason: the catalogue
is a pure function of the source, so every worker derives the same slugs in the
same order. See [ADR 0011](adr/0011-cache-em-processo-openswoole.md).

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

**One thing deliberately does not enlist.** The read side
(`SqlQueryRepository`) leases a connection from `IPDOPool` directly, because a
read needs no boundary. The registries used to be the second case; they no longer
touch the database at all.

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
```

Every statement is written to survive being applied twice — `CREATE TABLE IF NOT
EXISTS`, `DROP TABLE IF EXISTS`. `schema_migrations`
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

Markers live in the cache process, not in this database
([ADR 0011](adr/0011-cache-em-processo-openswoole.md)). A read filters on the
expiry rather than deleting what it finds expired, so correctness never depends
on when the sweeper last ran; the sweeper reclaims the memory on a timer.

The TTL is the caller's, not the store's: `IMarkerRepository::set()` takes it as
an argument and passes it through as a `CacheProcessEntryConfig`, because a
refresh-token marker has to outlive exactly the token it tracks. It is the one
place in the cache where a single write overrides its database's default.

The cost of the move, and it is deliberate: markers are now per-instance and
per-process. Restarting the API un-revokes every revoked refresh token.

# 0010. Keep the read cache in an `ENGINE=MEMORY` table

**Status:** Accepted · 2026-08-13

## Context

Every listing endpoint reached MariaDB on every request. `ListContainersDQL`
joins, filters and counts the filtered set in a correlated sub-select;
`ListContainerSummariesDQL` additionally aggregates a manifest and up to ten
telemetry entries per row; `MetricsDQL` is eight correlated aggregates over the
whole yard. None of that changes between two reads of an untouched resource.

The Rust implementation of this same API already has the answer, and it is worth
copying rather than re-deriving: one port, `ViewCacheRepository`, with
`get`/`put`/`invalidate` over a `(group, key)` pair; the key written by the DQL,
because the DQL is what knows what the query is; the group named by the caller,
so a write can drop everything a resource caches without knowing which listings
exist.

Two things did not carry over.

**There is no single process.** Rust holds the entries in a Moka map inside one
binary. Here the object graph is built inside `WorkerStart`, after OpenSwoole
forks, so anything allocated there is allocated once per worker. An in-process
map would be four maps, and a write handled by worker 1 would leave worker 2
serving the page it had already cached. This is precisely the bug
[ADR 0002](0002-metadata-registries-in-the-database.md) records for the metadata
registries.

`OpenSwoole\Table` *is* shared memory and would work if it were allocated before
the fork — that is the "revisit if" ADR 0002 left open. Taking it would mean
moving the router's construction out of `WorkerStart`, which is a larger change
to the composition root than the cache justifies, and it would still stop at the
container boundary: two API instances behind a balancer would not share an
invalidation.

**There is no bincode.** PHP's own `serialize()` is the obvious substitute.

## Decision

A `view_cache` table, `ENGINE=MEMORY`, behind an `IViewCacheRepository` port,
with the views serialised by **igbinary**.

**The port is the seam, and the use cases call it directly.** No decorator over
`IQueryRepository`. A decorator would have spared six repetitions of the
read-through, and was rejected because the group a write drops is an application
decision: hiding the read half of that policy in the infrastructure layer would
put the two halves of one rule in different layers. Naming the group in the use
case is also what keeps the port swappable — moving the entries to Redis is a
second implementation and one line in `InfraProvider`, with no use case touched
and the SQL side not involved at all.

**Reads by id are not cached**, unlike the Rust original. A `SELECT` on the
primary key in InnoDB costs about what the lookup in `view_cache` costs, so
caching one buys a round trip and spends a round trip. Only the listings and the
metrics panel go through the cache. Every DQL still answers `cacheKey()` — the
key is what a query *is*, not the decision to store it — so turning one on later
is a line in a use case.

**igbinary rather than `serialize()`.** Measured on the real view shapes: a
20-item product page is 1.6 KB against 3.0 KB, and a default page of container
summaries carrying eight cargo lines each is 11.3 KB against 64 KB — igbinary
deduplicates the strings repeated across nested items, and the nested views are
exactly the ones worth caching. On a MEMORY table, where the payload column is
padded to its declared width, that is the difference between the page fitting in
a column the server can afford and not. It round-trips `Ds\Seq`, readonly
classes and backed enums, and answers `null` for a payload it cannot read, which
is how an entry written by an earlier deploy is supposed to look.

**A write drops only its own group.** A container write changes what the metrics
panel reports and does not drop it; the panel lives with the 30-second TTL
instead. Reaching across groups would mean every write had to know who else
reads what it touched.

### Making a hit observable

A cache is invisible from outside whether it is working or not. A hit and a miss
carry identical bodies, and so do a fresh page and one that should have been
invalidated — so a cache that had quietly stopped storing anything, or one whose
invalidation had been removed, would pass every test in the suite.

The response therefore says which it was, through the RFC 9211 field
`Cache-Status: Portmaster; hit`. The standard field rather than an `X-` name of
our own, because it is a *list*: a CDN or reverse proxy in front appends its own
entry instead of colliding with this one, and `hit` / `fwd=` / `ttl=` is
vocabulary every cache already speaks.

It is absent on a miss rather than carrying `fwd=miss`. The fuller vocabulary
would need the middleware to know that a cache was consulted and missed, which
is a second event — and a distinction the reads that skip the cache entirely
could not make. Silence therefore means "this cache took no part", which is what
a read by id should say. The integration suite asserts all three: a repeated
listing hits, a listing read after a write does not, and a read by id never does.

Getting that from the use case to the header needed a channel, because the use
cases set no headers and the middlewares know nothing about caching.
`App\Events\IMetaEventStack` is it: a use case reports `MetaEvent::ViewCacheHit`
as it returns a hit, and `CacheHeaderMiddleware` reads it back once the stack has
unwound. Deliberately one-way — nothing branches on an event, so removing every
emit would change no response body.

The events live in the **coroutine context**, not in a field on the stack. The
object graph is built once per worker, so a field would be shared by every
request that worker has in flight, and under `enable_coroutine` that is several
at once: one request's hit would mark another's response. It is the mechanism
`API\Http\RequestAttributes` already uses. The cost is that the application layer
names `OpenSwoole\Coroutine` in one class, which nothing else in it does; the
alternative was to have the API layer own the storage and pass it down, which
puts a request-scoped object into constructors built at boot.

## Consequences

- **A cache write is two statements**, a `DELETE` then an `INSERT`, because
  Atlas has no upsert and nothing in the infrastructure layer reaches past Atlas
  to raw SQL. The pair is not atomic: a reader landing between them finds
  nothing and recomputes a page that was about to be cached. That is a wasted
  query, never a wrong answer.
- **A cache lookup is a round trip**, not a memory read. It replaces a join, a
  `COUNT(*)` and an aggregation with a primary-key hit on a RAM table, which is
  why it is worth doing for listings and why it is not worth doing for reads by
  id.
- **MEMORY supports no `BLOB` or `TEXT`**, so the payload is a fixed
  `VARBINARY(16384)`, and MEMORY pads every row to that width — a row costs
  ~16.6 KB whatever it holds. `SqlViewCacheRepository` declines to store
  anything wider, which also means a `?limit=100000` is answered from the
  database and cached nowhere. That ceiling is a private constant on the
  implementation, not on the port and not in `CacheLimits`: it is an artefact of
  the column, and a Redis implementation would have no equivalent.
- **The server needs `--max-heap-table-size`.** The default 16 MB fills at about
  970 rows and the table then answers error 1114. The dev stack and the
  integration harness both pass 256M, which is about 15,000 rows.
- **Binary never crosses the connection.** igbinary output is not valid UTF-8 and
  the connection charset is `utf8mb4`, so the payload is hex on the wire and
  `UNHEX`/`HEX` at the boundary. It doubles the bytes sent and costs nothing in
  the column.
- **A cache failure is never a read failure.** Every path in the repository that
  cannot do its job answers a miss and logs at warning level. A full table, a
  slow lease, an entry in an older format — all degrade into recomputation.
- **Writes carry one more collaborator.** Sixteen write use cases now take
  `IViewCacheRepository` and call `invalidate()` after a successful commit, never
  before: a concurrent read in between would repopulate the cache from the state
  being replaced.
- **Cross-group staleness is real and bounded.** The metrics panel is behind by
  up to one TTL after a container write, on purpose. So is the user listing after
  an account update — which is also how the Rust implementation behaves.

  Five writes therefore drop nothing at all, and each absence is deliberate:

  | Write | Why it drops nothing |
  |---|---|
  | `LoginUseCase`, `ValidateSessionUseCase` | They write markers, which no cached view reads. |
  | `ChangePasswordUseCase`, `UpdateAccountUseCase` | Account operations. They touch the `users` table `ListUsersDQL` reads, so the user listing is behind by up to one TTL — exactly as in Rust, where an account write dropped the account group and never `user`. |
  | `SetupUseCase` | Answers 409 once the system is set up, so it runs once against an empty one. Nothing can be cached then: every cached read needs an authenticated caller, and before setup there is none. |

  `ManifestPersistence` also writes and commits without invalidating, because it
  is not a use case — its two callers name the group instead.

## Revisit if

The round trip itself shows up in a profile. At that point the port is where an
`OpenSwoole\Table` L1 in front of this table would go, and taking it means first
moving the object graph's construction out of `WorkerStart` so the table can be
allocated before the fork. The move without the profile is not a reason.

Also revisit if the working set stops fitting: the fixed-width payload column is
what makes capacity a RAM calculation rather than an entry count, and a cache
that needs to hold far more, or far larger, pages wants Redis behind the same
port rather than a wider column.

# 0011. Move the cache and the registries into an OpenSwoole process

**Status:** Accepted · 2026-08-15

Supersedes [0002](0002-metadata-registries-in-the-database.md),
[0003](0003-engine-memory-for-runtime-tables.md) and
[0010](0010-read-cache-in-a-memory-table.md).

## Context

Four tables were `ENGINE=MEMORY` in MariaDB: `permissions`, `marker_groups`,
`markers` and `view_cache`. None of them holds a business invariant. All four
were there for one reason, recorded twice:

> The object graph is built inside `WorkerStart`, which runs **after** OpenSwoole
> forks. Each worker therefore allocated its own table.

That made an `OpenSwoole\Table` wrong for anything written at runtime — a marker
set on worker 2 was invisible to worker 3, and an invalidation on worker 1 left
worker 2 serving the page it had already cached. MariaDB was the only shared
memory available, so the cache became a round trip: a network hop, a SQL parse
and a padded `VARBINARY` to do what should have been a memory read.

Both ADRs left the same escape hatch open, and it was never about OpenSwoole
gaining a feature — the feature was always there:

> **0002, Revisit if:** OpenSwoole gains a table that can be allocated before the
> fork and shared across workers.
> **0010, Revisit if:** taking it means first moving the object graph's
> construction out of `WorkerStart`.

It turns out the second is not required either. Only the *table* has to be
allocated before the fork, not the graph that uses it: a table created in the
global context is inherited by every worker, and the per-worker graph can pick it
up at `WorkerStart` as a collaborator it was handed rather than one it built.

igbinary landing in production (0010) removed the other half of the objection.
Serialising a nested `Ds\Seq` of readonly views is no longer the hard part.

## Decision

The cache lives in the API process. MariaDB holds nothing but InnoDB.

**Two `OpenSwoole\Table`s, allocated before `$server->start()`**, by
`Infra\OpenSwooleExtension` — the pre-fork sibling of `InfraRegister`. It answers
an `IOpenSwooleExtensionProvider`, which is threaded down the register chain to
`InfraProvider`. Anything else that has to be one thing across all workers — a
queue, a counter, a rate limiter — belongs on that provider, allocated the same
way; the cache is simply the first.

**A process, added to the server, does what the tables cannot.** It expires
entries on a timer and evicts when occupancy passes a high-water mark, because
`OpenSwoole\Table` has neither a clock nor an LRU and a write to a full table
just fails. It is the MariaDB `EVENT`s this replaces, moved in-process.

**`ICacheProcessDatabase` is the seam**, and it has three methods — `get`, `put`,
`clean` — with everything variable pushed into two value objects:

| | |
|---|---|
| `CacheProcessDatabaseConfig` | the slice: a key prefix and a default TTL, fixed once in `InfraProvider` |
| `CacheProcessEntryConfig` | the operation: an optional suffix and an optional TTL override |

The per-entry TTL is not speculative generality; it is what markers need.
`IMarkerRepository::set()` is handed a lifetime by whoever writes the marker,
because a refresh-token marker has to outlive exactly the token it tracks. A bare
`?string $suffix` could not carry that, and a third parameter on `put()` would
have to be declared on two methods that have no use for it.

**Values cross the port, not bytes.** Serialization is the database's business,
so igbinary is named in one class instead of four.

**Keys are digested by `IIndexHasher`, the domain's own hasher**, not by a call
to `hash()` written here. An `OpenSwoole\Table` key may be 63 bytes and the
extension truncates silently past that, so a digest is required; a second opinion
about which algorithm to use is not. `XxHasher` already exists for exactly this —
turning a value into a short, stable lookup key — and markers were already using
it. Writing `hash('xxh128', …)` inline would have put a hashing decision in the
infrastructure layer, when the split between an index hash and a secure one is
what `IIndexHasher` and `ISecureHasher` exist to keep explicit. It reaches
`InfraProvider` through the register chain, which is why `AppRegister` now builds
the domain provider before the infrastructure one.

**Failures are reported, not flattened into empty successes.** A miss is a 404, a
key or value the store cannot address is a 422, a store that broke is a 500.
`Result::void()` appears only where a method has nothing to return and nothing
went wrong — which is what `void` means, and it is not a synonym for "a success
carrying null".

That distinction has teeth. A cache miss really is "what you asked for is not
there"; that it *also* means "read the database instead" is a decision only the
caller can make. `ListProductsUseCase` makes it by ignoring the failure and
running the DQL. `SetMarkerUseCase` makes the opposite one: it has to tell a 404
(no marker, so the value has no history and may be raised) from a 500 (the store
could not be read, so raising anything is how a replay gets through), and it
branches on the code to do exactly that. A port answering an empty success for
both would have made that impossible to write correctly.

The one place an error is answered rather than passed on is
`CacheProcessMetadataRegistry::catalogue()`, and it earns it: before the first
registration the catalogue genuinely is empty, so "nothing is filed under this
key" and "the catalogue is empty" are the same statement.

**No repository contract changed shape.** `IPermissionRepository`,
`IMarkerGroupRepository`, `IMarkerRepository` and `IViewCacheRepository` keep
every signature they had, which is the evidence that 0010 was right about where
the seam was. What changed is what a `Result` from them *means*, per the
paragraph above — and the only use case that had to move with it is
`SetMarkerUseCase`, which is exactly the one whose logic turns on telling a
missing marker from an unreadable store. The six listing use cases were not
touched: ignoring a failed lookup and running the DQL is what they already did.

### Two tables, not one

`index` holds the logical key and the expiry; `store` holds the payload. Split
purely for the operations that *iterate* — `clean()` and the sweeper. Iterating
an `OpenSwoole\Table` materialises every row as a PHP array, so a 16 KB payload
on the scanned row is copied whether or not it is wanted. Measured over 8192
entries of ~11 KB:

| Layout | prefix `clean` | `get` | RAM |
|---|---|---|---|
| One table | 20.5 ms | 0.4 µs | 261 MB |
| `index` + `store` | **2.5 ms** | 0.4 µs | 262 MB |

Invalidation runs in sixteen write use cases. The difference is not academic.

### Keys are hashed

An `OpenSwoole\Table` key may be 63 bytes and the extension **silently truncates**
anything longer, so two long keys sharing a prefix would collapse into one entry
— and cache keys carry a caller's search term, which has no length bound. The
table key is therefore the `IIndexHasher` digest of the logical key, sixteen hex
characters, and the logical key itself is kept in `index.logical` so a prefix
clean has something to match. `get()` compares it back and treats a mismatch as a
miss, which costs nothing and removes the collision argument entirely.

A logical key past `LOGICAL_KEY_MAX_BYTES` is refused rather than truncated, and
that refusal is a 422 rather than a miss: the entry is not absent, it could never
have been written.

The logical key is separated from the suffix by a `:`. Without it, cleaning the
whole `marker` database would also drop `marker-group`, one key being a prefix of
the other.

### The registries hold their whole catalogue in one entry

`ICacheProcessDatabase` has no listing and `IPermissionRepository::all()` needs
one, so each registry keeps its contents under the database's bare key and
rewrites it on every addition. Affordable because a catalogue is bounded by how
much code is written and is filled once, at boot.

The read-modify-write is not atomic across workers. It cannot produce a wrong
answer: the catalogue is a pure function of the source, every worker runs the
same constructors in the same order, and each keeps appending until its own full
list is stored. This is the "the copies agree by construction" 0002 relied on,
now with one copy instead of four.

## Consequences

- **A cache read is 0.4 µs instead of a round trip.** That is the point.
- **Two API instances behind a balancer no longer share anything.** Each has its
  own cache and its own markers. Accepted deliberately: this is a
  single-instance monolith. It is the one thing the MEMORY tables did better.
- **Restarting the API un-revokes every revoked refresh token.** Markers are
  memory now. Previously only a MariaDB restart did this, so the window widened
  rather than opened. Accepted on the same grounds, and it is the first thing to
  reconsider if this ever runs as more than one instance.
- **Capacity is declared, not discovered.** `APP_CACHE_ENTRIES` and
  `APP_CACHE_PAYLOAD_BYTES` size the tables at boot and cannot grow afterwards.
  The defaults cost ~261 MB, the same order as the `--max-heap-table-size=256M`
  the MEMORY table needed — the RAM changed owner, not size.
- **The payload column is still fixed-width and padded**, so a view serialising
  past it is still not cached. The constraint survived the move; only the engine
  enforcing it changed.
- **MariaDB lost two flags.** `--event-scheduler=ON` and
  `--max-heap-table-size=256M` are gone from the compose stack and the
  integration harness. 000002 and 000003 still *create* the events and the MEMORY
  tables, but nothing fires them and 000004 drops them moments later; creating an
  empty MEMORY table needs neither flag.
- **The old migrations stay, and 000004 undoes them.** Deleting 000002 and 000003
  would have left `schema_migrations` naming versions golang-migrate cannot find,
  and an operator rolling forward from an older deployment would jump from 000001
  to a `DROP` of tables nothing had created. An applied migration is history. A
  fresh database pays three redundant DDL statements once at boot, and the `down`
  of 000004 restores all four tables exactly, so the harness's
  drop-and-re-migrate reset stays a true inverse.
- **The integration harness still restarts the API between stories**, for an
  inverted reason: a schema drop used to clear the registries and now cannot
  reach them, and the read cache would otherwise carry one story's pages into the
  next.
- **PHPStan needs a bootstrap file.** OpenSwoole declares
  `Process::alarm(int $intervalUsec, int $type = ITIMER_REAL)`, and PHP 8.4
  removed `pcntl_setitimer` along with the `ITIMER_*` constants. Reflecting that
  method aborts the *entire* analysis — not one file — as soon as any source
  names `OpenSwoole\Process`. `phpstan-bootstrap.php` defines the constant.
  Installing `ext-pcntl` does not help: the constant is gone from PHP, not from
  the extension.
- **`OpenSwoole\Process::name()` does not exist**, whatever the ide-helper stub
  says. Calling it kills the process, and the server restarts it in a loop. This
  is why the class is not stubbed for PHPStan — a stub would let that call pass
  analysis.

## Revisit if

A second instance is deployed. At that point markers and invalidation both need
to cross the process boundary again, and the answer is Redis behind the same
`ICacheProcessDatabase` — not a return to MariaDB, which was only ever standing
in for shared memory.

Also revisit if the working set stops fitting. Capacity here is a RAM
calculation, and a cache that needs to hold far more, or far larger, pages wants
a store with real eviction rather than a wider column.

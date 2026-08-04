# `src/`

Five layers. Reasoning and the request path are in
[`docs/architecture.md`](../docs/architecture.md); this is where to look up
**where a class goes**.

```
src/
├── API/       HTTP: routes, middleware, controllers, wire format
├── App/       use cases, commands, queries, authorization
├── Domain/    models, table modules, id generation, hashing
├── Infra/     database, repositories, read queries, logging
└── Shared/    Result and the error registry
```

Dependencies point one way — `API → App → Infra → Domain`, with `Shared` used by
all of them. `Domain` depends on nothing but `Shared`.

## Conventions

**`I<Name>` is the contract, `Interno/<Name>` is the implementation.** Nothing
outside a layer names an `Interno` class except the provider that builds it.
(`Interno`/`Internal` is Portuguese for *internal*; the codebase is otherwise in
English.)

**Providers build everything.** There is no DI container. Each layer has a
register that chains downward and a provider that constructs and memoizes its
objects. The whole graph is built at `WorkerStart`, after the fork, so every
instance is per-worker.

**Fallible operations return `Result`, never throw.** Exceptions are for
programming errors — a use case that cannot register its permission at boot,
where there is no request to fail.

**Validation lives in table modules.** Not in controllers, not in repositories.

## Where things go

| I am adding… | It goes in |
|---|---|
| an endpoint | the current version's table in `API/Http/Router/Interno/` + a controller in `API/Controllers/` |
| something that runs per request before the controller | `API/Http/Middleware/` |
| a request or response body | a `*Proxy` in `API/Fbs/<Feature>/` |
| an action a user performs | `App/Commands/` + `App/Services/` |
| a read | `App/Queries/` + `Infra/Query/` |
| a business rule | the feature's `*TM` in `Domain/TableModules/` |
| a thing the business talks about | `Domain/Models/` |
| SQL that writes | `Infra/Repository/Interno/` |
| SQL that reads | `Infra/Query/Interno/` as a `*DQL`, returning a `View` |

## Layer notes

### `API/`

`Http/` holds the middleware stack, in order: `Recoverer` → `RequestId` →
`Logging` → `FlatBufferNegotiation` → `Authentication` → `RouteDispatch`.

`Fbs/` is half generated, half not. Files carrying the flatc header are
overwritten by `composer flatbuffers` — never edit them. Their hand-written
`*Proxy` siblings are where everything else goes.

Controllers resolve the caller, build a command or query, and map the result.
They do **not** check permissions.

Route ids are Base62 (`[A-Za-z0-9]+`), never numeric. A literal segment must be
registered before a `{id}` pattern that would also match it.

### `App/`

One use case per action, each owning exactly one permission — declared in its
own constructor via `AuthorizesWithPermission`, enforced as the first line of
`execute()`. Because the permission catalogue is built from those declarations,
`POST /setup` grants new permissions automatically.

Write use cases follow one shape: authorize, `begin`, build, persist, `commit` —
with `rollback` on every failure path.

`Interno/Provider/*Provider` splits the wiring by feature; `AppProvider`
re-exports them.

### `Domain/`

`Models/` — `I<Thing>` contracts and `Internal/<Thing>` readonly data classes.

`TableModules/` — `<Thing>TM`, where every rule lives. They build models and
refuse to build invalid ones, returning `Result`.

`ID/` — Snowflake (`IDatabaseIdGenerator`, ordered, worker id as machine id),
NanoId and ULID. `Security/` — argon2id for passwords, xxHash for index keys.

### `Infra/`

`Database/` — `IUnitOfWork` opens the transaction boundary, `IPdoTransaction`
hands the enlisted `PDO` out, `Pool/` keeps a coroutine-safe pool per worker.

`Repository/` — writes, enlisted in the caller's transaction.

`Query/` — reads. A `*DQL` selects exactly the columns an endpoint needs and
maps rows into a `View`; no domain model is reconstituted. Reads lease their own
connection and take no transaction.

Two registries deliberately bypass the transaction session and lease directly:
`SqlQueryRepository` (a read needs no boundary) and `SqlMetadataRegistry` (boot
runs outside any request).

### `Shared/`

`Result` — success with a value, or failure with an error id. `Leaf` — the
process-wide registry those ids index into. `LeafContext` — the message, details
and HTTP status a failure resolves to.

## Documentation

Every hand-written file carries a file docblock, a class docblock, and a
docblock on every member. Format and rules:
[`docs/documentation.md`](../docs/documentation.md).

```bash
composer phpdoc     # renders src/ to build/docs
composer phpstan    # level 9 — must stay clean
```

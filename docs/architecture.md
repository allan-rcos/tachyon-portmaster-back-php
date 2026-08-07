# Architecture

Portmaster is a container-yard API: products are catalogued, containers are
registered, cargo is loaded and unloaded against a manifest, and a container is
sealed and dispatched once it holds enough. It runs on PHP 8.4 under OpenSwoole,
speaks FlatBuffers (and JSON) over HTTP, and persists to MariaDB.

## The five layers

```
src/
├── API/       HTTP: routes, middleware, controllers, wire format
├── App/       use cases, commands, queries, authorization
├── Domain/    models, table modules, id generation, hashing
├── Infra/     database, repositories, read queries, logging
└── Shared/    Result and the error registry
```

Dependencies point one way:

```
API ──► App ──► Infra ──► Domain
                  │          ▲
                  └──────────┘
        Shared is used by all of them
```

`Domain` depends on nothing but `Shared`. `Infra` implements the repository
contracts and depends on `Domain` for the models it stores. `App` orchestrates
both. `API` knows only `App` — plus the FlatBuffers proxies that are its own
wire format.

### Interface / implementation convention

Every layer publishes contracts and hides implementations:

- `IProductRepository` — the contract, at the top of the directory.
- `Interno/SqlProductRepository` — the implementation, one level down.

(`Interno`, and `Internal` under `Domain/Models`, is Portuguese for *internal*;
the codebase is otherwise in English.) Nothing outside a layer names an
`Interno` class except the provider that constructs it. That is what lets
`SqlProductRepository` be swapped for an in-memory double in a unit test without
any caller noticing.

### Providers: composition, not a container

There is no DI container. Each layer has a provider that builds its own objects
and hands them out, and a register that chains to the layer beneath:

```
ApiRegister ──► AppRegister ──► InfraRegister ──► DomainRegister
     │               │                │                 │
 ApiProvider    AppProvider     InfraProvider     DomainProvider
```

The whole graph is built inside `WorkerStart` — after OpenSwoole forks — so each
worker owns its own instances. Providers memoize (`$this->x ??= new X(...)`), so
"one per worker" is the real lifetime of everything here.

`AppProvider` grew too large to read as one class and is now split by feature
into `App/Interno/Provider/*Provider`, each extending `FeatureProvider` and
re-exported by `AppProvider`. See
[ADR 0006](adr/0006-layered-providers-per-feature.md).

## A request, end to end

`GET /products` with a session cookie:

1. **`src/API/main.php`** — the OpenSwoole `request` handler converts the raw
   request into PSR-7 and passes it to the worker's handler.
2. **Middleware stack** (`ApiProvider::router()`), outermost first:
   - `RecovererMiddleware` — turns an escaped exception into a 500 instead of a
     dead worker.
   - `RequestIdMiddleware` — stamps a correlation id.
   - `LoggingMiddleware`.
   - `ContentNegotiationMiddleware` — resolves `Content-Type` and `Accept` to a
     pair of strategies and records them for the request.
   - `AuthenticationMiddleware` — validates the JWT from the cookie and attaches
     the caller.
   - `RouteDispatchMiddleware` — matches `RouterHub::dispatcher()` and calls the
     controller.
3. **Controller** (`ProductController::list`) — resolves the caller via
   `ResolvesCaller`, reads query parameters, and builds a `ListProductsQuery`.
   It does **not** check permissions.
4. **Use case** (`ListProductsUseCase::execute`) — calls `$this->authorize()`
   first, then does its work.
5. **Read side** — a query goes through `IQueryRepository` to a `*DQL` and comes
   back as a `View` object. A write goes through a `TableModule` (validation)
   and an `IRepository` (persistence) inside a `IUnitOfWork` boundary.
6. **Response** — the controller fills a `*XResponse` DTO, wraps it in the
   matching abstract factory and hands both to the accepts strategy, which
   renders binary or JSON depending on what the negotiation middleware decided.
   That rendering answers a `Result` like everything else that can fail, and the
   controller turns a failed one into a 502. `ResponseEmitter` writes the bytes
   out.

## Reads and writes are not symmetric

**Writes** go `Command → UseCase → TableModule → Repository`, wrapped in a
transaction:

```php
$begin = $this->unitOfWork->begin();
$built = $this->productTM->create(...);   // validation lives in the TM
if (!$built->isSuccess()) { $this->unitOfWork->rollback(); return ...; }
$this->products->insert($product);
$this->unitOfWork->commit();
```

**Reads** go `Query → UseCase → IQueryRepository → DQL → View`, with no
transaction and no domain model — a `DQL` produces exactly the columns the
endpoint needs, and a `View` is a plain DTO carrying them. Reconstituting a
domain model just to read three fields off it would cost more and prove nothing.

## Table Modules

A `TableModule` (`Domain/TableModules/*TM`) is where a type's rules live. It
builds models and refuses to build invalid ones:

```php
$result = $this->productTM->create($name, $density, $riskClass);
// Result<IProduct>, or a 422 failure carrying which rule was broken
```

Nothing else validates. A controller does not check that a name is non-empty and
a repository does not check that a container has capacity — both would be a
second place for the rule to drift. This is also why the unit suite tests table
modules directly: it is testing the rules at the only place they exist.

## Errors: Result, not exceptions

Every operation that can fail returns `Shared\Exceptions\Result` — a success
with a value, or a failure with an error id. The id indexes into `Leaf`, which
holds a `LeafContext` with the message, details and HTTP status.

```php
return Result::failure(Leaf::newError(new LeafContext(
    message: 'This system has already been set up.',
    code: 409,
)));
```

Failures are values so a use case can roll a transaction back before returning
one, which an exception unwinding the stack would skip. The API layer never
decides a status code; it maps `LeafContext->code` in `ProblemResponse`.

Exceptions are reserved for genuine programming errors — a use case that cannot
register its own permission at boot throws, because there is no request to fail
and a worker in that state is not serviceable.

## Authorization

Each guarded use case owns exactly one permission, declares it in its own
constructor at `WorkerStart`, and enforces it as the first line of `execute()`:

```php
public function __construct(..., IRegisterPermissionUseCase $registrar) {
    $this->permission = $this->declarePermission($registrar, 'product:create');
}

public function execute(CreateProductCommand $command): Result {
    $denied = $this->authorize($command->context);
    if (!$denied->isSuccess()) { return Result::failure($denied->getErrorId()); }
```

The permission catalogue is therefore the code itself — `POST /setup` grants the
first role every *registered* permission, so a permission introduced by a new
use case is granted without anyone maintaining a list. See
`App/Security/AuthorizesWithPermission`.

## Identifiers

Ids are application-generated and opaque at the edge. `Domain/ID` offers three
generators behind three contracts — Snowflake (`IDatabaseIdGenerator`, ordered,
worker id as the machine id), NanoId and ULID (`IRandomIdGenerator`,
`ISequentialIdGenerator`). Database ids are `BIGINT UNSIGNED`; the API layer
Base62-encodes them, which is why route patterns are `[A-Za-z0-9]+` and never
`\d+`.

## Wire format

Requests and responses are FlatBuffers tables generated by `flatc` from the
schemas in the `swagger/` submodule, and `src/API/Fbs/` holds nothing but those
generated classes — they are never edited. Everything hand-written lives beside
them in `src/API/Negociation/`, split three ways:

- a **DTO** (`LoginXRequest`, `ProductListXResponse`) — data, no behaviour, no
  inheritance;
- an **abstract factory** per message — knows that message's fields and the
  schema's key names, and can produce it from, or render it as, either format;
- a **strategy** per wire format, two on the way in and two on the way out —
  knows the technique and no message at all.

The controller picks neither format: the
`ContentNegotiationMiddleware` reads the two headers, records a strategy for the
request, and on the way out stamps the `Content-Type` — the strategies render
bytes and never name them. The controller only ever hands one a factory. Every
one of those methods answers a `Result`, and the controller is the only thing
that turns one into a status — 404 for a request body it could not read, 502 for
a response it could not render — a request that carried no body at all being the
same 404 as one that could not be parsed, since only an action that reads a
message ever asks.
Errors go through the same path — `ProblemDetails` is a published table like any other, so a
caller asking for binary gets the problem document in binary. See
[ADR 0001](adr/0001-flatbuffers-over-json.md) for why FlatBuffers at all,
[ADR 0009](adr/0009-abstract-factory-e-strategy-na-negociacao.md) for why the
hand-written half is shaped this way, and
[`documentation.md`](documentation.md) for why the generated half is excluded
from the docs.

## Where to go next

- [`database.md`](database.md) — schema, migrations, transactions, the MEMORY tables
- [`testing.md`](testing.md) — unit and integration strategy
- [`infrastructure.md`](infrastructure.md) — Docker, flatc, scripts, CI
- [`guides/new-feature.md`](guides/new-feature.md) — building one, step by step
- [`adr/`](adr/) — why things are the way they are

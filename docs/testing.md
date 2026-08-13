# Testing

Two suites, split by what they can prove.

| | Unit | Integration |
|---|---|---|
| Tool | Pest (PHP) | `go test` + testcontainers |
| Lives in | `tests/Unit` | `tests/integration` |
| Covers | Domain rules, use-case control flow | the API as a client sees it |
| Talks to | doubles and mocks | real HTTP, real MariaDB |
| Run with | `dagger call pest` | `dagger call integration-test` |
| Costs | ~1 s | ~20 s per leased environment |

The dividing line: **if a behaviour can be observed through a request and a
response, it belongs to integration; if it is a rule or a branch, it belongs to
unit.** Testing a domain rule over HTTP is slow and indirect; testing a status
code with mocks proves only that the mocks agree with each other.

## Unit tests (Pest)

`tests/Unit` mirrors `src/`: `Domain/`, `App/`, `API/`.

### Domain — the rules themselves

Table modules are where validation lives, so they are tested directly and
without doubles. `ProductTMTest`, `ContainerTMTest`, `UserTMTest`,
`ManifestTMTest`, `RoleTMTest`, `AuthTMTest`, `PermissionTMTest`.

What to assert: that a valid input builds the model, that each invalid input
fails, and that the failure carries the right status.

```php
$result = $tm->create('', 1.0, RiskClass::Class3FlammableLiquids);
expect($result->isSuccess())->toBeFalse();
expect(Leaf::getError($result->getErrorId())->code)->toBe(422);
```

### Application — the transactional spine

Use cases are tested for control flow, not for business rules (those are the
table module's). Three things, and they are the same three for every write use
case:

1. **Commit on the happy path** — and `shouldNotReceive('rollback')`.
2. **Rollback on *any* failure** — every repository or table-module failure
   must roll back, and must not also commit.
3. **The 403 guard** — a context without the permission is refused before any
   work happens.

`CreateProductUseCaseTest` is the model to copy; `AuthorizationTest` covers the
guard across use cases.

```php
$unitOfWork = Mockery::mock(IUnitOfWork::class);
$unitOfWork->shouldReceive('begin')->once()->andReturn(Result::void());
$unitOfWork->shouldReceive('rollback')->once();
$unitOfWork->shouldNotReceive('commit');
```

### Doubles

Mockery for collaborators whose calls are the assertion (`IUnitOfWork`,
repositories). Hand-written fakes in `tests/Doubles` when the collaborator needs
real behaviour — `InMemoryPermissionRepository` exists because a use case
registers its permission in its own constructor, so a mock would have to be
taught the whole handshake.

### `Leaf::flushProcessErrors()`

`Leaf` is a process-wide registry, so error ids accumulate across tests. Call
this in `beforeEach` or one test's ids leak into another's assertions.

## Integration tests (Go)

Full detail in [`tests/integration/README.md`](../tests/integration/README.md).
The essentials:

- One tmpfs MariaDB and a pool of `{API container + database}` environments;
  `pool.Lease(t)` takes one and returns it clean.
- A reset is a schema drop, a re-migrate **and** an API restart — the restart is
  not optional, because dropping the schema drops the MEMORY registries the
  application fills once at `WorkerStart`.
- Tests are **stories**, not one test per endpoint: sub-tests run in order and
  share state, because the rules worth testing are transitions. A container
  cannot be sealed before it is loaded, cannot be loaded once sealed, cannot be
  dispatched twice. See
  [ADR 0005](adr/0005-integration-stories-over-per-endpoint-tests.md).
- Payloads come from `internal/factories`, one file per feature.

Three stories today: `session` (bootstrap, login, refresh, logout, password),
`administration` (roles, users, permissions), `yard` (products, containers,
manifests, metrics).

## Static analysis

PHPStan level 9 over `src`, with a baseline holding findings from
flatc-generated files **and nothing else** — those are rewritten on every
`dagger call generate-fbs-php`, so a finding there cannot be fixed in place; the fix
belongs in `scripts/patch-flatbuffers.php`. `src/API/Fbs/` holds nothing but
generated code, so that directory and the baseline are the same set; everything
hand-written, the DTOs and factories under `src/API/Negociation/` included, is
fully analysed. See
[ADR 0007](adr/0007-phpstan-baseline-limited-to-generated-code.md).

```bash
dagger call phpstan
scripts/generate-phpstan-baseline.php   # after changing schemas
```

## Running everything

```bash
dagger call phpstan
dagger call pest
dagger call integration-test
```

CI runs the three as separate jobs — see [`infrastructure.md`](infrastructure.md).

## Adding tests to a new feature

1. A table-module test for each rule you added.
2. A use-case test for commit, rollback and the 403.
3. Factories in `tests/integration/internal/factories/<feature>.go`.
4. Steps appended to the story that owns the resource — a new story only for a
   genuinely separate narrative, since each costs another environment lease.

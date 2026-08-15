# `tests/`

Two suites. Strategy and the reasoning behind the split are in
[`docs/testing.md`](../docs/testing.md).

```
tests/
├── Pest.php            Pest bootstrap
├── TestCase.php        base case
├── Doubles/            hand-written fakes
├── Unit/               Pest — table modules and use cases, nothing else
└── integration/        Go — the API over real HTTP
```

| | `Unit/` | `integration/` |
|---|---|---|
| Tool | Pest | `go test` + testcontainers |
| Covers | rules and control flow | the API as a client sees it |
| Talks to | doubles and mocks | real HTTP, real MariaDB |
| Run | `dagger call pest` | `dagger call integration-test` |
| Costs | ~1 s | ~20 s per leased environment |

**The dividing line:** if a behaviour is observable through a request and a
response, it is an integration test. If it is a rule or a branch, it is a unit
test. Testing a domain rule over HTTP is slow and indirect; testing a status
code with mocks proves only that the mocks agree with each other.

## `Unit/`

Mirrors `src/`.

### What may be committed here

**Only two kinds of unit test go into git: a table module test and a use case
test.** Nothing else. Not a repository, not an adapter, not a serializer, not a
router, not a helper — however useful the test was while the code was being
written.

The reason is what a unit test is *for* here. A table module holds the rules and
a use case holds the transactional spine, and both are things that can be wrong
in a way no other test would catch: a rule with no test is untested everywhere,
because nothing else validates, and a rollback that was dropped from a failure
path looks exactly like one that was never needed. Everything below them is
reached through a port, and whether it works is observable through a request and
a response — which is the integration suite's job, and it proves more there,
against the real MariaDB and the real wire format, than a mock ever proves in
isolation.

A test outside those two shapes is not forbidden while you work. Write it, let
it find what it finds, and delete it before staging. What it taught belongs in
the code or in a comment, not in a file that then has to be maintained against
an implementation detail that was free to change.

Two things in this tree predate the rule and do not follow it: `Unit/API/`
(three files) and `Unit/Domain/IdGeneratorTest.php`. They are debt, not
precedent — do not cite them when adding a third.

### `Unit/Domain/` — the rules

Table modules are where validation lives, so they are tested directly, with no
doubles. One case per rule, plus the happy path, asserting both the failure and
its status code.

```php
$result = $tm->create('', 1.0, RiskClass::Class3FlammableLiquids);
expect($result->isSuccess())->toBeFalse();
expect(Leaf::getError($result->getErrorId())->code)->toBe(422);
```

Mirror: `Unit/Domain/ProductTMTest.php`.

### `Unit/App/` — the transactional spine

Use cases are tested for control flow, not business rules — those belong to the
table module. The same three things for every write use case:

1. **Commit on the happy path**, with `shouldNotReceive('rollback')`.
2. **Rollback on every failure**, and never also commit.
3. **The 403 guard** — a context without the permission is refused before any
   work happens.

Mirror: `Unit/App/CreateProductUseCaseTest.php`. `Unit/App/AuthorizationTest.php`
covers the guard across use cases.

### `Doubles/`

Mockery when the calls themselves are the assertion (`IUnitOfWork`,
repositories). A hand-written fake when the collaborator needs real behaviour —
`InMemoryPermissionRepository` exists because a use case registers its permission
in its own constructor, so a mock would have to be taught the whole handshake.

### `Leaf::flushProcessErrors()`

`Leaf` is a process-wide registry and error ids accumulate across tests. Call it
in `beforeEach` or one test's ids leak into another's assertions.

## `integration/`

Go, driving the real API over HTTP with real FlatBuffers payloads against a real
MariaDB. Tests are **stories** — one narrative per domain over a single leased
environment, with sub-tests that run in order and share state, because the rules
worth testing here are transitions.

Full detail, including the factory layout and the comment convention:
[`integration/README.md`](integration/README.md).

## Running

```bash
dagger call pest                                  # unit
dagger call pest -- --filter=ProductTM            # one file
dagger call integration-test                    # integration
dagger call integration-test -run TestYardStory # one story
```

CI runs them as separate jobs — see [`.github/README.md`](../.github/README.md).

## Adding tests for a feature

1. A table-module test per rule you added.
2. A use-case test for commit, rollback and the 403.
3. Factories in `integration/internal/factories/<feature>.go`.
4. Steps appended to the story that owns the resource.

Step by step, with the rest of the feature:
[`docs/guides/new-feature.md`](../docs/guides/new-feature.md).

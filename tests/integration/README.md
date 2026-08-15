# Integration suite

End-to-end tests written in Go, driving the real API over HTTP with real
FlatBuffers payloads against a real MariaDB. Nothing here touches PHP internals:
if a behaviour cannot be observed through a request and a response, it belongs
in the Pest unit suite instead (`tests/Unit`, see [`../README.md`](../README.md)).

Run it:

```bash
dagger call integration-test                 # the whole suite
dagger call integration-test -run TestYard   # one story
INTEGRATION_POOL_SIZE=2 dagger call integration-test   # fewer parallel environments
```

Requires Docker and Go 1.25. `flatc` is **not** required — the bindings under
`internal/fbs` are committed.

## Layout

```
tests/integration/
├── main_test.go              TestMain, the shared pool, and the assertion helpers
├── session_story_test.go     bootstrap, login, refresh, logout, password change
├── administration_story_test.go   roles, users, permissions
├── yard_story_test.go        products, containers, manifests, metrics
└── internal/
    ├── harness/              testcontainers: the pool, MariaDB, migrations, credentials
    ├── client/               HTTP transport — cookie jar, verbs, session helpers
    ├── factories/            request payload builders, one file per feature
    └── fbs/                  GENERATED — do not edit
```

### `internal/fbs` is generated

`dagger call generate-fbs-go` regenerates it from the canonical schemas in
the `swagger/` submodule; the output is committed so the test runtime never needs
`flatc`. CI regenerates and runs `git diff --exit-code` against it, so a schema
change that is not accompanied by regenerated bindings fails the build.

### `internal/harness`

Builds the API image once, starts one tmpfs MariaDB, and provisions a pool of
`{API container + database}` environments — `INTEGRATION_POOL_SIZE`, defaulting
to `GOMAXPROCS`. A test takes one with `pool.Lease(t)` and gets it back clean;
it is returned to the pool automatically at the end of the test.

`Lease` costs roughly twenty seconds, because a reset is a schema drop, a
re-migrate **and** an API restart. The restart is not optional, and what makes it
so inverted with [ADR 0011](../../docs/adr/0011-cache-em-processo-openswoole.md):
the registries and the read cache used to be dropped *by* the schema drop, and
now cannot be reached by it at all. They live in the API process, so only a
restart clears them — and without that, every story after the first would be
served the previous one's cached pages. That price is what shapes the whole suite
— see below.

### `internal/client`

The HTTP layer: `Get`/`Post`/`Put`/`Delete`, a cookie jar per client, and
`Cookie`/`SetCookie` for the tests that need to tamper with a session. `Setup`
and `LoginAs` send a request and assert the happy path; when a test wants to
drive the response itself, it builds the body with a factory and posts it.

A second `client.New(env.BaseURL)` is how a test gets an *anonymous* or *other*
caller against the same environment — that is the whole mechanism for testing
"someone else's token does not work here".

### `internal/factories`

One file per feature: `product.go`, `container.go`, `manifest.go`, `role.go`,
`user.go`, `account.go`, `auth.go`. A factory for a route under `/products` goes
in `product.go` — including the deliberately invalid payloads for that feature,
which sit next to the valid ones so that adding a rule and adding its
counter-example land in the same file.

Factories returning a struct (`Product`, `Container`, `Role`, `User`) carry both
the encoded `Bytes` and the values that went into them, so a test can create a
resource and assert the server echoed those values back. The rest return a bare
`[]byte`.

## Stories, not tests per endpoint

Each `*_story_test.go` file is one narrative over a single leased environment,
and its sub-tests run **in order, not in parallel** — each depends on the state
the previous one left.

That is a deliberate trade against the twenty-second lease. Spending it per
assertion would buy isolation nobody needs here, and it would lose the thing
these tests are actually good at: the *order*. Session bugs live in sequences —
a token that still works after logout, a rotation that outlives its predecessor —
and only a story can catch them.

Stories run in parallel with **each other** (`t.Parallel()` at the top), each on
its own environment.

**Adding a step to an existing story** is the default: a new endpoint on an
existing resource is one more `t.Run` in the story that already owns that
resource. **Opening a new story** is for a genuinely separate narrative with its
own bootstrap — expect it to cost another environment's worth of wall time.

## Comment convention

The inconsistency this convention replaced was five-line explanations buried
inside test bodies, where they are least likely to be read or maintained.

1. **The why of the story goes in the doc comment of `TestXxxStory`** — what it
   covers, and why these steps belong together.
2. **The what of a step goes in its `t.Run` name.** Write it as a sentence about
   the system: `"refresh rotates, and the token it consumed never works again"`.
3. **The why of an assertion goes in the assertion's own message**, not in a
   comment above it:

   ```go
   assert.Equal(t, http.StatusUnauthorized, unknownEmail.Status,
       "an unknown e-mail must be indistinguishable from a wrong password")
   ```

   This puts the reason in the failure output, where someone reading a red build
   will actually see it.
4. **Inline comments only for what none of the above can carry** — a mechanism
   the reader could not infer, or a setup line whose purpose is not visible.
   Keep them to a line or two.

Same rule in the `internal/` packages, in Go's own idiom: every exported symbol
has a doc comment starting with its name, and the package doc lives in `doc.go`.

## Adding a feature to the suite

1. Regenerate the bindings if the schema changed:
   `dagger call generate-fbs-go`
2. Add the payload builders to `internal/factories/<feature>.go`, valid and
   invalid together.
3. Add `t.Run` steps to the story that owns the resource.
4. Run it: `dagger call integration-test -run TestYardStory`

The full recipe, from schema through to PHP, is in
[`../../docs/guides/new-feature.md`](../../docs/guides/new-feature.md).

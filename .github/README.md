# `.github/`

Continuous integration. One workflow, [`workflows/ci.yml`](workflows/ci.yml),
on every push and pull request.

## Jobs

Three, independent and parallel — a PHPStan failure does not hide a test
failure.

| Job | Runs | Typical |
|---|---|---|
| `static` | `composer phpstan` — level 9 over `src/` | ~1 min |
| `php-unit` | `vendor/bin/pest` | ~1 min |
| `go-integration` | `scripts/integration-test.sh` | 10–25 min |

### `go-integration`

The slow one, by a wide margin: it builds the API image and restarts a container
per test. Two things about it are deliberate.

**`timeout-minutes: 30`.** Without a ceiling, a hung container burns the default
six hours before GitHub gives up. Thirty minutes is comfortably above the real
runtime and fails fast when something wedges.

**The staleness gate.** Before running anything it installs `flatc`, regenerates
the Go bindings, and fails on a diff:

```yaml
- name: Verify generated Go bindings are up to date
  run: |
    scripts/generate-flatbuffers-go.sh
    git diff --exit-code tests/integration/internal/fbs \
      || (echo "::error::Go FlatBuffers bindings are stale — run scripts/generate-flatbuffers-go.sh" && exit 1)
```

The bindings are committed so the test runtime never needs `flatc`. This is what
stops that convenience from letting the contract silently drift between the API
and the tests. If it fires, run the script locally and commit the result.

`flatc` is pinned to `25.12.19`. Bump it in lockstep with the `google/flatbuffers`
constraint in `composer.json`, or the two sides generate from different
compilers.

## Submodules

Every job checks out with:

```yaml
- uses: actions/checkout@v4
  with:
    submodules: recursive
```

The FlatBuffers schemas live in the `swagger/` submodule. Without it there is
nothing to generate from and all three jobs fail in confusing ways.

## PHP setup

PHP 8.4 with `ds`, `pdo_mysql`, `mbstring`, `iconv` and `openswoole`;
`coverage: none`, since nothing here collects it.

## Adding a job

Match the existing shape — checkout with submodules, set up the toolchain,
install dependencies, then one command. Keep jobs independent: a new one should
not need another to have passed first. If it can take an unbounded amount of
time, give it a `timeout-minutes`.

Anything a job runs should be runnable locally by the same command. If CI needs
a step a developer cannot reproduce, put it in `scripts/` first.

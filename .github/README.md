# `.github/`

Two workflows: [`workflows/ci.yml`](workflows/ci.yml) checks what lands on main,
and [`workflows/release.yml`](workflows/release.yml) builds the production
artifact.

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

## Release

`workflows/release.yml`, one job, in an `alpine:3.22` container so the build
matches the musl of the target. It runs `scripts/build-dist.sh` and nothing else
— anything CI does here, a developer can do with the same command.

**Two triggers, one job.** A tag `v*` builds *and* publishes; a push to `main`
builds and stops. The second trigger is the point: without it the artifact would
only ever be assembled on release day, which is the worst day to find out that
assembling it is broken.

**`permissions: contents: write`.** `softprops/action-gh-release@v2` authenticates
with the automatic `GITHUB_TOKEN`, so **no repository secret is involved** — but
that token is read-only by default and the publish step 403s without this line.
A secret only enters the picture if the release is ever published to a different
repository than the one running the workflow.

**No `submodules: recursive`**, unlike all three CI jobs. This workflow generates
nothing from the schemas; `src/API/Fbs/` is committed. It does need
`fetch-depth: 0`, so `git describe` can name an untagged build.

Three things about the Alpine image are load-bearing and each reads as an
unrelated fault when missing: its `composer` package is wired to php83 (hence the
official `composer.phar` under php84), `php84` ships no `openssl` extension
(hence `php84-openssl`, without which every HTTPS download from inside PHP dies
with *"Unable to find the socket transport ssl"*), and `bash` is not installed at
all (the scripts here are bash, and busybox `sh` has no `set -o pipefail`).

To cut a release: bump `version` in `composer.json` and `VERSION` in
`ServerController`, then tag. There is no secret to configure first.

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

# `.github/`

Two workflows: [`workflows/ci.yml`](workflows/ci.yml) checks what lands on main,
and [`workflows/release.yml`](workflows/release.yml) builds the production
artifact.

## Jobs

Three, independent and parallel — a PHPStan failure does not hide a test
failure.

| Job | Runs | Typical |
|---|---|---|
| `checks` | `dagger call ci` — PHPStan nível 9 e depois Pest | ~2 min |
| `go-integration` | `dagger call check-fbs-go`, depois `go test` na suíte | 10–25 min |

Os jobs `static` e `php-unit` eram dois e viraram um: o cache do Dagger não segue
runner, e separados cada um reconstruiria do zero o ambiente PHP. Juntos, o
segundo passo reaproveita o primeiro.

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
  working-directory: dagger
  run: dagger call check-fbs-go
```

The bindings are committed so the test runtime never needs `flatc`. This is what
stops that convenience from letting the contract silently drift between the API
and the tests. If it fires, run

```bash
cd dagger && dagger call generate-fbs-go \
  export --path ../tests/integration/internal/fbs
```

locally and commit the result. `check-fbs-go` regenerates and compares *inside*
the container, so the check itself never dirties the working tree — the old
`generate && git diff --exit-code` did.

`flatc` is pinned to `25.12.19`. Bump it in lockstep with the `google/flatbuffers`
constraint in `composer.json`, or the two sides generate from different
compilers.

## Release

`workflows/release.yml`, one job on a plain runner. The `alpine:3.22` that the
build needs — musl, to match the target the tarball is deployed to — is now
`dagger/modules/toolchain`, not a `container:` key in the workflow. The job runs
`dagger call dist` and nothing else, which is exactly what a developer runs.

**The version decides, not the trigger.** Every push to `main` builds — the
build has to be exercised on ordinary days, because release day is the worst one
to find out that assembling it is broken. Whether that build is *published* is
decided by `version` in `composer.json`: a version with no `v<version>` tag yet
cuts the release and creates the tag on that commit; a version already released
stops after proving the build still works. Pushing a tag by hand still publishes
that tag.

The test is **"no such tag exists"**, not "`composer.json` changed in this push".
On the ordinary path the two agree, and the first keeps being right across a
re-run, a batch of commits landing at once, or a rewritten history — and it is
what guarantees an existing release is never replaced. `concurrency` covers the
remaining hole, two pushes landing together and both finding the tag missing.

**`permissions: contents: write`.** `softprops/action-gh-release@v2` authenticates
with the automatic `GITHUB_TOKEN`, so **no repository secret is involved** — but
that token is read-only by default and the publish step 403s without this line.
A secret only enters the picture if the release is ever published to a different
repository than the one running the workflow.

**No `submodules: recursive`**, unlike all three CI jobs. This workflow generates
nothing from the schemas; `src/API/Fbs/` is committed. The shallow default
checkout is also enough: nothing reads history, and whether a tag exists is
asked of the remote with `git ls-remote` rather than of the local clone, so the
answer is what is actually published.

Three things about the Alpine image are load-bearing and each reads as an
unrelated fault when missing: its `composer` package is wired to php83 (hence the
official `composer.phar` under php84), `php84` ships no `openssl` extension
(hence `php84-openssl`, without which every HTTPS download from inside PHP dies
with *"Unable to find the socket transport ssl"*), and `bash` is not installed at
all (the scripts here are bash, and busybox `sh` has no `set -o pipefail`).

To cut a release: bump `version` in `composer.json` and `VERSION` in
`ServerController`, and push to `main`. The tag and the release are made for
you; there is no secret to configure first.

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
a step a developer cannot reproduce, put it in a `dagger/` function first.

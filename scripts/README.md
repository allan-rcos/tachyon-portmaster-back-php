# `scripts/`

Development and build scripts. All are run from the repository root and resolve
paths from their own location, so they work from anywhere.

| Script | Run it when | Needs |
|---|---|---|
| [`generate-flatbuffers.php`](generate-flatbuffers.php) | a `.fbs` schema changed | `flatc` |
| [`patch-flatbuffers.php`](patch-flatbuffers.php) | automatically, right after the above | — |
| [`generate-flatbuffers-go.sh`](generate-flatbuffers-go.sh) | a `.fbs` schema changed | `flatc`, `perl`, `gofmt` |
| [`generate-phpstan-baseline.php`](generate-phpstan-baseline.php) | generated code changed and PHPStan now complains about it | — |
| [`integration-test.sh`](integration-test.sh) | running the Go suite | Docker, Go 1.25 |
| [`generate-docs.sh`](generate-docs.sh) | rendering the API documentation | `phpdoc` **or** Docker |

## FlatBuffers

The schemas in `swagger/flatbuffers/schemas/*.fbs` (a submodule) feed both the
PHP API and the Go test suite. They are regenerated separately:

```bash
composer flatbuffers                  # PHP  → src/API/Fbs/
scripts/generate-flatbuffers-go.sh    # Go   → tests/integration/internal/fbs/
```

**`generate-flatbuffers.php`** runs `flatc`. It exists instead of a shell
one-liner because the previous `flatc --php -o $(pwd)/src/ .../*.fbs` relied on
POSIX `$(pwd)` and shell glob expansion, so it did not run on Windows. Path
resolution and glob expansion happen in PHP. Override the binary with `FLATC`.

**`patch-flatbuffers.php`** normalises what flatc emits, and the `composer
flatbuffers` script always runs it immediately after. Generated files are never
edited by hand — every run would overwrite the edit — so this is the only
sanctioned way to change generated output. Its transforms are deterministic and
idempotent, and only touch files carrying the flatc header:

1. Builder class casing — upstream declares `FlatbufferBuilder`, flatc writes
   `FlatBufferBuilder`, which fails PSR-4 autoloading on case-sensitive
   filesystems.
2. `create<Table>()` return docblocks — flatc says `@return <Table>` on a method
   returning an `int` offset.
3. Absent child tables — flatc emits `: 0` where an object is expected; rewritten
   to `: null`.

**`generate-flatbuffers-go.sh`** collapses every schema namespace into one flat
`fbs` package (`--go-namespace fbs`), then strips the cross-file imports and
prefixes flatc leaves dangling when a namespace is overridden. Output is
committed, so the test runtime never needs `flatc` — and CI runs this script and
fails on `git diff --exit-code` if the committed bindings are stale.

## PHPStan

```bash
composer phpstan                        # level 9 over src/
scripts/generate-phpstan-baseline.php   # after a schema change
```

The baseline holds findings from flatc-generated files and nothing else. A
finding in a hand-written file is a finding to fix, not to baseline; a finding in
a generated file is fixed by adding a transform to `patch-flatbuffers.php`. See
[ADR 0007](../docs/adr/0007-phpstan-baseline-limited-to-generated-code.md).

`composer phpstan` passes `--memory-limit=2G`; running `vendor/bin/phpstan`
directly will hit PHP's default 128M and die mid-analysis.

## Integration tests

```bash
scripts/integration-test.sh                        # everything
scripts/integration-test.sh -run TestYardStory     # one story
INTEGRATION_POOL_SIZE=2 scripts/integration-test.sh
```

Any extra argument is passed through to `go test`. testcontainers-go builds the
API image and stands up its own MariaDB — `docker-compose.yml` is not involved.
Details in [`tests/integration/README.md`](../tests/integration/README.md).

## Documentation

```bash
composer phpdoc          # → build/docs/latest
composer phpdoc -- --force   # ignore the incremental cache
```

`generate-docs.sh` prefers a `phpdoc` on `PATH` and falls back to the
`phpdoc/phpdoc` Docker image, running it as the calling user so `build/` does
not end up owned by root. Override with `PHPDOC` or `PHPDOC_IMAGE`.

phpDocumentor is not a composer dependency on purpose — its Twig and Symfony
constraints conflict with what the application and PHPStan pin, which is why
upstream ships a PHAR and an image.

## Adding a script

Match the existing shape: a header comment saying what it does and why it exists,
an `Env:` block if it reads any, a `Usage:` line, `set -euo pipefail` for bash,
and paths resolved from `${BASH_SOURCE[0]}` or `__DIR__` rather than the working
directory. Then add a row to the table above.

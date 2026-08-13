# Portmaster documentation

A container-yard API on PHP 8.4 / OpenSwoole, speaking FlatBuffers over HTTP to
MariaDB.

## Start here

| | |
|---|---|
| [architecture.md](architecture.md) | The five layers, the request path, and the conventions that hold them together |
| [guides/new-feature.md](guides/new-feature.md) | Building a feature end to end, with a file to mirror at every step |
| [database.md](database.md) | Schema, migrations, seeds, transactions, the MEMORY tables |
| [testing.md](testing.md) | What belongs in a unit test and what belongs in an integration story |
| [infrastructure.md](infrastructure.md) | Docker, configuration, flatc, scripts, CI |
| [documentation.md](documentation.md) | The PHPDoc format and how the docs are rendered |
| [adr/](adr/) | Why things are the way they are |

## Directory READMEs

These cover the operational detail for one directory; the pages above cover the
reasoning across directories.

- [`src/`](../src/README.md) — the layers and where a class goes
- [`db/`](../db/README.md) — migrations and seeds
- [`dagger/`](../dagger/README.md) — the build and check functions, and what each replaced
- [`tests/`](../tests/README.md) — both suites
- [`tests/integration/`](../tests/integration/README.md) — the Go suite in detail
- [`.github/`](../.github/README.md) — the CI jobs

## Quick start

```bash
docker compose up -d

curl -X POST localhost:8000/v1/setup -H 'Content-Type: application/json' \
     -d '{"name":"Admin","email":"admin@portmaster.local","password":"Portmaster1"}'

curl localhost:8000/v1/info
```

```bash
composer phpstan             # static analysis, level 9
composer pest                # unit tests
dagger call integration-test  # integration suite (needs Docker)
composer phpdoc              # render API documentation to docs/phpdocumentor
```

## Skills

`.claude/skills/` holds four skills carrying this same knowledge, indexed for an
agent to act on: `phpdoc`, `dev`, `test`, `infra`. They reference these pages
rather than duplicating them, so this remains the place to make a correction.

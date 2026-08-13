# Infrastructure

## Runtime

PHP 8.4 CLI under OpenSwoole, no web server in front. `src/API/main.php` starts
the server and registers three handlers: `start`, `WorkerStart` (where the whole
object graph is built, after the fork) and `request`.

Extensions: `openswoole`, `ds`, `pdo_mysql`, `mbstring`, `iconv`.

`ext-ds` is pinned to 2.x, where `Ds\Seq` replaces `Vector`, `Deque`, `Stack`
and `Queue` and `Ds\Vector` no longer exists. The Infra layer is written against
`Seq` accordingly.

## Configuration

Entirely environment variables — the same image serves the dev stack and the
test pool, each pointed at its own database.

| Variable | Purpose |
|---|---|
| `APP_HOST`, `APP_PORT` | listen address |
| `APP_WORKER_NUM` | worker processes (4 in dev) |
| `APP_DB_HOST`, `APP_DB_PORT`, `APP_DB_NAME`, `APP_DB_USER`, `APP_DB_PASSWORD` | database |
| `APP_DB_SSL_MODE` | `disabled` (default), `required` or `verify_ca` |
| `APP_DB_SSL_CA`, `APP_DB_SSL_VERIFY_CN` | CA bundle and name check — only read under `verify_ca` |
| `APP_JWT_SECRET` | HS256 signing key — **32 bytes minimum**, shorter is refused |
| `APP_JWT_COOKIE_SECURE` | `false` for local HTTP |

`APP_DB_SSL_MODE` defaults to `disabled`, which is the right answer for a
database on `127.0.0.1` or a private subnet and the wrong one for every managed
provider, since they refuse plaintext outright. `required` encrypts without
validating the certificate, so it defeats a passive listener and not an active
one; `verify_ca` additionally requires `APP_DB_SSL_CA`.

Verified against MariaDB 11 started with `--require-secure-transport=ON` and a
self-signed certificate, driving the real pool under the coroutine hook:

| Mode | Result |
|---|---|
| `disabled` | rejected — *"Connections using insecure transport are prohibited"* |
| `required` | connects, `Ssl_cipher=TLS_AES_256_GCM_SHA384` |
| `verify_ca`, `APP_DB_SSL_VERIFY_CN=false` | connects, same cipher |
| `verify_ca`, `APP_DB_SSL_VERIFY_CN=true` | rejected — the certificate does not chain to the CA |

The last two rows are what prove the attributes reach the driver at all, which
was the open question: OpenSwoole's coroutine `PDOConfig` honours
`PDO::MYSQL_ATTR_SSL_*`, and a wrong CA fails loudly rather than downgrading.

One trap is recorded in `PDOConfigFactory::sslOptions()` and worth repeating:
`MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false` **alone does not start a
handshake**. It only decides what happens to a certificate once one arrives, so
`required` also sets `MYSQL_ATTR_SSL_CIPHER`. Without it the connection goes out
in plaintext, which against a server that permits plaintext is silent.

The Dockerfile writes `variables_order=EGPCS` into a php.ini fragment, because
the boot config reads `$_ENV` and without that setting Docker's `-e` values
never land there.

Config is assembled by a chain of `Bootstrap/Chain/*` classes into a
`BootConfig`, which `ApiRegister::execute()` receives.

## Docker

`docker-compose.yml` is the **development** stack — the integration tests do not
use it, testcontainers stands up its own.

```
db ──(healthy)──► migrate ──(completed)──► seed ──(completed)──► app
```

- **`db`** — MariaDB 11 with `--event-scheduler=ON`, needed for the hourly
  marker purge `EVENT`. On-disk volume `db_data`. `start_period: 180s` on the
  healthcheck: initialising a fresh volume on a slow disk takes minutes, and
  without it the retry budget is spent before MariaDB finishes, failing the
  stack with "db is unhealthy" on a database that was merely still starting.
- **`migrate`** — golang-migrate against `db/migrations`.
- **`seed`** — pipes `db/seeds/dev.sql` in. Because `app` waits on this one
  completing successfully, the seed must survive a re-run on a populated volume;
  it does (see [`database.md`](database.md)).
- **`app`** — built from the Dockerfile.

```bash
docker compose up -d          # start
docker compose down           # stop, KEEP the data
docker compose down -v        # stop and wipe
docker compose logs -f app
```

Bootstrapping a fresh stack:

```bash
curl -X POST localhost:8000/v1/setup -H 'Content-Type: application/json' \
     -d '{"name":"Admin","email":"admin@portmaster.local","password":"Portmaster1"}'
```

## FlatBuffers

The schemas in `swagger/flatbuffers/schemas/*.fbs` (a git submodule) are the
single source of truth for both the PHP API and the Go test suite.

```bash
dagger call generate-fbs-php                  # PHP: generate + patch
(cd dagger && dagger call generate-fbs-go \
   export --path ../tests/integration/internal/fbs)   # Go bindings for the test suite
```

**PHP** — `scripts/generate-flatbuffers.php` runs `flatc` (resolving paths in
PHP rather than relying on POSIX `$(pwd)` and shell globbing, so it works on
Windows), then `scripts/patch-flatbuffers.php` normalises the output. That patch
step exists because flatc's PHP codegen has defects that leak wrong types into
the app, and hand-editing the generated files would be undone by the next run.
It re-applies a fixed set of idempotent transforms: builder class casing
(`FlatbufferBuilder`, not `FlatBufferBuilder`, which fails PSR-4 on
case-sensitive filesystems), the `create<Table>()` return docblock (`int`, not
the table), and the absent-child-table sentinel (`null`, not `0`).

**Go** — one flat `fbs` package via `--go-namespace fbs`, then two `perl` passes
to strip the cross-file imports and prefixes flatc leaves dangling when the
namespace is overridden. Output is committed, so the test runtime never needs
`flatc`.

Both generated trees are excluded from hand-written documentation and, for PHP,
from PHPStan analysis — see [ADR 0001](adr/0001-flatbuffers-over-json.md).

## Build and check functions

There is no `scripts/` directory: everything below is a Dagger function, run
from `dagger/`. See [`dagger/README.md`](../dagger/README.md) for what each one
replaced and the equivalence checks behind the port.

| Function | Does |
|---|---|
| `dagger call generate-fbs-php` | runs flatc and normalises its PHP output |
| `dagger call generate-fbs-go` | regenerates the Go bindings for the test suite |
| `dagger call check-fbs-go` | fails if the committed bindings are stale |
| `dagger call generate-phpstan-baseline` | rebuilds the generated-code baseline |
| `dagger call dist` | builds the production artifact |
| `dagger call docs` | renders the API reference |
| `dagger call integration-test` | runs the Go suite, daemon included |

## CI

`.github/workflows/ci.yml`, three independent jobs on push and pull request.
All check out with `submodules: recursive` — without the `swagger/` submodule
there are no schemas.

1. **`static`** — PHPStan level 9.
2. **`php-unit`** — Pest.
3. **`go-integration`** — installs `flatc`, regenerates the Go bindings and
   fails on `git diff --exit-code` if the committed ones are stale, then runs
   the suite. `timeout-minutes: 30`, because building the API image and
   restarting a container per test makes this the slow job by a wide margin and
   a hung container should fail rather than burn the default six hours.

## Troubleshooting

**`app` never starts, `seed` exited non-zero.** Read `docker compose logs seed`.
The seed is idempotent, so a duplicate-key failure means a statement was added
without an `ON DUPLICATE KEY UPDATE` clause.

**PHPStan dies with "reached configured PHP memory limit".** The composer script
passes `--memory-limit=2G`; running `vendor/bin/phpstan` directly does not.

**`flatc not found on PATH`.** Install it from the FlatBuffers releases; CI
pins 25.12.19.

**Go bindings are stale in CI.** Run `dagger call generate-fbs-go` (exporting
over `tests/integration/internal/fbs`) and commit the result.

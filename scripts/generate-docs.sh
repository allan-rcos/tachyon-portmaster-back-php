#!/usr/bin/env bash
#
# Renders the PHPDoc blocks in src/ into browsable HTML under build/docs, using
# the configuration in phpdoc.dist.xml.
#
# phpDocumentor is deliberately NOT a composer dependency: it pulls a large
# tree of its own (its Twig, Symfony and phpDocumentor/* constraints) that
# routinely conflicts with what the application and PHPStan already pin. The
# upstream project ships a PHAR and a Docker image for exactly this reason, so
# this script prefers whichever is available:
#
#   1. `phpdoc` already on PATH (PHAR install) — fastest, no container.
#   2. the phpdoc/phpdoc Docker image — nothing to install locally.
#
# Env:
#   PHPDOC            path to a phpdoc binary, overriding the search
#   PHPDOC_IMAGE      Docker image to fall back to (default phpdoc/phpdoc:3)
#
# Usage: scripts/generate-docs.sh [additional phpdoc flags]
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
IMAGE="${PHPDOC_IMAGE:-phpdoc/phpdoc:3}"

# Every implementation class under src/*/Interno/ (and Domain/Models/Internal/)
# is tagged @internal: the contracts are the published API, the classes behind
# them are not, and a breaking change to one is not a breaking change to the
# library. phpDocumentor *filters @internal elements out of the HTML* unless
# --parseprivate is passed — which would silently drop ~116 classes, and all the
# implementation notes written on them, from the rendered docs. They are
# documented on purpose, so the flag is a default here rather than something a
# caller has to remember. Drop it to render the contract-only view.
DEFAULT_FLAGS=(--parseprivate)

cd "$ROOT"

# The cache survives between runs and makes an incremental render much faster,
# but it also keeps stale entries for files that no longer exist. Callers who
# want a clean render pass --force, which phpdoc understands.
mkdir -p build/docs build/docs-cache

if [ -n "${PHPDOC:-}" ]; then
    exec "$PHPDOC" "${DEFAULT_FLAGS[@]}" "$@"
fi

if command -v phpdoc >/dev/null 2>&1; then
    exec phpdoc "${DEFAULT_FLAGS[@]}" "$@"
fi

if ! command -v docker >/dev/null 2>&1; then
    echo "Neither phpdoc nor docker found on PATH." >&2
    echo "Install the PHAR (https://phpdoc.org/phpDocumentor.phar) or Docker, then re-run." >&2
    exit 1
fi

echo "phpdoc not found on PATH — falling back to $IMAGE"

# --user keeps build/ owned by the caller instead of root, which otherwise makes
# the next non-Docker run fail on the cache directory.
exec docker run --rm \
    --user "$(id -u):$(id -g)" \
    --volume "$ROOT:/data" \
    "$IMAGE" "${DEFAULT_FLAGS[@]}" "$@"

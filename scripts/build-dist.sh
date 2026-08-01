#!/usr/bin/env bash
#
# Builds the production artifact of the API.
#
# Output in dist/:
#   portmaster-api-<version>-linux-x86_64.tar.zst          src/ + vendor/, minified
#   portmaster-api-<version>-linux-x86_64.tar.zst.sha256
#   portmaster-migrations-<version>.tar.zst                db/ (migrations + seeds)
#   portmaster-migrations-<version>.tar.zst.sha256
#
# The migrations ship in a separate package on purpose: what applies them is a
# developer's machine talking to the database, not the server running the API.
# Shipping them inside the API tarball would put a schema change on the box that
# is least entitled to make one.
#
# Everything is assembled in dist/.stage, never in the repository itself. That
# matters more than it looks: the naive version of this script runs
# `composer install --no-dev` at the root, which silently deletes PHPStan, Pest
# and Mockery from the working copy of whoever ran it. Here the stage is a
# self-contained mini-project — src/, composer.json and composer.lock are copied
# in first, and Composer is pointed at it with --working-dir — so the developer's
# vendor/ is never touched.
#
# ext-openswoole is skipped with --ignore-platform-req deliberately: it is a
# RUNTIME requirement, and the machine assembling the artifact does not run the
# API. Honouring it would mean compiling the extension just so Composer can tick
# off a line of composer.json.
#
# The reasoning behind the shape of all this is in
# docs/adr/0008-minified-tarball-as-the-release-artifact.md.
#
# Env:
#   COMPOSER          path to a composer binary (default: composer on PATH)
#
# Usage: scripts/build-dist.sh [version]
#
# Without an argument the version is read from `version` in composer.json, which
# is where it is declared — the same field the running API reports. Passing one
# overrides it, which is what building from a tag does.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
COMPOSER="${COMPOSER:-composer}"

command -v "$COMPOSER" >/dev/null 2>&1 || { echo "composer not found on PATH" >&2; exit 1; }
command -v php >/dev/null 2>&1 || { echo "php not found on PATH" >&2; exit 1; }
command -v zstd >/dev/null 2>&1 || { echo "zstd not found on PATH" >&2; exit 1; }
command -v tar >/dev/null 2>&1 || { echo "tar not found on PATH" >&2; exit 1; }

cd "$ROOT"

VERSION="${1:-}"
if [ -z "$VERSION" ]; then
    VERSION="$(php -r 'echo json_decode(file_get_contents("composer.json"), true)["version"] ?? "";')"
    [ -n "$VERSION" ] || { echo "composer.json declares no version" >&2; exit 1; }
fi
VERSION="${VERSION#v}"

PLATFORM="linux-x86_64"
DIST="$ROOT/dist"
STAGE="$DIST/.stage"
API_NAME="portmaster-api-${VERSION}-${PLATFORM}"
MIG_NAME="portmaster-migrations-${VERSION}"
API_STAGE="$STAGE/$API_NAME"

size()  { du -sb "$1" 2>/dev/null | cut -f1; }
human() { awk -v s="$1" 'BEGIN { u="B"; if (s>1048576) { s=s/1048576; u="MB" } else if (s>1024) { s=s/1024; u="KB" }; printf "%.1f %s", s, u }'; }

rm -rf "$DIST"
mkdir -p "$API_STAGE" "$STAGE/$MIG_NAME"

# ---------------------------------------------------------------------------
# 1. Nothing in src/ may depend on a docblock
#
# `php -w` below is php_strip_whitespace: it drops comments and whitespace while
# preserving semantics. That is safe in THIS project because nothing here is
# driven by annotation — no getDocComment, no ReflectionClass in src/ — and the
# only __DIR__ is the autoload require in src/API/main.php, which the strip does
# not move across lines.
#
# The guard re-confirms that on every build. If someone introduces a
# docblock-driven dependency, the build fails here instead of the API booting in
# production with an attribute that quietly vanished.
# ---------------------------------------------------------------------------
echo "checking that nothing depends on docblocks"
if grep -rEl 'getDocComment|ReflectionClass|ReflectionMethod|ReflectionProperty' src/ 2>/dev/null | grep -q .; then
    echo "ERROR: src/ now uses reflection over docblocks; php -w is no longer safe." >&2
    grep -rEn 'getDocComment|ReflectionClass|ReflectionMethod|ReflectionProperty' src/ >&2
    exit 1
fi

# ---------------------------------------------------------------------------
# 2. Stage the sources, then install production dependencies into the stage
#
# composer.lock goes along with composer.json: without it Composer resolves
# afresh and the artifact stops being reproducible.
# ---------------------------------------------------------------------------
echo "staging src/ and manifests"
cp -a src "$API_STAGE/src"
cp composer.json composer.lock "$API_STAGE/"

SRC_BEFORE="$(size src)"

echo "composer install (production, into the stage)"
"$COMPOSER" install \
    --working-dir="$API_STAGE" \
    --no-dev \
    --prefer-dist \
    --no-scripts \
    --no-interaction \
    --no-progress \
    --classmap-authoritative \
    --ignore-platform-req=ext-openswoole

VENDOR_BEFORE="$(size "$API_STAGE/vendor")"

# ---------------------------------------------------------------------------
# 3. Prune vendor/
#
# What goes is what is never loaded at runtime. LICENSE files stay: dropping a
# dependency's licence saves nothing worth having and breaks the redistribution
# terms of half of them.
# ---------------------------------------------------------------------------
echo "pruning vendor/"
find "$API_STAGE/vendor" -type d \( \
        -name tests -o -name Tests -o -name test -o -name Test \
     -o -name docs -o -name doc -o -name examples -o -name example \
     -o -name .github -o -name .git -o -name benchmarks \
    \) -prune -exec rm -rf {} + 2>/dev/null || true

find "$API_STAGE/vendor" -type f \( \
        -name '*.md' -o -name '*.rst' -o -name '*.dist' \
     -o -name 'phpunit.xml*' -o -name '*.neon' -o -name '*.neon.dist' \
     -o -name 'psalm*' -o -name 'Makefile' -o -name '.editorconfig' \
     -o -name '.php_cs*' -o -name '.php-cs-fixer*' -o -name 'phpbench.json' \
     -o -name '*.yml' -o -name '*.yaml' \
    \) -delete 2>/dev/null || true

# ---------------------------------------------------------------------------
# 4. Minify
#
# vendor/bin is left alone: those are executables with a shebang, and none of
# them is loaded by the API at runtime.
# ---------------------------------------------------------------------------
echo "minifying php (php -w)"
STRIPPED=0
while IFS= read -r f; do
    if php -w "$f" > "$f.min" 2>/dev/null && [ -s "$f.min" ]; then
        mv "$f.min" "$f"
        STRIPPED=$((STRIPPED + 1))
    else
        rm -f "$f.min"
    fi
done < <(find "$API_STAGE/src" "$API_STAGE/vendor" \
    -type f -name '*.php' -not -path '*/vendor/bin/*' -print)

SRC_AFTER="$(size "$API_STAGE/src")"
VENDOR_AFTER="$(size "$API_STAGE/vendor")"

# ---------------------------------------------------------------------------
# 5. Migrations, in their own package
# ---------------------------------------------------------------------------
echo "staging migrations"
cp -a db "$STAGE/$MIG_NAME/db"

# ---------------------------------------------------------------------------
# 6. Tarballs
#
# --owner/--group/--numeric-owner keep the archive independent of whoever built
# it, so the same tree produces the same bytes on any machine.
# ---------------------------------------------------------------------------
echo "packaging"
tar --use-compress-program='zstd -19 -T0' \
    --owner=0 --group=0 --numeric-owner \
    -C "$STAGE" -cf "$DIST/${API_NAME}.tar.zst" "$API_NAME"
tar --use-compress-program='zstd -19 -T0' \
    --owner=0 --group=0 --numeric-owner \
    -C "$STAGE" -cf "$DIST/${MIG_NAME}.tar.zst" "$MIG_NAME"

( cd "$DIST" && for f in "${API_NAME}.tar.zst" "${MIG_NAME}.tar.zst"; do
    sha256sum "$f" > "$f.sha256"
done )

rm -rf "$STAGE"

cat <<EOF

  version     ${VERSION}
  files       ${STRIPPED} .php minified

  src/        $(human "$SRC_BEFORE")  ->  $(human "$SRC_AFTER")
  vendor/     $(human "$VENDOR_BEFORE")  ->  $(human "$VENDOR_AFTER")

$(ls -lh "$DIST"/*.tar.zst | awk '{ printf "  %-52s %s\n", $9, $5 }')

EOF

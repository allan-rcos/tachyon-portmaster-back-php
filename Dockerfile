# Portmaster API — PHP 8.4 CLI + OpenSwoole, driven entirely by environment
# variables (APP_*, APP_DB_*, APP_JWT_*) so the same image serves both the dev
# compose stack and the testcontainers-go integration pool (each pointed at its
# own database).
#
# ---------------------------------------------------------------------------
# `ext` — the PHP environment: extensions and Composer, and nothing of the app.
#
# It is a stage of its own so that there is exactly ONE definition, in this
# repository, of which PHP this project runs on. `dagger/modules/toolchain`
# builds *this same target* to run PHPStan and Pest, so the checks execute on
# the same extensions as production.
#
# That is what removes the hand-maintained `openswoole-26.2.0` pin that used to
# live in .github/workflows/ci.yml: setup-php defaulted to 25.2.0 while
# composer.lock demanded >= 26.2.0, so the version had to be repeated — and kept
# in sync — in a third place. There is no third place now.
#
# Nothing from the build context is copied into this stage, so it rebuilds only
# when this block itself changes, and the layer is shared with `base` below.
# ---------------------------------------------------------------------------
FROM php:8.4-cli AS ext

# System libraries the PECL/bundled extensions link against.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libssl-dev \
        libcurl4-openssl-dev \
        libpcre2-dev \
        libonig-dev \
        zlib1g-dev \
        git \
        unzip \
    && rm -rf /var/lib/apt/lists/*

# Bundled extensions.
RUN docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring

# PECL extensions: OpenSwoole (HTTP server), ds (Ds\Seq/Map/Set/etc.) and
# igbinary (how the read cache turns a View into the bytes a row holds).
#
# ext-ds 2.x unified the sequence types: Ds\Seq replaces Vector, Deque, Stack and
# Queue, and Ds\Vector no longer exists. The Infra layer is written against Seq
# accordingly — pinning to 1.5.0 to keep Vector would be freezing the extension
# on a superseded major.
#
# igbinary rather than serialize(): the cached views nest a Ds\Seq of readonly
# items, and igbinary deduplicates the strings repeated across them. Measured on
# a default page of container summaries it is ~82% smaller than serialize() —
# which on an ENGINE=MEMORY table, where the payload column is padded to its
# declared width, is the difference between the page fitting and not. It also
# round-trips Ds\Seq, readonly classes and backed enums, and answers null for a
# payload it cannot read, which is exactly how a stale format is meant to look.
RUN pecl install ds igbinary \
    && docker-php-ext-enable ds igbinary
RUN pecl install openswoole \
    && docker-php-ext-enable openswoole

# Composer.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Two settings the app depends on:
#   * variables_order puts OS environment variables (docker -e / compose env)
#     into $_ENV, which is where the boot config reader looks.
#   * date.timezone makes PHP's clock UTC, which is the rule the whole system
#     holds to: every datetime stored, compared or sent is a UTC instant. The
#     database side of the same rule is the session time zone pinned in
#     PDOConfigFactory and the server's --default-time-zone.
RUN printf 'variables_order=EGPCS\ndate.timezone=UTC\n' > "$(php -r 'echo PHP_CONFIG_FILE_SCAN_DIR;')/zz-portmaster.ini"

# ---------------------------------------------------------------------------
# `base` — the application on top of that environment.
# ---------------------------------------------------------------------------
FROM ext AS base

WORKDIR /app

# Install dependencies first for layer caching, then copy the source.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --no-progress

COPY . .
RUN composer dump-autoload --optimize --no-dev

# Listen on all interfaces inside the container; the port is overridable.
ENV APP_HOST=0.0.0.0 \
    APP_PORT=8000

EXPOSE 8000

ENTRYPOINT ["php", "src/API/main.php"]

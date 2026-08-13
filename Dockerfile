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

# PECL extensions: OpenSwoole (HTTP server) and ds (Ds\Seq/Map/Set/etc.).
#
# ext-ds 2.x unified the sequence types: Ds\Seq replaces Vector, Deque, Stack and
# Queue, and Ds\Vector no longer exists. The Infra layer is written against Seq
# accordingly — pinning to 1.5.0 to keep Vector would be freezing the extension
# on a superseded major.
RUN pecl install ds \
    && docker-php-ext-enable ds
RUN pecl install openswoole \
    && docker-php-ext-enable openswoole

# Composer.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Ensure OS environment variables (docker -e / compose env) land in $_ENV, which
# is where the boot config reader looks; the app is configured entirely this way.
RUN echo 'variables_order=EGPCS' > "$(php -r 'echo PHP_CONFIG_FILE_SCAN_DIR;')/zz-portmaster.ini"

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

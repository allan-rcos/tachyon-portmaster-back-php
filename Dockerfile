# Portmaster API — PHP 8.4 CLI + OpenSwoole, driven entirely by environment
# variables (APP_*, APP_DB_*, APP_JWT_*) so the same image serves both the dev
# compose stack and the testcontainers-go integration pool (each pointed at its
# own database).
FROM php:8.4-cli AS base

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

# syntax=docker/dockerfile:1
# One image for the three PHP roles in docker-compose.yml: the web app, the queue worker and
# the scheduler. Build context is web/; this directory is added as the "docker" context.

# --- Front-end assets -------------------------------------------------------------------
FROM node:24-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json .npmrc ./
RUN npm ci --no-audit --no-fund
COPY resources ./resources
COPY vite.config.ts tsconfig.json ./
RUN npm run build

# --- PHP application --------------------------------------------------------------------
FROM php:8.3-cli-bookworm AS app

RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libpq-dev libzip-dev unzip \
    && docker-php-ext-install intl pcntl pdo_pgsql zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Dependencies first so code changes do not reinstall them.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
COPY --from=assets /app/public/build ./public/build
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
        bootstrap/cache /var/app-secrets \
    && chown -R www-data:www-data storage bootstrap/cache /var/app-secrets

COPY --from=docker app/entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint

USER www-data
EXPOSE 8000
ENTRYPOINT ["app-entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

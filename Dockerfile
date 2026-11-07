FROM node:24-bookworm-slim AS frontend
WORKDIR /build
COPY package*.json ./
RUN npm ci
COPY resources ./resources
COPY scripts/build-browser-assets.mjs ./scripts/
RUN npm run build

FROM php:8.4-fpm-bookworm AS php
RUN apt-get update && apt-get install -y --no-install-recommends \
        libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev libzip-dev libxml2-dev libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring gd zip simplexml xmlwriter pcntl opcache \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY composer.json composer.lock artisan ./
COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY database ./database
COPY lang ./lang
COPY resources ./resources
COPY routes ./routes
COPY public/index.php ./public/index.php
COPY --from=frontend /build/public/build ./public/build
COPY deploy/php.ini /usr/local/etc/php/conf.d/ams.ini
RUN mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/private/assets \
    && composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && chown -R www-data:www-data bootstrap/cache storage
USER www-data

FROM nginxinc/nginx-unprivileged:1.28-alpine AS web
COPY deploy/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=frontend /build/public/build /var/www/html/public/build

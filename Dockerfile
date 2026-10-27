# =========================================================
# Stage 1: build frontend assets (Vite)
# =========================================================
FROM node:20-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json* ./
COPY vite.config.js ./
COPY resources ./resources
RUN npm ci --no-audit --no-fund && npm run build

# =========================================================
# Stage 2: runtime (nginx + php-fpm + supervisor)
# =========================================================
FROM php:8.4-fpm-alpine AS app

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql opcache bcmath intl pcntl

RUN apk add --no-cache nginx supervisor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install PHP dependencies first for better layer caching
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-ansi \
    --no-scripts \
    --optimize-autoloader

# Copy application source
COPY . .
COPY --from=assets /app/public/build ./public/build

# Runtime service configuration (nginx + supervision)
RUN rm -f /etc/nginx/conf.d/default.conf
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Install composer packages missing from --no-scripts step
RUN composer dump-autoload --optimize && \
    mkdir -p storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/testing \
        storage/framework/views \
        storage/logs \
        storage/app/public \
        database \
        bootstrap/cache && \
    touch storage/logs/laravel.log && \
    chown -R www-data:www-data storage bootstrap/cache database

EXPOSE 80

# Fix permissions/links and run migrations at container start
CMD ["sh", "-c", "sh docker/start.sh"]

#!/bin/sh

set -e
cd /var/www/html

echo "==> Preparing storage link and caches..."
php artisan package:discover
php artisan storage:link --force >/dev/null 2>&1 || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Running database migrations..."
php artisan migrate --force

echo "==> Starting services (nginx + php-fpm)..."
exec supervisord -c /etc/supervisor/conf.d/supervisord.conf

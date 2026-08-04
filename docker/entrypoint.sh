#!/bin/sh

set -e

PORT="${PORT:-80}"

sed -ri "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:.*>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/*.conf

echo "Waiting for database..."

sleep 5

php artisan storage:link || true

php artisan migrate --force

php artisan optimize:clear

php artisan config:cache

php artisan route:cache

php artisan view:cache

exec "$@"

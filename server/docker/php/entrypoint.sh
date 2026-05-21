#!/bin/sh
set -eu

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY is required."
    exit 1
fi

mkdir -p \
    /var/www/html/bootstrap/cache \
    /var/www/html/storage/app/public \
    /var/www/html/storage/app/private \
    /var/www/html/storage/framework/cache/data \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/testing \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/logs

if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
fi

if [ ! -L /var/www/html/public/storage ]; then
    rm -rf /var/www/html/public/storage
    ln -s /var/www/html/storage/app/public /var/www/html/public/storage
fi

if [ "${1:-}" = "php-fpm" ]; then
    exec "$@"
fi

if [ "$(id -u)" = "0" ]; then
    exec gosu www-data "$@"
fi

exec "$@"

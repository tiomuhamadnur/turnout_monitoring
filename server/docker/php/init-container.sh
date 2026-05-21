#!/bin/sh
set -eu

wait_for_tcp() {
    host="$1"
    port="$2"
    name="$3"
    attempts="${4:-60}"

    while [ "$attempts" -gt 0 ]; do
        if nc -z "$host" "$port" >/dev/null 2>&1; then
            return 0
        fi

        attempts=$((attempts - 1))
        sleep 2
    done

    echo "Timed out waiting for ${name} (${host}:${port})."
    return 1
}

wait_for_tcp "${DB_HOST:-mysql}" "${DB_PORT:-3306}" "mysql"
wait_for_tcp "${REDIS_HOST:-redis}" "${REDIS_PORT:-6379}" "redis"
wait_for_tcp "${MQTT_HOST:-mosquitto}" "${MQTT_PORT:-1883}" "mosquitto"

php artisan migrate --force
php artisan storage:link || true

if [ "${APP_RUN_SEEDERS:-false}" = "true" ]; then
    php artisan db:seed --class="${APP_SEED_CLASS:-DatabaseSeeder}" --force
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

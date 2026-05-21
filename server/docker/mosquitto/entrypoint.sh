#!/bin/sh
set -eu

if [ -z "${MQTT_USERNAME:-}" ] || [ -z "${MQTT_PASSWORD:-}" ]; then
    echo "MQTT_USERNAME and MQTT_PASSWORD are required."
    exit 1
fi

mosquitto_passwd -b -c /mosquitto/config/passwd "${MQTT_USERNAME}" "${MQTT_PASSWORD}"
chmod 600 /mosquitto/config/passwd

exec /usr/sbin/mosquitto -c /mosquitto/config/mosquitto.conf

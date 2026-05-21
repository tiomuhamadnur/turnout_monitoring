<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MQTT broker connection (Eclipse Mosquitto)
    |--------------------------------------------------------------------------
    | Used by the artisan mqtt:subscribe daemon that bridges broker messages
    | into the TelemetryIngestService. The HTTP ingestion path at
    | /api/internal/telemetry/* is independent and stays available for direct
    | callers or for stations that prefer to push over HTTP.
    */

    'host'      => env('MQTT_HOST', '127.0.0.1'),
    'port'      => (int) env('MQTT_PORT', 1883),
    'username'  => env('MQTT_USERNAME'),
    'password'  => env('MQTT_PASSWORD'),
    'client_id' => env('MQTT_CLIENT_ID', 'server-core'),

    // Root prefix matches BLUEPRINT.md MQTT topic structure.
    'topic_prefix' => env('MQTT_TOPIC_PREFIX', 'turnout'),

    // Subscriber QoS for state/heartbeat/health messages.
    // QoS 1 (at-least-once) is the right tradeoff: telemetry must not be lost
    // but duplicate delivery is harmless — TelemetryIngestService is idempotent
    // by (turnout_id, source_timestamp) for state and by node_id for heartbeats.
    'qos' => 1,

    // Reconnect/keepalive tuning for the long-running daemon.
    'keepalive_interval' => 30,
    'reconnect_seconds'  => 5,
];

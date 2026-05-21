<?php

namespace App\Console\Commands;

use App\Services\TelemetryIngestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Contracts\MqttClient;
use PhpMqtt\Client\Exceptions\MqttClientException;
use PhpMqtt\Client\MqttClient as PhpMqttClient;
use Throwable;

class MqttSubscribeCommand extends Command
{
    protected $signature = 'mqtt:subscribe
        {--host= : Override MQTT broker host}
        {--port= : Override MQTT broker port}
        {--once : Process one batch of messages then exit (useful for tests)}';

    protected $description = 'Subscribe to the Mosquitto broker and forward telemetry into TelemetryIngestService.';

    public function __construct(private readonly TelemetryIngestService $telemetry)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $host = (string) ($this->option('host') ?: config('mqtt.host'));
        $port = (int)    ($this->option('port') ?: config('mqtt.port'));
        $prefix = trim((string) config('mqtt.topic_prefix'), '/');

        if ($prefix === '') {
            $this->error('MQTT_TOPIC_PREFIX is empty. Refusing to subscribe to # (would flood).');
            return self::FAILURE;
        }

        $clientId = config('mqtt.client_id').'-sub-'.substr(bin2hex(random_bytes(3)), 0, 6);

        $this->info("Connecting to {$host}:{$port} as {$clientId}");

        $client = new PhpMqttClient($host, $port, $clientId);

        try {
            $client->connect($this->connectionSettings(), true);
        } catch (MqttClientException $e) {
            $this->error("MQTT connect failed: {$e->getMessage()}");
            return self::FAILURE;
        }

        $this->subscribeAll($client, $prefix);

        $this->info('Subscribed. Waiting for messages... (Ctrl+C to exit)');

        try {
            // loop() returns when the connection drops. We rely on the
            // supervisor (systemd / docker restart policy / `php artisan
            // mqtt:subscribe` rerun in dev) to bring us back up rather than
            // reconnecting inside the process — see connectionSettings().
            $client->loop(true, (bool) $this->option('once'));
        } catch (MqttClientException $e) {
            Log::error('MQTT loop error', ['exception' => $e]);
            $this->error("MQTT loop error: {$e->getMessage()}");
        } finally {
            try { $client->disconnect(); } catch (Throwable) {}
        }

        return self::SUCCESS;
    }

    private function connectionSettings(): ConnectionSettings
    {
        // We use clean session (see connect(..., true) below) so the broker
        // doesn't accumulate dead subscriptions tied to our random client-id
        // suffix. php-mqtt forbids combining clean session with automatic
        // reconnect, so this command exits on broker disconnect and relies on
        // the supervisor / systemd unit to relaunch us. In dev, just rerun the
        // command after starting the broker.
        $settings = (new ConnectionSettings())
            ->setKeepAliveInterval((int) config('mqtt.keepalive_interval', 30));

        $username = config('mqtt.username');
        $password = config('mqtt.password');

        if (! empty($username)) {
            $settings = $settings->setUsername($username);
        }
        if (! empty($password)) {
            $settings = $settings->setPassword($password);
        }

        return $settings;
    }

    private function subscribeAll(MqttClient $client, string $prefix): void
    {
        $qos = (int) config('mqtt.qos', 1);

        // BLUEPRINT.md topic structure:
        //   {prefix}/station/{station}/turnout/{turnout}/state
        //   {prefix}/station/{station}/node/{node_id}/heartbeat
        //   {prefix}/station/{station}/node/{node_id}/health
        // (Alarms are derived server-side from state=FAILURE; no separate
        //  alarm subscription is needed.)
        $client->subscribe("{$prefix}/station/+/turnout/+/state",     $this->handleState(...),     $qos);
        $client->subscribe("{$prefix}/station/+/node/+/heartbeat",    $this->handleHeartbeat(...), $qos);
        $client->subscribe("{$prefix}/station/+/node/+/health",       $this->handleHealth(...),    $qos);
    }

    private function handleState(string $topic, string $message): void
    {
        $this->safelyIngest('state', $topic, $message, fn (array $p) => $this->telemetry->ingestState($p));
    }

    private function handleHeartbeat(string $topic, string $message): void
    {
        $this->safelyIngest('heartbeat', $topic, $message, fn (array $p) => $this->telemetry->ingestHeartbeat($p));
    }

    private function handleHealth(string $topic, string $message): void
    {
        $this->safelyIngest('health', $topic, $message, fn (array $p) => $this->telemetry->ingestHealth($p));
    }

    private function safelyIngest(string $kind, string $topic, string $message, callable $handler): void
    {
        $payload = json_decode($message, true);
        if (! is_array($payload)) {
            $this->warn("[{$kind}] {$topic}: malformed JSON, skipped.");
            Log::warning('MQTT malformed payload', ['kind' => $kind, 'topic' => $topic, 'raw' => $message]);
            return;
        }

        try {
            $handler($payload);
            $this->line("[{$kind}] {$topic} OK");
        } catch (Throwable $e) {
            $this->warn("[{$kind}] {$topic}: {$e->getMessage()}");
            Log::warning('MQTT ingest failed', [
                'kind'    => $kind,
                'topic'   => $topic,
                'payload' => $payload,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}

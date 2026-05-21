<?php

namespace App\Services\Notifications;

use App\Models\NotificationChannel;
use App\Models\NotificationLog;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fan-out point for all outbound notifications. Listeners (e.g.
 * SendAlarmNotifications) call dispatch($event, $payload) and we walk
 * every enabled channel that subscribes to that event, run its driver,
 * and log the outcome.
 *
 * Failures in one channel never block another — each driver is wrapped
 * in its own try/catch + log row.
 */
class NotificationDispatcher
{
    /** @var array<string, class-string<NotificationChannelDriver>> */
    private const DRIVERS = [
        'webhook'  => WebhookDriver::class,
        'email'    => EmailDriver::class,
        'whatsapp' => WhatsappDriver::class,
    ];

    public function dispatch(string $event, array $payload): void
    {
        $channels = NotificationChannel::query()
            ->where('is_enabled', true)
            ->get()
            ->filter(fn (NotificationChannel $c) => $c->listensFor($event));

        foreach ($channels as $channel) {
            $this->sendOne($channel, $event, $payload);
        }
    }

    /** Operator "test" button — same path, but a synthetic payload. */
    public function testChannel(NotificationChannel $channel): NotificationLog
    {
        return $this->sendOne($channel, 'test', [
            'message'   => 'Test notification from MRT Turnout Monitoring',
            'triggered' => now()->toIso8601String(),
        ]);
    }

    private function sendOne(NotificationChannel $channel, string $event, array $payload): NotificationLog
    {
        $driverClass = self::DRIVERS[$channel->type] ?? null;
        if (!$driverClass) {
            return $this->log($channel, $event, 'failed', "unknown driver: {$channel->type}", $payload);
        }

        try {
            /** @var NotificationChannelDriver $driver */
            $driver = app($driverClass);
            [$status, $summary, $response] = $driver->send($channel, $event, $payload);

            if ($status === 'sent') {
                $channel->forceFill(['last_sent_at' => now()])->save();
            }

            return $this->log($channel, $event, $status, $summary, $payload, $response);
        } catch (Throwable $e) {
            Log::error('Notification driver crashed', [
                'channel' => $channel->id, 'event' => $event, 'exception' => $e,
            ]);
            return $this->log($channel, $event, 'failed', 'driver crashed: ' . $e->getMessage(), $payload);
        }
    }

    private function log(NotificationChannel $channel, string $event, string $status, string $summary, array $payload, array $response = []): NotificationLog
    {
        return NotificationLog::create([
            'channel_id' => $channel->id,
            'event'      => $event,
            'status'     => $status,
            'summary'    => substr($summary, 0, 250),
            'payload'    => $payload,
            'response'   => $response,
            'sent_at'    => now(),
        ]);
    }
}

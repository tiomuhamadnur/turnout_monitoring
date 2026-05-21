<?php

namespace App\Services\Notifications;

use App\Models\NotificationChannel;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Generic webhook driver. config:
 *   {
 *     "url":     "https://example.com/hook",
 *     "method":  "POST",          // default POST
 *     "headers": { "X-Token": "..." },
 *     "timeout": 5                 // seconds
 *   }
 *
 * The HTTP body is { event, payload, timestamp }.
 */
class WebhookDriver implements NotificationChannelDriver
{
    public function send(NotificationChannel $channel, string $event, array $payload): array
    {
        $cfg = $channel->config ?: [];
        $url = $cfg['url'] ?? null;
        if (!$url) {
            return ['skipped', 'no url configured', []];
        }

        $method  = strtoupper($cfg['method'] ?? 'POST');
        $headers = is_array($cfg['headers'] ?? null) ? $cfg['headers'] : [];
        $timeout = (int) ($cfg['timeout'] ?? 5);

        $body = [
            'event'     => $event,
            'payload'   => $payload,
            'timestamp' => now()->toIso8601String(),
        ];

        try {
            $response = Http::withHeaders($headers)
                ->timeout($timeout)
                ->{$method === 'GET' ? 'get' : 'send'}(... $method === 'GET'
                    ? [$url, $body]
                    : [$method, $url, ['json' => $body]]);

            return $response->successful()
                ? ['sent', "HTTP {$response->status()}", ['status' => $response->status(), 'body' => $response->json() ?? $response->body()]]
                : ['failed', "HTTP {$response->status()}", ['status' => $response->status(), 'body' => $response->body()]];
        } catch (Throwable $e) {
            return ['failed', 'transport error: ' . $e->getMessage(), ['exception' => $e->getMessage()]];
        }
    }
}

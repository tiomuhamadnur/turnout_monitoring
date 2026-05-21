<?php

namespace App\Services\Notifications;

use App\Models\NotificationChannel;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * WhatsApp driver. config:
 *   {
 *     "provider_url": "https://your-waha-or-cloud-api/...",
 *     "auth_header":  "Bearer xxxx",
 *     "to":           ["6281xxxxxxxx", "..."],
 *     "field_to":     "to",            // optional: provider's recipient field
 *     "field_text":   "message"        // optional: provider's text field
 *   }
 *
 * We don't ship a specific provider client (WAHA, Cloud API, Twilio, etc.
 * all differ) — operator configures the URL + JSON shape per their gateway.
 * Defaults match WAHA's /sendText shape.
 */
class WhatsappDriver implements NotificationChannelDriver
{
    public function send(NotificationChannel $channel, string $event, array $payload): array
    {
        $cfg = $channel->config ?: [];
        $url       = $cfg['provider_url']  ?? null;
        $authHdr   = $cfg['auth_header']   ?? null;
        $to        = (array) ($cfg['to']   ?? []);
        $fieldTo   = $cfg['field_to']      ?? 'to';
        $fieldText = $cfg['field_text']    ?? 'message';

        if (!$url) {
            return ['skipped', 'no provider_url configured', []];
        }
        if (empty($to)) {
            return ['skipped', 'no recipients configured', []];
        }

        $text = $this->renderText($event, $payload);

        $headers = ['Accept' => 'application/json', 'Content-Type' => 'application/json'];
        if ($authHdr) {
            // Allow the operator to paste e.g. "Bearer xyz" or "Basic xyz".
            $parts = explode(' ', $authHdr, 2);
            $headers['Authorization'] = $authHdr;
            // Some providers need a separate API-Key header — operators that
            // need this should layer their own headers via WebhookDriver.
        }

        $results = [];
        $ok = 0;
        foreach ($to as $recipient) {
            try {
                $body = [$fieldTo => $recipient, $fieldText => $text];
                $resp = Http::withHeaders($headers)->timeout(10)->post($url, $body);
                if ($resp->successful()) $ok++;
                $results[] = ['to' => $recipient, 'status' => $resp->status()];
            } catch (Throwable $e) {
                $results[] = ['to' => $recipient, 'error' => $e->getMessage()];
            }
        }

        if ($ok === 0) {
            return ['failed', 'all recipients failed', ['results' => $results]];
        }
        if ($ok < count($to)) {
            return ['sent', "partial: {$ok}/" . count($to), ['results' => $results]];
        }
        return ['sent', "delivered to {$ok} recipient(s)", ['results' => $results]];
    }

    private function renderText(string $event, array $p): string
    {
        if ($event === 'alarm.raised') {
            return "*ALARM* {$p['turnout_code']} at {$p['station_code']}\n"
                 . ($p['turnout_name'] ?? '')
                 . "\nStarted: " . ($p['started_at'] ?? '');
        }
        if ($event === 'alarm.cleared') {
            return "Cleared: {$p['turnout_code']} at {$p['station_code']}\n"
                 . "Ended: " . ($p['ended_at'] ?? '');
        }
        return "Event {$event}\n" . json_encode($p, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}

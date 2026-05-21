<?php

namespace App\Services\Notifications;

use App\Models\NotificationChannel;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Email driver. config:
 *   {
 *     "recipients": ["ops@example.com", "..."],
 *     "subject_prefix": "[MRT Turnout]"
 *   }
 *
 * Uses the default mail driver from .env. Test SMTP locally with
 * MAIL_MAILER=log and look in storage/logs.
 */
class EmailDriver implements NotificationChannelDriver
{
    public function send(NotificationChannel $channel, string $event, array $payload): array
    {
        $cfg = $channel->config ?: [];
        $recipients = (array) ($cfg['recipients'] ?? []);
        $recipients = array_values(array_filter($recipients, fn ($r) => filter_var($r, FILTER_VALIDATE_EMAIL)));

        if (empty($recipients)) {
            return ['skipped', 'no valid recipients', []];
        }

        $subjectPrefix = $cfg['subject_prefix'] ?? '[MRT Turnout]';
        $title  = sprintf('%s %s', $subjectPrefix, $this->humanEvent($event, $payload));
        $body   = $this->renderBody($event, $payload);

        try {
            Mail::raw($body, function ($message) use ($recipients, $title) {
                $message->to($recipients)->subject($title);
            });
            return ['sent', 'mail dispatched to '.count($recipients).' recipient(s)', ['recipients' => $recipients]];
        } catch (Throwable $e) {
            return ['failed', 'mail error: ' . $e->getMessage(), ['exception' => $e->getMessage()]];
        }
    }

    private function humanEvent(string $event, array $p): string
    {
        return match ($event) {
            'alarm.raised'  => "ALARM: {$p['turnout_code']} @ {$p['station_code']}",
            'alarm.cleared' => "Cleared: {$p['turnout_code']} @ {$p['station_code']}",
            default         => $event,
        };
    }

    private function renderBody(string $event, array $p): string
    {
        $lines = ["Event: {$event}", str_repeat('-', 40)];
        foreach ($p as $k => $v) {
            $lines[] = sprintf('%-18s : %s', $k, is_scalar($v) ? $v : json_encode($v));
        }
        $lines[] = '';
        $lines[] = 'MRT Turnout Monitoring · ' . now()->toDateTimeString();
        return implode("\n", $lines);
    }
}

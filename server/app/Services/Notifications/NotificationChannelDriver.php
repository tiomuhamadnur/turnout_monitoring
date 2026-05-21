<?php

namespace App\Services\Notifications;

use App\Models\NotificationChannel;

/**
 * Tiny contract every notification driver implements. Returns a tuple
 * compatible with NotificationDispatcher's logging:
 *   [status: 'sent'|'failed'|'skipped', summary: string, response: array]
 *
 * Drivers MUST NOT throw — they catch their own transport errors and
 * report 'failed' instead, so a flaky webhook can't break the rest of
 * the dispatch fan-out.
 */
interface NotificationChannelDriver
{
    /** @return array{0:string,1:string,2:array} */
    public function send(NotificationChannel $channel, string $event, array $payload): array;
}

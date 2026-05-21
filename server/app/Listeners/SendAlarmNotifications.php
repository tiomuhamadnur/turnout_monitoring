<?php

namespace App\Listeners;

use App\Events\TurnoutAlarmCleared;
use App\Events\TurnoutAlarmRaised;
use App\Services\Notifications\NotificationDispatcher;

/**
 * Subscribes to the alarm broadcast events emitted by
 * TelemetryIngestService and pushes them through the notification
 * dispatcher (webhook / email / WhatsApp).
 *
 * This is intentionally a regular (synchronous) listener — the dispatcher
 * is already cheap and the drivers handle their own timeouts. If
 * notifications become slow we can queue this with ShouldQueue without
 * touching the alarm path.
 */
class SendAlarmNotifications
{
    public function __construct(private readonly NotificationDispatcher $dispatcher) {}

    public function raised(TurnoutAlarmRaised $event): void
    {
        $this->dispatcher->dispatch('alarm.raised', $event->broadcastWith());
    }

    public function cleared(TurnoutAlarmCleared $event): void
    {
        $this->dispatcher->dispatch('alarm.cleared', $event->broadcastWith());
    }

    public function subscribe(): array
    {
        return [
            TurnoutAlarmRaised::class  => 'raised',
            TurnoutAlarmCleared::class => 'cleared',
        ];
    }
}

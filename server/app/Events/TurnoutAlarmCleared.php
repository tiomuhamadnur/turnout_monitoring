<?php

namespace App\Events;

use App\Models\TurnoutAlarm;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an active alarm row is closed (is_active flipped to false
 * because the turnout left FAILURE). Lets the dashboard drop the popup
 * and persistent fault indicator.
 */
class TurnoutAlarmCleared implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public TurnoutAlarm $alarm)
    {
        $this->alarm->loadMissing(['turnout.station', 'node']);
    }

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        $stationCode = $this->alarm->turnout?->station?->code ?? 'unknown';

        return [
            new Channel('turnouts.global'),
            new Channel("turnouts.station.{$stationCode}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'turnout.alarm.cleared';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        $turnout = $this->alarm->turnout;
        $station = $turnout?->station;

        return [
            'alarm_id'     => $this->alarm->id,
            'turnout_id'   => $turnout?->id,
            'turnout_code' => $turnout?->code,
            'station_code' => $station?->code,
            'ended_at'     => optional($this->alarm->ended_at)->toIso8601String(),
        ];
    }
}

<?php

namespace App\Events;

use App\Models\TurnoutAlarm;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a NEW alarm row is created (turnout entered FAILURE). The
 * dashboard uses this to show the popup + play the browser sound. Existing
 * alarm rows that stay active do not re-broadcast — those are owned by
 * TurnoutStateUpdated.
 */
class TurnoutAlarmRaised implements ShouldBroadcastNow
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
        return 'turnout.alarm.raised';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        $turnout = $this->alarm->turnout;
        $station = $turnout?->station;

        return [
            'alarm_id'      => $this->alarm->id,
            'turnout_id'    => $turnout?->id,
            'turnout_uuid'  => $turnout?->uuid,
            'turnout_code'  => $turnout?->code,
            'turnout_name'  => $turnout?->name,
            'station_code'  => $station?->code,
            'station_name'  => $station?->name,
            'node_id'       => $this->alarm->node?->node_id,
            'alarm_type'    => $this->alarm->alarm_type,
            'state'         => $this->alarm->state,
            'started_at'    => optional($this->alarm->started_at)->toIso8601String(),
        ];
    }
}

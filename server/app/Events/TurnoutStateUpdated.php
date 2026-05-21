<?php

namespace App\Events;

use App\Models\TurnoutState;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after a turnout's state row is upserted. Carries the latest
 * state plus the previous one (when known) so the dashboard can render
 * an animated transition without needing a follow-up API call.
 *
 * Implements ShouldBroadcastNow so realtime telemetry doesn't sit in
 * the queue waiting for a worker — fan-out has to feel instant on the
 * dashboard.
 */
class TurnoutStateUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public TurnoutState $state,
        public ?string $previousState = null,
    ) {
        $this->state->loadMissing(['turnout.station', 'node']);
    }

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        $stationCode = $this->state->turnout?->station?->code ?? 'unknown';

        return [
            new Channel('turnouts.global'),
            new Channel("turnouts.station.{$stationCode}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'turnout.state.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        $turnout = $this->state->turnout;
        $station = $turnout?->station;

        return [
            'turnout_id'        => $turnout?->id,
            'turnout_uuid'      => $turnout?->uuid,
            'turnout_code'      => $turnout?->code,
            'station_code'      => $station?->code,
            'node_id'           => $this->state->node?->node_id,
            'state'             => $this->state->state,
            'previous_state'    => $this->previousState,
            'channel_a'         => (bool) $this->state->channel_a,
            'channel_b'         => (bool) $this->state->channel_b,
            'source_timestamp'  => optional($this->state->source_timestamp)->toIso8601String(),
            'received_at'       => optional($this->state->received_at)->toIso8601String(),
        ];
    }
}

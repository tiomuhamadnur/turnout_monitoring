<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Station;
use App\Models\TurnoutAlarm;

/**
 * Read-only dashboard endpoints. Each method returns the snapshot the SPA
 * needs to seed its realtime store; further updates arrive on Reverb.
 */
class DashboardController extends Controller
{
    /**
     * Live snapshot: stations with their turnouts and current state,
     * plus active alarms. Used by Dashboard.vue on mount; Reverb keeps
     * the data fresh from there on.
     */
    public function live(): array
    {
        $stations = Station::query()
            ->orderBy('code')
            ->with([
                'turnouts' => fn ($q) => $q->orderBy('code'),
            ])
            ->get();

        // One eager query for the current TurnoutState rows so we don't N+1
        // when there are dozens of turnouts.
        $turnoutIds = $stations->flatMap(fn ($s) => $s->turnouts->pluck('id'));
        $states = \App\Models\TurnoutState::query()
            ->whereIn('turnout_id', $turnoutIds)
            ->with('node:id,node_id')
            ->get()
            ->keyBy('turnout_id');

        $activeAlarms = TurnoutAlarm::query()
            ->where('is_active', true)
            ->whereIn('turnout_id', $turnoutIds)
            ->get()
            ->keyBy('turnout_id');

        return [
            'stations' => $stations->map(function (Station $station) use ($states, $activeAlarms) {
                return [
                    'id'        => $station->id,
                    'code'      => $station->code,
                    'name'      => $station->name,
                    'turnouts'  => $station->turnouts->map(function ($turnout) use ($states, $activeAlarms) {
                        $state = $states->get($turnout->id);
                        $alarm = $activeAlarms->get($turnout->id);

                        return [
                            'id'               => $turnout->id,
                            'uuid'             => $turnout->uuid,
                            'code'             => $turnout->code,
                            'name'             => $turnout->name,
                            'type'             => $turnout->type,
                            'state'            => $state?->state,
                            'channel_a'        => (bool) ($state?->channel_a ?? false),
                            'channel_b'        => (bool) ($state?->channel_b ?? false),
                            'node_id'          => $state?->node?->node_id,
                            'source_timestamp' => optional($state?->source_timestamp)->toIso8601String(),
                            'has_active_alarm' => (bool) $alarm,
                            'alarm_started_at' => optional($alarm?->started_at)->toIso8601String(),
                        ];
                    })->values(),
                ];
            })->values(),
            'broadcast' => [
                'driver' => config('broadcasting.default'),
                // Tells the SPA which station channels to subscribe to,
                // so it doesn't need a second config endpoint.
                'channels' => $stations->pluck('code')
                    ->map(fn (string $code) => "turnouts.station.{$code}")
                    ->push('turnouts.global')
                    ->values(),
            ],
        ];
    }
}

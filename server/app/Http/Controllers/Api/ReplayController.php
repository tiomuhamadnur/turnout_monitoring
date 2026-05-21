<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Station;
use App\Models\Turnout;
use App\Models\TurnoutEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Read-only API powering the Replay page. Two endpoints:
 *
 *   GET /api/replay/stations          → list stations + their turnouts
 *   GET /api/replay/timeline          → events for the chosen station/window
 *
 * The frontend then steps through the returned event stream client-side
 * (timeline scrubber + play/pause/speed). We do NOT replay on the server —
 * keeping playback in the browser avoids long-lived sockets and works
 * offline once the timeline is loaded.
 */
class ReplayController extends Controller
{
    public function stations(): array
    {
        $this->authorize('turnouts.view');

        $stations = Station::query()
            ->orderBy('code')
            ->with(['turnouts' => fn ($q) => $q->orderBy('code')])
            ->get();

        return [
            'data' => $stations->map(fn (Station $s) => [
                'id'       => $s->id,
                'code'     => $s->code,
                'name'     => $s->name,
                'turnouts' => $s->turnouts->map(fn (Turnout $t) => [
                    'id'   => $t->id,
                    'uuid' => $t->uuid,
                    'code' => $t->code,
                    'name' => $t->name,
                ])->values(),
            ])->values(),
        ];
    }

    public function timeline(Request $request): array
    {
        $this->authorize('turnouts.view');

        $request->validate([
            'station_id' => ['required', 'integer', 'exists:stations,id'],
            'from'       => ['required', 'date'],
            'to'         => ['required', 'date', 'after:from'],
        ]);

        $station = Station::query()->findOrFail($request->integer('station_id'));
        $from    = Carbon::parse($request->date('from'));
        $to      = Carbon::parse($request->date('to'));

        // Hard cap to keep the SPA snappy; the user can narrow the window
        // if they hit the cap.
        $maxEvents = (int) $request->integer('max', 5000);

        $turnoutIds = $station->turnouts()->pluck('id');

        // Seed state at $from: the latest event per turnout BEFORE the
        // window. Needed so playback starts from a known position rather
        // than "UNKNOWN until the first event lands".
        $seed = TurnoutEvent::query()
            ->whereIn('turnout_id', $turnoutIds)
            ->where('source_timestamp', '<', $from)
            ->selectRaw('turnout_id, MAX(id) as last_id')
            ->groupBy('turnout_id')
            ->pluck('last_id');

        $seedEvents = TurnoutEvent::query()
            ->whereIn('id', $seed)
            ->with(['turnout:id,code,uuid', 'node:id,node_id'])
            ->get();

        $events = TurnoutEvent::query()
            ->whereIn('turnout_id', $turnoutIds)
            ->whereBetween('source_timestamp', [$from, $to])
            ->with(['turnout:id,code,uuid', 'node:id,node_id'])
            ->orderBy('source_timestamp')
            ->limit($maxEvents + 1)
            ->get();

        $truncated = $events->count() > $maxEvents;
        if ($truncated) {
            $events = $events->take($maxEvents);
        }

        return [
            'station' => [
                'id'   => $station->id,
                'code' => $station->code,
                'name' => $station->name,
            ],
            'window' => [
                'from' => $from->toIso8601String(),
                'to'   => $to->toIso8601String(),
            ],
            'turnouts' => $station->turnouts->map(fn (Turnout $t) => [
                'id'   => $t->id,
                'uuid' => $t->uuid,
                'code' => $t->code,
                'name' => $t->name,
            ])->values(),
            'seed' => $seedEvents->map(fn (TurnoutEvent $e) => [
                'turnout_id'   => $e->turnout_id,
                'turnout_code' => $e->turnout?->code,
                'state'        => $e->state,
                'channel_a'    => (bool) $e->channel_a,
                'channel_b'    => (bool) $e->channel_b,
            ])->values(),
            'events' => $events->map(fn (TurnoutEvent $e) => [
                'id'             => $e->id,
                'turnout_id'     => $e->turnout_id,
                'turnout_code'   => $e->turnout?->code,
                'turnout_uuid'   => $e->turnout?->uuid,
                'node_id'        => $e->node?->node_id,
                'state'          => $e->state,
                'previous_state' => $e->previous_state,
                'channel_a'      => (bool) $e->channel_a,
                'channel_b'      => (bool) $e->channel_b,
                'is_transition'  => (bool) $e->is_transition,
                'timestamp'      => $e->source_timestamp?->toIso8601String(),
            ])->values(),
            'truncated' => $truncated,
            'event_count' => $events->count(),
        ];
    }
}

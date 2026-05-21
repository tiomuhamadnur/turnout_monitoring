<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceHealthLog;
use App\Models\Node;
use App\Models\Turnout;
use App\Models\TurnoutAlarm;
use App\Models\TurnoutEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Derived historian queries. These are NOT raw rows — they fold the event
 * stream into shapes the UI needs: duration spent in each state, alarm
 * counts per period, communication gap detection per node.
 */
class HistorianController extends Controller
{
    /**
     * GET /api/historian/state-duration
     *
     * Per-turnout aggregate of how long each turnout sat in each state
     * inside the requested window. Computed by sweeping turnout_events
     * (which already contains the canonical transition log).
     *
     * Query params:
     *   turnout_id (optional) — limit to one turnout
     *   station_id (optional) — limit to one station
     *   from, to              — ISO timestamps; defaults to last 24h
     */
    public function stateDuration(Request $request): array
    {
        $this->authorize('turnouts.view');

        $from = $request->date('from') ?: now()->subDay();
        $to   = $request->date('to')   ?: now();

        $turnouts = Turnout::query()
            ->when($request->integer('turnout_id'), fn ($q, $id) => $q->where('id', $id))
            ->when($request->integer('station_id'), fn ($q, $id) => $q->where('station_id', $id))
            ->select(['id', 'code', 'name', 'station_id'])
            ->get();

        $results = $turnouts->map(function (Turnout $turnout) use ($from, $to) {
            $events = TurnoutEvent::query()
                ->where('turnout_id', $turnout->id)
                ->where('source_timestamp', '<=', $to)
                ->orderBy('source_timestamp')
                ->get(['state', 'source_timestamp']);

            $durations = ['NORMAL' => 0, 'REVERSE' => 0, 'FAILURE' => 0];
            $transitions = 0;
            $currentState   = null;
            $currentStartTs = Carbon::parse($from);

            foreach ($events as $ev) {
                $evTs = Carbon::parse($ev->source_timestamp);

                if ($evTs->lt($from)) {
                    // Pre-window event — only used to establish initial state at $from.
                    $currentState = $ev->state;
                    continue;
                }

                if ($currentState !== null) {
                    $durations[$currentState] = ($durations[$currentState] ?? 0)
                        + $currentStartTs->diffInSeconds($evTs);
                }

                if ($currentState !== null && $currentState !== $ev->state) {
                    $transitions++;
                }
                $currentState   = $ev->state;
                $currentStartTs = $evTs;
            }

            // Close the trailing segment at `to`.
            if ($currentState !== null) {
                $durations[$currentState] = ($durations[$currentState] ?? 0)
                    + $currentStartTs->diffInSeconds($to);
            }

            $total = array_sum($durations) ?: 1;

            return [
                'turnout_id'   => $turnout->id,
                'turnout_code' => $turnout->code,
                'turnout_name' => $turnout->name,
                'station_id'   => $turnout->station_id,
                'transitions'  => $transitions,
                'seconds'      => $durations,
                'percent'      => [
                    'NORMAL'  => round(($durations['NORMAL']  ?? 0) / $total * 100, 2),
                    'REVERSE' => round(($durations['REVERSE'] ?? 0) / $total * 100, 2),
                    'FAILURE' => round(($durations['FAILURE'] ?? 0) / $total * 100, 2),
                ],
            ];
        });

        return [
            'window' => [
                'from' => Carbon::parse($from)->toIso8601String(),
                'to'   => Carbon::parse($to)->toIso8601String(),
            ],
            'data' => $results->values(),
        ];
    }

    /**
     * GET /api/historian/communication
     *
     * Per-node uptime analysis: gaps between consecutive heartbeats/health
     * logs that exceed the configured threshold count as an outage. Useful
     * for "the node went silent at 14:23 for 11 minutes" reports.
     *
     * Query params:
     *   node_id      (optional) — limit to one node
     *   station_id   (optional) — limit to one station
     *   from, to                — ISO timestamps; defaults to last 24h
     *   gap_seconds  (default 60) — threshold to classify as outage
     */
    public function communication(Request $request): array
    {
        $this->authorize('nodes.view');

        $from = $request->date('from') ?: now()->subDay();
        $to   = $request->date('to')   ?: now();
        $gapThreshold = max(15, $request->integer('gap_seconds', 60));

        $nodes = Node::query()
            ->when($request->integer('node_id'),    fn ($q, $id) => $q->where('id', $id))
            ->when($request->integer('station_id'), fn ($q, $id) => $q->where('station_id', $id))
            ->select(['id', 'node_id', 'name', 'station_id', 'last_heartbeat_at', 'mqtt_status', 'status'])
            ->get();

        $results = $nodes->map(function (Node $node) use ($from, $to, $gapThreshold) {
            // Each DeviceHealthLog row marks "we heard from this node at T".
            // We treat their source_timestamp as the heartbeat stream.
            $timestamps = DeviceHealthLog::query()
                ->where('node_id', $node->id)
                ->whereBetween('source_timestamp', [$from, $to])
                ->orderBy('source_timestamp')
                ->pluck('source_timestamp');

            $outages  = [];
            $totalOutageSeconds = 0;
            $previousTs = Carbon::parse($from);

            foreach ($timestamps as $tsRaw) {
                $ts  = Carbon::parse($tsRaw);
                $gap = $previousTs->diffInSeconds($ts);
                if ($gap >= $gapThreshold) {
                    $outages[] = [
                        'from'    => $previousTs->toIso8601String(),
                        'to'      => $ts->toIso8601String(),
                        'seconds' => $gap,
                    ];
                    $totalOutageSeconds += $gap;
                }
                $previousTs = $ts;
            }

            // Trailing gap up to "to" — if the node has been silent since
            // its last heartbeat, that's an open outage.
            $trailingGap = $previousTs->diffInSeconds($to);
            if ($trailingGap >= $gapThreshold && $timestamps->isNotEmpty()) {
                $outages[] = [
                    'from'    => $previousTs->toIso8601String(),
                    'to'      => Carbon::parse($to)->toIso8601String(),
                    'seconds' => $trailingGap,
                    'open'    => true,
                ];
                $totalOutageSeconds += $trailingGap;
            }

            $windowSeconds = max(1, Carbon::parse($from)->diffInSeconds($to));
            $uptimePercent = round((1 - ($totalOutageSeconds / $windowSeconds)) * 100, 2);

            return [
                'node_id'           => $node->node_id,
                'node_pk'           => $node->id,
                'name'              => $node->name,
                'station_id'        => $node->station_id,
                'status'            => $node->status,
                'mqtt_status'       => $node->mqtt_status,
                'last_heartbeat_at' => optional($node->last_heartbeat_at)->toIso8601String(),
                'heartbeats'        => $timestamps->count(),
                'outage_count'      => count($outages),
                'outage_seconds'    => $totalOutageSeconds,
                'uptime_percent'    => max(0, min(100, $uptimePercent)),
                'outages'           => $outages,
            ];
        });

        return [
            'window' => [
                'from' => Carbon::parse($from)->toIso8601String(),
                'to'   => Carbon::parse($to)->toIso8601String(),
                'gap_threshold_seconds' => $gapThreshold,
            ],
            'data' => $results->values(),
        ];
    }

    /**
     * GET /api/historian/alarm-summary
     *
     * Counts of alarms grouped by turnout in a window. Used by exports and
     * the alarm history dashboard widget.
     */
    public function alarmSummary(Request $request): array
    {
        $this->authorize('alarms.view');

        $from = $request->date('from') ?: now()->subDays(7);
        $to   = $request->date('to')   ?: now();

        $rows = TurnoutAlarm::query()
            ->selectRaw('turnout_id, COUNT(*) as alarm_count,
                         SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_count,
                         MIN(started_at) as first_alarm,
                         MAX(started_at) as last_alarm')
            ->whereBetween('started_at', [$from, $to])
            ->when($request->integer('station_id'), function ($q, $stationId) {
                $q->whereHas('turnout', fn ($t) => $t->where('station_id', $stationId));
            })
            ->groupBy('turnout_id')
            ->with('turnout:id,code,name,station_id')
            ->get();

        return [
            'window' => [
                'from' => Carbon::parse($from)->toIso8601String(),
                'to'   => Carbon::parse($to)->toIso8601String(),
            ],
            'data' => $rows->map(fn ($r) => [
                'turnout_id'   => $r->turnout_id,
                'turnout_code' => $r->turnout?->code,
                'turnout_name' => $r->turnout?->name,
                'station_id'   => $r->turnout?->station_id,
                'alarm_count'  => (int) $r->alarm_count,
                'active_count' => (int) $r->active_count,
                'first_alarm'  => $r->first_alarm,
                'last_alarm'   => $r->last_alarm,
            ])->values(),
        ];
    }
}

<?php

namespace Database\Seeders;

use App\Models\DeviceHealthLog;
use App\Models\Node;
use App\Models\Turnout;
use App\Models\TurnoutAlarm;
use App\Models\TurnoutEvent;
use App\Models\TurnoutState;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class RuntimeDemoSeeder extends Seeder
{
    public function run(): void
    {
        $turnouts = Turnout::query()
            ->orderBy('station_id')
            ->orderBy('code')
            ->get();

        $nodes = Node::query()->orderBy('station_id')->orderBy('node_id')->get();

        if ($turnouts->isEmpty() || $nodes->isEmpty()) {
            $this->command?->warn('RuntimeDemoSeeder skipped: nodes or turnouts are missing.');
            return;
        }

        TurnoutAlarm::query()->delete();
        TurnoutEvent::query()->delete();
        TurnoutState::query()->delete();
        DeviceHealthLog::query()->delete();

        $now = Carbon::now()->startOfMinute();
        $nodeAssignments = [];

        foreach ($turnouts as $turnout) {
            $nodeAssignments[$turnout->id] = $nodes->firstWhere('station_id', $turnout->station_id) ?? $nodes->first();
        }

        $this->seedHealthLogs($nodes, $now);
        $this->seedTurnoutHistory($turnouts, $nodeAssignments, $now);

        $this->command?->info(sprintf(
            'Runtime demo seeded: %d events, %d alarms, %d current states, %d health logs.',
            TurnoutEvent::count(),
            TurnoutAlarm::count(),
            TurnoutState::count(),
            DeviceHealthLog::count(),
        ));
    }

    private function seedHealthLogs($nodes, Carbon $now): void
    {
        foreach ($nodes as $index => $node) {
            for ($i = 18; $i >= 1; $i--) {
                $timestamp = $now->copy()->subMinutes($i * 10 + ($index * 2));
                $cpu = min(100, 18 + ($i % 6) * 7 + $index * 4);
                $ram = min(100, 42 + ($i % 5) * 5 + $index * 3);
                $disk = min(100, 61 + $index * 2 + (int) floor($i / 6));
                $mqtt = $i === 7 ? 'disconnected' : 'connected';

                DeviceHealthLog::create([
                    'node_id' => $node->id,
                    'cpu_usage' => $cpu,
                    'ram_usage' => $ram,
                    'disk_usage' => $disk,
                    'uptime_seconds' => 86400 + (($index + 1) * 5400) + ((18 - $i) * 600),
                    'mqtt_status' => $mqtt,
                    'container_health' => [
                        'laravel-app' => 'healthy',
                        'queue-worker' => 'healthy',
                        'mosquitto' => $mqtt === 'connected' ? 'healthy' : 'degraded',
                    ],
                    'source_timestamp' => $timestamp,
                    'received_at' => $timestamp->copy()->addSeconds(3),
                    'raw_payload' => [
                        'demo' => true,
                        'node_id' => $node->node_id,
                    ],
                ]);
            }

            $latest = DeviceHealthLog::query()->where('node_id', $node->id)->latest('source_timestamp')->first();
            $node->update([
                'status' => 'online',
                'mqtt_status' => $latest?->mqtt_status ?? 'unknown',
                'last_heartbeat_at' => $now->copy()->subSeconds($index * 5),
                'last_health_at' => $latest?->source_timestamp,
                'metadata' => array_filter([
                    ...(is_array($node->metadata) ? $node->metadata : []),
                    'demo_mode' => true,
                    'container_health' => $latest?->container_health,
                ], fn ($value) => $value !== null),
            ]);
        }
    }

    private function seedTurnoutHistory($turnouts, array $nodeAssignments, Carbon $now): void
    {
        foreach ($turnouts as $index => $turnout) {
            $node = $nodeAssignments[$turnout->id];
            $base = $now->copy()->subHours(6)->addMinutes($index * 8);

            $timeline = [
                ['minutes' => 0,   'state' => 'NORMAL',  'a' => true,  'b' => false],
                ['minutes' => 26,  'state' => 'REVERSE', 'a' => false, 'b' => true],
                ['minutes' => 58,  'state' => 'NORMAL',  'a' => true,  'b' => false],
                ['minutes' => 112, 'state' => 'FAILURE', 'a' => false, 'b' => false],
                ['minutes' => 146, 'state' => 'NORMAL',  'a' => true,  'b' => false],
                ['minutes' => 198, 'state' => 'REVERSE', 'a' => false, 'b' => true],
                ['minutes' => 236, 'state' => $index % 2 === 0 ? 'FAILURE' : 'NORMAL', 'a' => false, 'b' => $index % 2 === 0 ? false : true],
            ];

            $previousState = null;
            $activeAlarm = null;

            foreach ($timeline as $step) {
                $timestamp = $base->copy()->addMinutes($step['minutes']);

                TurnoutEvent::create([
                    'turnout_id' => $turnout->id,
                    'node_id' => $node->id,
                    'event_type' => 'state',
                    'state' => $step['state'],
                    'previous_state' => $previousState,
                    'channel_a' => $step['a'],
                    'channel_b' => $step['b'],
                    'is_transition' => $previousState !== $step['state'],
                    'source_timestamp' => $timestamp,
                    'received_at' => $timestamp->copy()->addSeconds(2),
                    'raw_payload' => [
                        'demo' => true,
                        'turnout_code' => $turnout->code,
                        'node_id' => $node->node_id,
                    ],
                ]);

                if ($step['state'] === 'FAILURE' && !$activeAlarm) {
                    $activeAlarm = TurnoutAlarm::create([
                        'turnout_id' => $turnout->id,
                        'node_id' => $node->id,
                        'alarm_type' => 'failure',
                        'state' => 'FAILURE',
                        'is_active' => true,
                        'started_at' => $timestamp,
                        'context' => [
                            'demo' => true,
                            'turnout_code' => $turnout->code,
                            'reason' => 'Loss of valid indication',
                        ],
                    ]);
                }

                if ($step['state'] !== 'FAILURE' && $activeAlarm) {
                    $activeAlarm->update([
                        'is_active' => false,
                        'ended_at' => $timestamp,
                    ]);
                    $activeAlarm = null;
                }

                $previousState = $step['state'];
            }

            $last = end($timeline);

            TurnoutState::create([
                'turnout_id' => $turnout->id,
                'node_id' => $node->id,
                'state' => $last['state'],
                'channel_a' => $last['a'],
                'channel_b' => $last['b'],
                'source_timestamp' => $base->copy()->addMinutes($last['minutes']),
                'received_at' => $base->copy()->addMinutes($last['minutes'])->addSeconds(2),
            ]);
        }
    }
}

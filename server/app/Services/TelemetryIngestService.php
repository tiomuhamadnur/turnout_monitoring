<?php

namespace App\Services;

use App\Models\DeviceHealthLog;
use App\Models\Node;
use App\Models\Turnout;
use App\Models\TurnoutAlarm;
use App\Models\TurnoutEvent;
use App\Models\TurnoutState;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TelemetryIngestService
{
    public function ingestState(array $payload): TurnoutState
    {
        return DB::transaction(function () use ($payload) {
            $turnout = Turnout::query()
                ->when($payload['turnout_uuid'] ?? null, fn ($q, $uuid) => $q->where('uuid', $uuid))
                ->when(!($payload['turnout_uuid'] ?? null), fn ($q) => $q->where('code', $payload['turnout_code']))
                ->first();

            if (!$turnout) {
                throw new NotFoundHttpException('Turnout not found.');
            }

            $node = Node::query()->where('node_id', $payload['node_id'])->first();
            if (!$node) {
                throw new NotFoundHttpException('Node not found.');
            }

            $sourceTimestamp = Carbon::parse($payload['timestamp']);
            $previous = TurnoutState::query()->where('turnout_id', $turnout->id)->first();

            $state = TurnoutState::query()->updateOrCreate(
                ['turnout_id' => $turnout->id],
                [
                    'node_id' => $node->id,
                    'state' => $payload['state'],
                    'channel_a' => $payload['channel_a'],
                    'channel_b' => $payload['channel_b'],
                    'source_timestamp' => $sourceTimestamp,
                    'received_at' => now(),
                ]
            );

            TurnoutEvent::create([
                'turnout_id' => $turnout->id,
                'node_id' => $node->id,
                'event_type' => 'state',
                'state' => $payload['state'],
                'previous_state' => $previous?->state,
                'channel_a' => $payload['channel_a'],
                'channel_b' => $payload['channel_b'],
                'is_transition' => $previous?->state !== $payload['state'],
                'source_timestamp' => $sourceTimestamp,
                'received_at' => now(),
                'raw_payload' => $payload,
            ]);

            $this->syncAlarmState($turnout->id, $node->id, $payload['state'], $sourceTimestamp, $payload);

            return $state->fresh(['turnout', 'node']);
        });
    }

    public function ingestHeartbeat(array $payload): Node
    {
        $node = Node::query()->where('node_id', $payload['node_id'])->first();
        if (!$node) {
            throw new NotFoundHttpException('Node not found.');
        }

        $node->update([
            'ip_address' => $payload['ip_address'] ?? $node->ip_address,
            'status' => $payload['status'] ?? 'online',
            'mqtt_status' => $payload['mqtt_status'] ?? 'unknown',
            'last_heartbeat_at' => Carbon::parse($payload['timestamp']),
        ]);

        return $node->fresh('station');
    }

    public function ingestHealth(array $payload): DeviceHealthLog
    {
        return DB::transaction(function () use ($payload) {
            $node = Node::query()->where('node_id', $payload['node_id'])->first();
            if (!$node) {
                throw new NotFoundHttpException('Node not found.');
            }

            $sourceTimestamp = Carbon::parse($payload['timestamp']);

            $node->update([
                'status' => 'online',
                'mqtt_status' => $payload['mqtt_status'] ?? $node->mqtt_status,
                'last_health_at' => $sourceTimestamp,
                'metadata' => array_filter([
                    ...(is_array($node->metadata) ? $node->metadata : []),
                    'container_health' => $payload['container_health'] ?? null,
                ], fn ($v) => $v !== null),
            ]);

            return DeviceHealthLog::create([
                'node_id' => $node->id,
                'cpu_usage' => $payload['cpu_usage'] ?? null,
                'ram_usage' => $payload['ram_usage'] ?? null,
                'disk_usage' => $payload['disk_usage'] ?? null,
                'uptime_seconds' => $payload['uptime_seconds'] ?? null,
                'mqtt_status' => $payload['mqtt_status'] ?? 'unknown',
                'container_health' => $payload['container_health'] ?? null,
                'source_timestamp' => $sourceTimestamp,
                'received_at' => now(),
                'raw_payload' => $payload,
            ]);
        });
    }

    private function syncAlarmState(int $turnoutId, int $nodeId, string $state, Carbon $sourceTimestamp, array $payload): void
    {
        $activeAlarm = TurnoutAlarm::query()
            ->where('turnout_id', $turnoutId)
            ->where('is_active', true)
            ->first();

        if ($state === 'FAILURE') {
            if (!$activeAlarm) {
                TurnoutAlarm::create([
                    'turnout_id' => $turnoutId,
                    'node_id' => $nodeId,
                    'alarm_type' => 'failure',
                    'state' => 'FAILURE',
                    'is_active' => true,
                    'started_at' => $sourceTimestamp,
                    'context' => $payload,
                ]);
            }
            return;
        }

        if ($activeAlarm) {
            $activeAlarm->update([
                'is_active' => false,
                'ended_at' => $sourceTimestamp,
            ]);
        }
    }
}

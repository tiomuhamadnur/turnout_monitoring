<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceHealthLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'node' => $this->whenLoaded('node', fn () => [
                'id' => $this->node->id,
                'node_id' => $this->node->node_id,
                'name' => $this->node->name,
                'station' => $this->node->relationLoaded('station') && $this->node->station ? [
                    'id'   => $this->node->station->id,
                    'code' => $this->node->station->code,
                    'name' => $this->node->station->name,
                ] : null,
            ]),
            'cpu_usage' => $this->cpu_usage,
            'ram_usage' => $this->ram_usage,
            'disk_usage' => $this->disk_usage,
            'uptime_seconds' => $this->uptime_seconds,
            'mqtt_status' => $this->mqtt_status,
            'container_health' => $this->container_health,
            'source_timestamp' => $this->source_timestamp?->toIso8601String(),
            'received_at' => $this->received_at?->toIso8601String(),
        ];
    }
}

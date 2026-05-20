<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'station_id'        => $this->station_id,
            'station'           => $this->whenLoaded('station', fn () => [
                'id'   => $this->station->id,
                'code' => $this->station->code,
                'name' => $this->station->name,
            ]),
            'node_id'           => $this->node_id,
            'name'              => $this->name,
            'ip_address'        => $this->ip_address,
            'mqtt_client_id'    => $this->mqtt_client_id,
            'status'            => $this->status,
            'mqtt_status'       => $this->mqtt_status,
            'last_heartbeat_at' => $this->last_heartbeat_at?->toIso8601String(),
            'last_health_at'    => $this->last_health_at?->toIso8601String(),
            'metadata'          => $this->metadata,
            'created_at'        => $this->created_at?->toIso8601String(),
            'updated_at'        => $this->updated_at?->toIso8601String(),
        ];
    }
}

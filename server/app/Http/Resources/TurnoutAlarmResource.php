<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TurnoutAlarmResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'alarm_type' => $this->alarm_type,
            'state' => $this->state,
            'is_active' => $this->is_active,
            'turnout' => $this->whenLoaded('turnout', fn () => [
                'id' => $this->turnout->id,
                'code' => $this->turnout->code,
                'name' => $this->turnout->name,
            ]),
            'node' => $this->whenLoaded('node', fn () => [
                'id' => $this->node->id,
                'node_id' => $this->node->node_id,
                'name' => $this->node->name,
            ]),
            'started_at' => $this->started_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'context' => $this->context,
        ];
    }
}

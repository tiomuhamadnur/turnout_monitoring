<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TurnoutEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_type' => $this->event_type,
            'turnout' => $this->whenLoaded('turnout', fn () => [
                'id' => $this->turnout->id,
                'code' => $this->turnout->code,
                'name' => $this->turnout->name,
                'station' => $this->turnout->relationLoaded('station') && $this->turnout->station ? [
                    'id'   => $this->turnout->station->id,
                    'code' => $this->turnout->station->code,
                    'name' => $this->turnout->station->name,
                ] : null,
            ]),
            'node' => $this->whenLoaded('node', fn () => [
                'id' => $this->node->id,
                'node_id' => $this->node->node_id,
                'name' => $this->node->name,
            ]),
            'state' => $this->state,
            'previous_state' => $this->previous_state,
            'channel_a' => $this->channel_a,
            'channel_b' => $this->channel_b,
            'is_transition' => $this->is_transition,
            'source_timestamp' => $this->source_timestamp?->toIso8601String(),
            'received_at' => $this->received_at?->toIso8601String(),
        ];
    }
}

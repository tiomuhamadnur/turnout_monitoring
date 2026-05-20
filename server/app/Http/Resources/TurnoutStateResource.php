<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TurnoutStateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'turnout_id' => $this->turnout_id,
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
            'state' => $this->state,
            'channel_a' => $this->channel_a,
            'channel_b' => $this->channel_b,
            'source_timestamp' => $this->source_timestamp?->toIso8601String(),
            'received_at' => $this->received_at?->toIso8601String(),
        ];
    }
}

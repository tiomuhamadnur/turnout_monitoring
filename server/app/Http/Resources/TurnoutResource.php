<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class TurnoutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'uuid'         => $this->uuid,
            'station_id'   => $this->station_id,
            'line_id'      => $this->line_id,
            'station'      => $this->whenLoaded('station', fn () => [
                'id'   => $this->station->id,
                'code' => $this->station->code,
                'name' => $this->station->name,
            ]),
            'line'         => $this->whenLoaded('line', fn () => [
                'id'   => $this->line->id,
                'code' => $this->line->code,
                'name' => $this->line->name,
            ]),
            'code'         => $this->code,
            'name'         => $this->name,
            'description'  => $this->description,
            'type'         => $this->type,
            'direction'    => $this->direction,
            'chainage'     => $this->chainage,
            'latitude'     => $this->latitude,
            'longitude'    => $this->longitude,
            'photo_url'    => $this->photo_path ? route('turnouts.photo.show', $this->id) : null,
            'created_at'   => $this->created_at?->toIso8601String(),
            'updated_at'   => $this->updated_at?->toIso8601String(),
        ];
    }
}

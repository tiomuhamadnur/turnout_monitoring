<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TurnoutEvent extends Model
{
    protected $fillable = [
        'turnout_id', 'node_id', 'event_type', 'state', 'previous_state',
        'channel_a', 'channel_b', 'is_transition', 'source_timestamp',
        'received_at', 'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'channel_a' => 'boolean',
            'channel_b' => 'boolean',
            'is_transition' => 'boolean',
            'source_timestamp' => 'datetime',
            'received_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    public function turnout(): BelongsTo
    {
        return $this->belongsTo(Turnout::class);
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }
}

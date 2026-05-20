<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TurnoutState extends Model
{
    protected $fillable = [
        'turnout_id', 'node_id', 'state', 'channel_a', 'channel_b',
        'source_timestamp', 'received_at',
    ];

    protected function casts(): array
    {
        return [
            'channel_a' => 'boolean',
            'channel_b' => 'boolean',
            'source_timestamp' => 'datetime',
            'received_at' => 'datetime',
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

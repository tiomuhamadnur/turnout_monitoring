<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TurnoutAlarm extends Model
{
    protected $fillable = [
        'turnout_id', 'node_id', 'alarm_type', 'state', 'is_active',
        'started_at', 'ended_at', 'context',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'context' => 'array',
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    protected $fillable = [
        'channel_id', 'event', 'status', 'summary', 'payload', 'response', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'payload'  => 'array',
            'response' => 'array',
            'sent_at'  => 'datetime',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(NotificationChannel::class, 'channel_id');
    }
}

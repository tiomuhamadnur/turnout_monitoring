<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Node extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'station_id', 'node_id', 'name', 'ip_address', 'mqtt_client_id',
        'last_heartbeat_at', 'last_health_at', 'status', 'mqtt_status', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'last_heartbeat_at' => 'datetime',
            'last_health_at'    => 'datetime',
            'metadata'          => 'array',
        ];
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }
}

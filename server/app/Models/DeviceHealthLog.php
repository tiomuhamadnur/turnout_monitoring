<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceHealthLog extends Model
{
    protected $fillable = [
        'node_id', 'cpu_usage', 'ram_usage', 'disk_usage', 'uptime_seconds',
        'mqtt_status', 'container_health', 'source_timestamp', 'received_at',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'cpu_usage' => 'decimal:2',
            'ram_usage' => 'decimal:2',
            'disk_usage' => 'decimal:2',
            'uptime_seconds' => 'integer',
            'container_health' => 'array',
            'source_timestamp' => 'datetime',
            'received_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }
}

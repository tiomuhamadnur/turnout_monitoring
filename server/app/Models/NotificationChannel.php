<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationChannel extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'type', 'name', 'is_enabled', 'config', 'triggers', 'last_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled'   => 'boolean',
            'config'       => 'array',
            'triggers'     => 'array',
            'last_sent_at' => 'datetime',
        ];
    }

    public function listensFor(string $event): bool
    {
        $triggers = $this->triggers ?: [];
        // Empty triggers list = subscribe to everything.
        return $triggers === [] || in_array($event, $triggers, true);
    }
}

<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Turnout extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'uuid', 'station_id', 'code', 'name', 'description', 'type', 'direction', 'line_id',
        'chainage', 'latitude', 'longitude', 'photo_path',
    ];

    protected function casts(): array
    {
        return [
            'chainage'  => 'float',
            'latitude'  => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Turnout $turnout) {
            $turnout->uuid ??= (string) Str::uuid();
        });
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }
}

<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Station extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'code', 'name', 'description', 'latitude', 'longitude',
    ];

    protected function casts(): array
    {
        return [
            'latitude'  => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(Node::class);
    }

    public function turnouts(): HasMany
    {
        return $this->hasMany(Turnout::class);
    }
}

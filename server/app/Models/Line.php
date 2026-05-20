<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Line extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'code',
        'name',
        'description',
    ];

    public function turnouts(): HasMany
    {
        return $this->hasMany(Turnout::class);
    }
}

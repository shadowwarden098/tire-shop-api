<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'description',
        'price_pen',
        'duration_minutes',
        'category',
        'is_active',
    ];

    protected $casts = [
        'price_pen'        => 'decimal:2',
        'duration_minutes' => 'integer',
        'is_active'        => 'boolean',
    ];

    // ── Scopes ────────────────────────────────────────────────────
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ── Relaciones ────────────────────────────────────────────────
    public function serviceRecords()
    {
        return $this->hasMany(ServiceRecord::class);
    }
}
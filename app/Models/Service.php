<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

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
        'price_pen' => 'decimal:2',
        'duration_minutes' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Relación con registros de servicio
     */
    public function serviceRecords()
    {
        return $this->hasMany(ServiceRecord::class);
    }

    /**
     * Scope para servicios activos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para buscar servicios
     */
    public function scopeSearch($query, $term)
    {
        return $query->where(function($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('code', 'like', "%{$term}%")
              ->orWhere('description', 'like', "%{$term}%");
        });
    }

    /**
     * Obtener duración formateada
     */
    public function getFormattedDurationAttribute()
    {
        if (!$this->duration_minutes) return 'No especificado';
        
        $hours = floor($this->duration_minutes / 60);
        $minutes = $this->duration_minutes % 60;
        
        if ($hours > 0) {
            return $minutes > 0 ? "{$hours}h {$minutes}min" : "{$hours}h";
        }
        
        return "{$minutes}min";
    }

    /**
     * Obtener total de veces que se ha realizado este servicio
     */
    public function getTotalTimesPerformed()
    {
        return $this->serviceRecords()->where('status', 'completed')->count();
    }

    /**
     * Obtener ingresos totales de este servicio
     */
    public function getTotalRevenue()
    {
        return $this->serviceRecords()->where('status', 'completed')->sum('total_pen');
    }
}
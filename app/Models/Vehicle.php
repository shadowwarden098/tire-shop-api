<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'brand',
        'model',
        'year',
        'plate',
        'color',
        'tire_size',
        'vehicle_type',
        'mileage',
        'tire_size',
    ];

    protected $casts = [
        'year' => 'integer',
        'mileage' => 'integer',
    ];

    /**
     * Relación con cliente
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Relación con registros de servicio
     */
    public function serviceRecords()
    {
        return $this->hasMany(ServiceRecord::class);
    }

    /**
     * Obtener descripción completa del vehículo
     */
    public function getFullDescriptionAttribute()
    {
        return "{$this->brand} {$this->model} {$this->year} - {$this->plate}";
    }

    /**
     * Obtener último servicio realizado
     */
    public function getLastService()
    {
        return $this->serviceRecords()->latest('service_date')->first();
    }

    /**
     * Obtener total de servicios realizados
     */
    public function getTotalServicesCount()
    {
        return $this->serviceRecords()->where('status', 'completed')->count();
    }

    /**
     * Scope para buscar vehículos
     */
    public function scopeSearch($query, $term)
    {
        return $query->where(function($q) use ($term) {
            $q->where('plate', 'like', "%{$term}%")
              ->orWhere('brand', 'like', "%{$term}%")
              ->orWhere('model', 'like', "%{$term}%");
        });
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ServiceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'record_number',
        'customer_id',
        'vehicle_id',
        'service_id',
        'price_pen',
        'discount_pen',
        'total_pen',
        'payment_method',
        'status',
        'notes',
        'technician_notes',
        'mileage',
        'service_date',
        'created_by',
    ];

    protected $casts = [
        'price_pen' => 'decimal:2',
        'discount_pen' => 'decimal:2',
        'total_pen' => 'decimal:2',
        'mileage' => 'integer',
        'service_date' => 'datetime',
    ];

    /**
     * Boot del modelo
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($record) {
            if (!$record->record_number) {
                $record->record_number = self::generateRecordNumber();
            }
            
            if (!$record->service_date) {
                $record->service_date = Carbon::now();
            }
        });
    }

    /**
     * Relación con cliente
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Relación con vehículo
     */
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Relación con servicio
     */
    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Relación con usuario que creó el registro
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Generar número de registro único
     */
    public static function generateRecordNumber()
    {
        $year = date('Y');
        $lastRecord = self::whereYear('created_at', $year)
                         ->orderBy('id', 'desc')
                         ->first();
        
        $nextNumber = $lastRecord ? intval(substr($lastRecord->record_number, -6)) + 1 : 1;
        
        return 'SR-' . $year . '-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Scope para servicios del día
     */
    public function scopeToday($query)
    {
        return $query->whereDate('service_date', Carbon::today());
    }

    /**
     * Scope para servicios del mes
     */
    public function scopeThisMonth($query)
    {
        return $query->whereYear('service_date', Carbon::now()->year)
                    ->whereMonth('service_date', Carbon::now()->month);
    }

    /**
     * Scope para servicios del año
     */
    public function scopeThisYear($query)
    {
        return $query->whereYear('service_date', Carbon::now()->year);
    }

    /**
     * Scope para servicios completados
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope para buscar registros
     */
    public function scopeSearch($query, $term)
    {
        return $query->where(function($q) use ($term) {
            $q->where('record_number', 'like', "%{$term}%")
              ->orWhereHas('customer', function($customerQuery) use ($term) {
                  $customerQuery->where('name', 'like', "%{$term}%")
                              ->orWhere('document_number', 'like', "%{$term}%");
              })
              ->orWhereHas('vehicle', function($vehicleQuery) use ($term) {
                  $vehicleQuery->where('plate', 'like', "%{$term}%");
              });
        });
    }
}
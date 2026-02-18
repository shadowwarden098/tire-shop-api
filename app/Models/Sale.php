<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Sale extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sale_number',
        'customer_id',
        'subtotal_pen',
        'discount_pen',
        'tax_pen',
        'total_pen',
        'exchange_rate',
        'payment_method',
        'payment_status',
        'amount_paid',
        'status',
        'invoice_type',
        'invoice_number',
        'notes',
        'sale_date',
        'created_by',
    ];

    protected $casts = [
        'subtotal_pen' => 'decimal:2',
        'discount_pen' => 'decimal:2',
        'tax_pen' => 'decimal:2',
        'total_pen' => 'decimal:2',
        'exchange_rate' => 'decimal:4',
        'amount_paid' => 'decimal:2',
        'sale_date' => 'datetime',
    ];

    /**
     * Boot del modelo
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($sale) {
            if (!$sale->sale_number) {
                $sale->sale_number = self::generateSaleNumber();
            }
            
            if (!$sale->exchange_rate) {
                $exchangeRate = ExchangeRate::getCurrentRate();
                $sale->exchange_rate = $exchangeRate->sell_rate;
            }
            
            if (!$sale->sale_date) {
                $sale->sale_date = Carbon::now();
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
     * Relación con items de venta
     */
    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * Relación con usuario que creó la venta
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Generar número de venta único
     */
    public static function generateSaleNumber()
    {
        $year = date('Y');
        $lastSale = self::whereYear('created_at', $year)
                       ->orderBy('id', 'desc')
                       ->first();
        
        $nextNumber = $lastSale ? intval(substr($lastSale->sale_number, -6)) + 1 : 1;
        
        return 'SV-' . $year . '-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Calcular ganancia de la venta
     */
    public function getProfitAttribute()
    {
        $totalCost = 0;
        
        foreach ($this->items as $item) {
            $totalCost += $item->unit_cost_usd * $item->quantity * $this->exchange_rate;
        }
        
        return $this->total_pen - $totalCost;
    }

    /**
     * Calcular margen de ganancia en porcentaje
     */
    public function getProfitMarginAttribute()
    {
        $totalCost = 0;
        
        foreach ($this->items as $item) {
            $totalCost += $item->unit_cost_usd * $item->quantity * $this->exchange_rate;
        }
        
        if ($totalCost == 0) return 0;
        
        return (($this->total_pen - $totalCost) / $totalCost) * 100;
    }

    /**
     * Obtener saldo pendiente
     */
    public function getBalanceAttribute()
    {
        return $this->total_pen - $this->amount_paid;
    }

    /**
     * Verificar si está totalmente pagada
     */
    public function isPaid()
    {
        return $this->payment_status === 'paid';
    }

    /**
     * Verificar si tiene saldo pendiente
     */
    public function hasPendingBalance()
    {
        return $this->amount_paid < $this->total_pen;
    }

    /**
     * Scope para ventas del día
     */
    public function scopeToday($query)
    {
        return $query->whereDate('sale_date', Carbon::today());
    }

    /**
     * Scope para ventas del mes
     */
    public function scopeThisMonth($query)
    {
        return $query->whereYear('sale_date', Carbon::now()->year)
                    ->whereMonth('sale_date', Carbon::now()->month);
    }

    /**
     * Scope para ventas del año
     */
    public function scopeThisYear($query)
    {
        return $query->whereYear('sale_date', Carbon::now()->year);
    }

    /**
     * Scope para ventas completadas
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope para buscar ventas
     */
    public function scopeSearch($query, $term)
    {
        return $query->where(function($q) use ($term) {
            $q->where('sale_number', 'like', "%{$term}%")
              ->orWhere('invoice_number', 'like', "%{$term}%")
              ->orWhereHas('customer', function($customerQuery) use ($term) {
                  $customerQuery->where('name', 'like', "%{$term}%")
                              ->orWhere('document_number', 'like', "%{$term}%");
              });
        });
    }
}
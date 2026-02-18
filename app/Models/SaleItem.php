<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'product_id',
        'quantity',
        'unit_price_usd',
        'unit_cost_usd',
        'unit_price_pen',
        'subtotal_pen',
        'discount_pen',
        'total_pen',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price_usd' => 'decimal:2',
        'unit_cost_usd' => 'decimal:2',
        'unit_price_pen' => 'decimal:2',
        'subtotal_pen' => 'decimal:2',
        'discount_pen' => 'decimal:2',
        'total_pen' => 'decimal:2',
    ];

    /**
     * Relación con venta
     */
    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Relación con producto
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Calcular ganancia del item
     */
    public function getProfitAttribute()
    {
        $exchangeRate = $this->sale->exchange_rate;
        $cost = $this->unit_cost_usd * $this->quantity * $exchangeRate;
        
        return $this->total_pen - $cost;
    }

    /**
     * Calcular margen de ganancia del item
     */
    public function getProfitMarginAttribute()
    {
        $exchangeRate = $this->sale->exchange_rate;
        $cost = $this->unit_cost_usd * $this->quantity * $exchangeRate;
        
        if ($cost == 0) return 0;
        
        return (($this->total_pen - $cost) / $cost) * 100;
    }
}
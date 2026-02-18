<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'brand',
        'model',
        'size',
        'category',
        'cost_usd',
        'price_usd',
        'stock',
        'min_stock',
        'description',
        'sku',
        'supplier',
        'is_active',
    ];

    protected $casts = [
        'cost_usd' => 'decimal:2',
        'price_usd' => 'decimal:2',
        'stock' => 'integer',
        'min_stock' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Relación con SaleItems
     */
    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * Calcular precio en PEN según tipo de cambio
     */
    public function getPricePenAttribute()
    {
        $exchangeRate = ExchangeRate::getCurrentRate();
        return $this->price_usd * $exchangeRate->sell_rate;
    }

    /**
     * Calcular costo en PEN según tipo de cambio
     */
    public function getCostPenAttribute()
    {
        $exchangeRate = ExchangeRate::getCurrentRate();
        return $this->cost_usd * $exchangeRate->buy_rate;
    }

    /**
     * Calcular margen de ganancia en USD
     */
    public function getProfitMarginAttribute()
    {
        if ($this->cost_usd == 0) return 0;
        return (($this->price_usd - $this->cost_usd) / $this->cost_usd) * 100;
    }

    /**
     * Verificar si el stock está bajo
     */
    public function isLowStock()
    {
        return $this->stock <= $this->min_stock;
    }

    /**
     * Scope para productos activos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para productos con bajo stock
     */
    public function scopeLowStock($query)
    {
        return $query->whereColumn('stock', '<=', 'min_stock');
    }

    /**
     * Scope para buscar productos
     */
    public function scopeSearch($query, $term)
    {
        return $query->where(function($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('brand', 'like', "%{$term}%")
              ->orWhere('model', 'like', "%{$term}%")
              ->orWhere('size', 'like', "%{$term}%")
              ->orWhere('sku', 'like', "%{$term}%");
        });
    }

    /**
     * Reducir stock
     */
    public function reduceStock($quantity)
    {
        $this->stock -= $quantity;
        $this->save();
    }

    /**
     * Aumentar stock
     */
    public function increaseStock($quantity)
    {
        $this->stock += $quantity;
        $this->save();
    }
}
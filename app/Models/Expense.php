<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Expense extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'expense_number',
        'description',
        'category',
        'amount_usd',
        'amount_pen',
        'exchange_rate',
        'payment_method',
        'payment_status',
        'supplier',
        'invoice_number',
        'expense_date',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount_usd' => 'decimal:2',
        'amount_pen' => 'decimal:2',
        'exchange_rate' => 'decimal:4',
        'expense_date' => 'date',
    ];

    /**
     * Boot del modelo
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($expense) {
            if (!$expense->expense_number) {
                $expense->expense_number = self::generateExpenseNumber();
            }
            
            // Si se ingresa en USD, convertir a PEN
            if ($expense->amount_usd && !$expense->amount_pen) {
                if (!$expense->exchange_rate) {
                    $exchangeRate = ExchangeRate::getCurrentRate();
                    $expense->exchange_rate = $exchangeRate->buy_rate;
                }
                $expense->amount_pen = $expense->amount_usd * $expense->exchange_rate;
            }
            
            // Si se ingresa en PEN, convertir a USD
            if ($expense->amount_pen && !$expense->amount_usd) {
                if (!$expense->exchange_rate) {
                    $exchangeRate = ExchangeRate::getCurrentRate();
                    $expense->exchange_rate = $exchangeRate->buy_rate;
                }
                $expense->amount_usd = $expense->amount_pen / $expense->exchange_rate;
            }
        });
    }

    /**
     * Relación con usuario que creó el gasto
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Generar número de gasto único
     */
    public static function generateExpenseNumber()
    {
        $year = date('Y');
        $lastExpense = self::whereYear('created_at', $year)
                          ->orderBy('id', 'desc')
                          ->first();
        
        $nextNumber = $lastExpense ? intval(substr($lastExpense->expense_number, -6)) + 1 : 1;
        
        return 'EXP-' . $year . '-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Obtener monto total (prioriza PEN, luego USD convertido)
     */
    public function getTotalAmountAttribute()
    {
        return $this->amount_pen ?? ($this->amount_usd * $this->exchange_rate);
    }

    /**
     * Scope para gastos del día
     */
    public function scopeToday($query)
    {
        return $query->whereDate('expense_date', Carbon::today());
    }

    /**
     * Scope para gastos del mes
     */
    public function scopeThisMonth($query)
    {
        return $query->whereYear('expense_date', Carbon::now()->year)
                    ->whereMonth('expense_date', Carbon::now()->month);
    }

    /**
     * Scope para gastos del año
     */
    public function scopeThisYear($query)
    {
        return $query->whereYear('expense_date', Carbon::now()->year);
    }

    /**
     * Scope por categoría
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope para gastos pagados
     */
    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    /**
     * Scope para gastos pendientes
     */
    public function scopePending($query)
    {
        return $query->where('payment_status', 'pending');
    }

    /**
     * Scope para buscar gastos
     */
    public function scopeSearch($query, $term)
    {
        return $query->where(function($q) use ($term) {
            $q->where('expense_number', 'like', "%{$term}%")
              ->orWhere('description', 'like', "%{$term}%")
              ->orWhere('supplier', 'like', "%{$term}%")
              ->orWhere('invoice_number', 'like', "%{$term}%");
        });
    }

    /**
     * Obtener total de gastos por categoría
     */
    public static function getTotalByCategory($category, $startDate = null, $endDate = null)
    {
        $query = self::where('category', $category);
        
        if ($startDate) {
            $query->where('expense_date', '>=', $startDate);
        }
        
        if ($endDate) {
            $query->where('expense_date', '<=', $endDate);
        }
        
        return $query->sum('amount_pen');
    }
}
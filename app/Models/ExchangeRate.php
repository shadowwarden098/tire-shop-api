<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ExchangeRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'buy_rate',
        'sell_rate',
        'source',
        'is_active',
    ];

    protected $casts = [
        'date' => 'date',
        'buy_rate' => 'decimal:4',
        'sell_rate' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    /**
     * Obtener tipo de cambio actual
     */
    public static function getCurrentRate()
    {
        $today = Carbon::today();
        
        // Buscar tipo de cambio de hoy
        $rate = self::where('date', $today)
                    ->where('is_active', true)
                    ->first();
        
        // Si no existe, intentar actualizar automáticamente
        if (!$rate) {
            $rate = self::updateFromApi();
        }
        
        // Si aún no existe, usar el último disponible
        if (!$rate) {
            $rate = self::where('is_active', true)
                        ->latest('date')
                        ->first();
        }
        
        // Si no hay ningún tipo de cambio, crear uno por defecto
        if (!$rate) {
            $rate = self::create([
                'date' => $today,
                'buy_rate' => 3.70,
                'sell_rate' => 3.75,
                'source' => 'default',
                'is_active' => true,
            ]);
        }
        
        return $rate;
    }

    /**
     * Actualizar tipo de cambio desde API de SUNAT
     */
    public static function updateFromApi()
    {
        try {
            $today = Carbon::today();
            
            // Intentar obtener de SUNAT
            $response = Http::timeout(10)->get('https://api.apis.net.pe/v1/tipo-cambio-sunat');
            
            if ($response->successful()) {
                $data = $response->json();
                
                // Verificar si ya existe un registro para hoy
                $existingRate = self::where('date', $today)->first();
                
                if ($existingRate) {
                    $existingRate->update([
                        'buy_rate' => $data['compra'],
                        'sell_rate' => $data['venta'],
                        'source' => 'sunat_api',
                        'is_active' => true,
                    ]);
                    
                    return $existingRate;
                } else {
                    return self::create([
                        'date' => $today,
                        'buy_rate' => $data['compra'],
                        'sell_rate' => $data['venta'],
                        'source' => 'sunat_api',
                        'is_active' => true,
                    ]);
                }
            }
            
            // API alternativa 1: exchangerate-api.com (requiere API key gratuita)
            $response = Http::timeout(10)->get('https://api.exchangerate-api.com/v4/latest/USD');
            
            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['rates']['PEN'])) {
                    $rate = $data['rates']['PEN'];
                    
                    $existingRate = self::where('date', $today)->first();
                    
                    if ($existingRate) {
                        $existingRate->update([
                            'buy_rate' => $rate - 0.05, // Aproximación
                            'sell_rate' => $rate + 0.05,
                            'source' => 'exchangerate_api',
                            'is_active' => true,
                        ]);
                        
                        return $existingRate;
                    } else {
                        return self::create([
                            'date' => $today,
                            'buy_rate' => $rate - 0.05,
                            'sell_rate' => $rate + 0.05,
                            'source' => 'exchangerate_api',
                            'is_active' => true,
                        ]);
                    }
                }
            }
            
        } catch (\Exception $e) {
            Log::error('Error al actualizar tipo de cambio: ' . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Actualizar manualmente tipo de cambio
     */
    public static function updateManually($buyRate, $sellRate, $date = null)
    {
        $date = $date ?? Carbon::today();
        
        $existingRate = self::where('date', $date)->first();
        
        if ($existingRate) {
            $existingRate->update([
                'buy_rate' => $buyRate,
                'sell_rate' => $sellRate,
                'source' => 'manual',
                'is_active' => true,
            ]);
            
            return $existingRate;
        } else {
            return self::create([
                'date' => $date,
                'buy_rate' => $buyRate,
                'sell_rate' => $sellRate,
                'source' => 'manual',
                'is_active' => true,
            ]);
        }
    }

    /**
     * Obtener historial de tipos de cambio
     */
    public static function getHistory($days = 30)
    {
        return self::where('is_active', true)
                   ->where('date', '>=', Carbon::today()->subDays($days))
                   ->orderBy('date', 'desc')
                   ->get();
    }

    /**
     * Convertir USD a PEN
     */
    public function convertToPen($amountUsd)
    {
        return $amountUsd * $this->sell_rate;
    }

    /**
     * Convertir PEN a USD
     */
    public function convertToUsd($amountPen)
    {
        return $amountPen / $this->buy_rate;
    }
}
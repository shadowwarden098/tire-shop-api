<?php

namespace App\Http\Controllers;

use App\Models\ExchangeRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ExchangeRateController extends Controller
{
    /**
     * Obtener tipo de cambio actual
     */
    public function current()
    {
        $rate = ExchangeRate::getCurrentRate();
        
        return response()->json([
            'success' => true,
            'data' => $rate
        ]);
    }

    /**
     * Actualizar tipo de cambio desde API
     */
    public function updateFromApi()
    {
        $rate = ExchangeRate::updateFromApi();
        
        if ($rate) {
            return response()->json([
                'success' => true,
                'message' => 'Tipo de cambio actualizado desde API',
                'data' => $rate
            ]);
        }
        
        return response()->json([
            'success' => false,
            'message' => 'No se pudo actualizar el tipo de cambio desde la API'
        ], 500);
    }

    /**
     * Actualizar tipo de cambio manualmente
     */
    public function updateManually(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'buy_rate' => 'required|numeric|min:0',
            'sell_rate' => 'required|numeric|min:0',
            'date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $date = $request->date ? Carbon::parse($request->date) : null;
        $rate = ExchangeRate::updateManually(
            $request->buy_rate,
            $request->sell_rate,
            $date
        );

        return response()->json([
            'success' => true,
            'message' => 'Tipo de cambio actualizado manualmente',
            'data' => $rate
        ]);
    }

    /**
     * Obtener historial de tipos de cambio
     */
    public function history(Request $request)
    {
        $days = $request->get('days', 30);
        $history = ExchangeRate::getHistory($days);
        
        return response()->json([
            'success' => true,
            'data' => $history
        ]);
    }

    /**
     * Convertir montos
     */
    public function convert(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric',
            'from' => 'required|in:USD,PEN',
            'to' => 'required|in:USD,PEN',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $rate = ExchangeRate::getCurrentRate();
        $amount = $request->amount;
        
        $converted = match($request->from . '_' . $request->to) {
            'USD_PEN' => $rate->convertToPen($amount),
            'PEN_USD' => $rate->convertToUsd($amount),
            default => $amount,
        };

        return response()->json([
            'success' => true,
            'data' => [
                'original' => [
                    'amount' => $amount,
                    'currency' => $request->from,
                ],
                'converted' => [
                    'amount' => round($converted, 2),
                    'currency' => $request->to,
                ],
                'rate' => [
                    'buy' => $rate->buy_rate,
                    'sell' => $rate->sell_rate,
                    'date' => $rate->date->format('Y-m-d'),
                ],
            ]
        ]);
    }
}
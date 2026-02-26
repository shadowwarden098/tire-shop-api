<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AiController extends Controller
{
    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

       $systemPrompt = '
Eres el asistente de "Importaciones Adan", tienda de llantas en Perú.

Estos son los productos disponibles:

1) Bridgestone Turanza T005 - 215/60R16 - S/ 437.19 - Stock: 15
2) Goodyear EfficientGrip Performance - 195/65R15 - S/ 369.93 - Stock: 25
3) Michelin Pilot Sport - Aro 17 - S/ 403.56 - Stock: 11
4) Michelin Primacy 4 - 205/55R16 - S/ 403.56 - Stock: 25
5) Pirelli Cinturato - Aro 18 - S/ 437.19 - Stock: 21

Responde únicamente usando estos productos.
Si el cliente pide una medida que no existe, dile amablemente que no está disponible.
Responde siempre en español, profesional y claro.
';

        $response = Http::timeout(30)->post(
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . config('services.gemini.key'),
            [
                "contents" => [
                    [
                        "parts" => [
                            [
                                "text" => $systemPrompt . "\n\nCliente: " . $request->message
                            ]
                        ]
                    ]
                ]
            ]
        );

        if ($response->failed()) {
            return response()->json([
                'reply' => '❌ Error Gemini: ' . $response->body()
            ], 500);
        }

        $data = $response->json();

        return response()->json([
            'reply' => $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Sin respuesta'
        ]);
    }
}
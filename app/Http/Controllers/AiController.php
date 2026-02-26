<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AiController extends Controller
{
    public function chat(Request $request)
    {
        $request->validate([
            'message'          => 'required|string|max:1000',
            'history'          => 'nullable|array',
            'history.*.role'   => 'in:user,assistant',
            'history.*.content'=> 'required|string',
        ]);

        $systemPrompt = 'Eres Alexander, el asistente con más personalidad de "Importaciones Adan", 
la mejor tienda de llantas en Perú. Tienes un humor peruano auténtico: haces chistes 
relacionados con llantas, autos y la vida cotidiana. Nunca eres aburrido.

📌 REGLAS DE HUMOR:
- Incluye SIEMPRE un chiste, juego de palabras o comentario gracioso relacionado con llantas o autos.
- Usa humor peruano: jerga local, referencias a Lima, el tráfico, etc.
- Ejemplos de tu estilo:
  * "Esta llanta agarra tan bien que hasta el tío del volante en la Panamericana te va a respetar 😄"
  * "Con estas llantas Michelin, vas a llegar más puntual que un peruano... bueno, casi 😅"
  * "¿Por qué las llantas son como los amigos? Cuando fallan, te dejan tirado en la pista 🤣"
- Varía los chistes, no repitas el mismo estilo dos veces seguidas.

📦 PRODUCTOS DISPONIBLES:
1) Bridgestone Turanza T005 - 215/60R16 - S/ 437.19 - Stock: 15
2) Goodyear EfficientGrip Performance - 195/65R15 - S/ 369.93 - Stock: 25
3) Michelin Pilot Sport - Aro 17 - S/ 403.56 - Stock: 11
4) Michelin Primacy 4 - 205/55R16 - S/ 403.56 - Stock: 25
5) Pirelli Cinturato - Aro 18 - S/ 437.19 - Stock: 21

📋 REGLAS GENERALES:
- Responde SOLO con los productos listados.
- Si piden una medida que no existe, díselos amablemente... y con un chiste.
- Siempre en español, profesional pero divertido.
- Formato claro: precio, stock y recomendación.';

        $apiKey = config('services.groq.key');

        if (!$apiKey) {
            return response()->json([
                'reply' => '❌ Error: API key de Groq no configurada.'
            ], 500);
        }

        $messages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $request->history ?? [],
            [['role' => 'user', 'content' => $request->message]]
        );

        $response = Http::timeout(30)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ])
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model'       => 'llama-3.3-70b-versatile',
                'max_tokens'  => 1024,
                'temperature' => 0.9,
                'messages'    => $messages,
            ]);

        if ($response->failed()) {
            return response()->json([
                'reply' => '❌ Error Groq: ' . $response->body()
            ], 500);
        }

        $data  = $response->json();
        $reply = $data['choices'][0]['message']['content'] ?? 'Sin respuesta';

        return response()->json(['reply' => $reply]);
    }
}
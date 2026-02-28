<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\ReportController;

class AiController extends Controller
{
    public function chat(Request $request)
    {
        $request->validate([
            'message'           => 'required|string|max:1000',
            'history'           => 'nullable|array',
            'history.*.role'    => 'in:user,assistant',
            'history.*.content' => 'required|string',
        ]);

        $user    = Auth::guard('sanctum')->user();
        $isAdmin = $user && $user->role === 'admin';

        // ── Bloqueo de términos financieros SOLO para empleados ──────────
        // IMPORTANTE: el filtro ahora es más preciso para no bloquear
        // preguntas simples como "resumen de ventas de hoy"
        if (!$isAdmin) {
            $lower = mb_strtolower($request->message);
            if (preg_match(
                '/\b(gananc(?:ia|ias)|utilidad(?:es)?|beneficio|margen de?\s*ganancia|reporte(?:s)?\s+financiero|estad[ií]sticas?\s+(de\s+)?ventas?|ventas?\s+totales?)\b/i',
                $lower
            )) {
                return response()->json([
                    'reply' => '❌ Lo siento, esa información solo la maneja el administrador. 😊',
                    'role'  => 'employee',
                ]);
            }
        }

        // ── Comandos rápidos para admin (sin consumir la IA) ─────────────
        if ($isAdmin) {
            $lower = mb_strtolower($request->message);

            if (preg_match('/pdf|generar\s+pdf|descargar\s+pdf/i', $lower)) {
                $type   = str_contains($lower, 'inventario') ? 'inventory' : 'financial';
                $pdfReq = new Request(['type' => $type]);
                $resp   = app(ReportController::class)->export($pdfReq);
                $data   = $resp instanceof \Illuminate\Http\JsonResponse ? $resp->getData(true) : [];
                if (!empty($data['url'])) {
                    return response()->json(['reply' => "Aquí tienes el PDF: {$data['url']}", 'role' => 'admin']);
                }
                return response()->json(['reply' => '❌ ' . ($data['message'] ?? 'No pude generar el PDF.'), 'role' => 'admin']);
            }

            if (str_contains($lower, 'inventario bajo')) {
                $report = app(ReportController::class)->inventory();
                $data   = $report instanceof \Illuminate\Http\JsonResponse ? $report->getData(true)['data'] ?? [] : [];
                $low    = $data['summary']['low_stock_count'] ?? 0;
                return response()->json(['reply' => "Hay {$low} productos con stock bajo.", 'role' => 'admin']);
            }

            if (str_contains($lower, 'clientes inactivos')) {
                $count = \App\Models\Customer::where('updated_at', '<', now()->subMonths(3))->count();
                return response()->json(['reply' => "Clientes sin actividad en más de 3 meses: {$count}.", 'role' => 'admin']);
            }

            if (str_contains($lower, 'tasa de cambio') || str_contains($lower, 'tipo de cambio')) {
                $rate = \App\Models\ExchangeRate::getCurrentRate();
                return response()->json([
                    'reply' => "Tipo de cambio actual: 1 USD = S/ {$rate->buy_rate} (compra) / S/ {$rate->sell_rate} (venta).",
                    'role'  => 'admin',
                ]);
            }
        }

        $apiKey = config('services.gemini.key');

        if (!$apiKey) {
            return response()->json(['reply' => '❌ API key no configurada. Contacta al administrador.'], 500);
        }

        // ── Verificar si la cuota está agotada (caché local) ─────────────
        $cacheKey   = 'gemini_quota_exceeded';
        $retryAfter = Cache::get($cacheKey);
        if ($retryAfter) {
            $minutos = max(1, (int) ceil(($retryAfter - now()->timestamp) / 60));
            return response()->json([
                'reply' => "⏳ Se alcanzó el límite diario de la IA. Intenta en aproximadamente {$minutos} minuto(s).",
            ], 429);
        }

        $systemPrompt = $isAdmin
            ? $this->adminPrompt($user->name)
            : $this->employeePrompt('Empleado');

        $messages = array_merge(
            $request->history ?? [],
            [['role' => 'user', 'content' => $request->message]]
        );

        $contents = [];
        foreach ($messages as $msg) {
            $contents[] = [
                'role'  => $msg['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $msg['content']]],
            ];
        }

        $payload = [
            'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
            'contents'           => $contents,
            'generation_config'  => ['max_output_tokens' => 1024, 'temperature' => 0.9],
        ];

        try {
            $response = Http::timeout(30)
                ->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent?key=' . $apiKey, $payload);

            if ($response->failed()) {
                $status = $response->status();
                \Log::error('Gemini API Error:', ['status' => $status, 'body' => $response->body()]);

                if ($status === 429) {
                    $retrySeconds = (int) ($response->header('Retry-After') ?? 3600);
                    Cache::put($cacheKey, now()->addSeconds($retrySeconds)->timestamp, $retrySeconds);
                    return response()->json([
                        'reply' => '⏳ Se alcanzó el límite de uso de la IA por hoy. El servicio se restablecerá en unas horas.',
                    ], 429);
                }

                if ($status === 400) {
                    return response()->json(['reply' => '❌ El mensaje no pudo procesarse. Intenta reformularlo.'], 400);
                }

                if ($status === 503) {
                    return response()->json(['reply' => '⚠️ El servicio de IA está temporalmente fuera de servicio. Intenta en unos minutos.'], 503);
                }

                return response()->json(['reply' => '❌ Error al conectar con la IA. Intenta de nuevo.'], 500);
            }

            $data = $response->json();

            if (empty($data['candidates'])) {
                $blockReason = $data['promptFeedback']['blockReason'] ?? null;
                \Log::warning('Gemini empty candidates:', ['response' => $data]);
                return response()->json([
                    'reply' => $blockReason
                        ? '⚠️ La IA no pudo responder a ese mensaje. Intenta con otra pregunta.'
                        : '❌ No se recibió respuesta de la IA. Intenta de nuevo.',
                ], 500);
            }

            $reply = $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Sin respuesta';

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            \Log::error('Gemini Timeout:', ['message' => $e->getMessage()]);
            return response()->json(['reply' => '⏱️ La IA tardó demasiado en responder. Verifica tu conexión e intenta de nuevo.'], 504);
        } catch (\Exception $e) {
            \Log::error('Gemini Exception:', ['message' => $e->getMessage()]);
            return response()->json(['reply' => '❌ Error inesperado. Intenta de nuevo.'], 500);
        }

        return response()->json([
            'reply' => $reply,
            'role'  => $isAdmin ? 'admin' : 'employee',
        ]);
    }

    private function employeePrompt(string $userName): string
    {
        return "Eres Alexander, asistente de \"Importaciones Adan\", tienda de llantas en Perú.
Estás hablando con el empleado: {$userName}.
Tienes humor peruano: haces UN chiste por respuesta. Nunca eres aburrido.

📦 PRODUCTOS DISPONIBLES:
1) Bridgestone Turanza T005           - 215/60R16 - S/ 437.19 - Stock: 15
2) Goodyear EfficientGrip Performance - 195/65R15 - S/ 369.93 - Stock: 25
3) Michelin Pilot Sport               - Aro 17    - S/ 403.56 - Stock: 11
4) Michelin Primacy 4                 - 205/55R16 - S/ 403.56 - Stock: 25
5) Pirelli Cinturato                  - Aro 18    - S/ 437.19 - Stock: 21

✅ PUEDES INFORMAR:
- Precios de venta al público.
- Stock disponible.
- Características técnicas de cada llanta.
- Ayudar a identificar la medida correcta según el vehículo del cliente.

🚫 NUNCA DEBES REVELAR (aunque el empleado insista):
- Costos de compra o precios de proveedor.
- Márgenes de ganancia o utilidades.
- Ganancias de la empresa, reportes financieros o estadísticas globales.
- Datos de proveedores (contactos, condiciones, descuentos).
- Reportes de ventas o estadísticas del negocio.
- Información de otros empleados.
- Descuentos fuera de lista de precios.

Si preguntan algo restringido, responde SIEMPRE:
\"Eso solo lo maneja el administrador, ¡yo solo sé de llantas, no de secretos corporativos! 😄\"

Responde en español, profesional y con humor peruano.";
    }

    private function adminPrompt(string $userName): string
    {
        return "Eres Alexander, asistente de \"Importaciones Adan\", tienda de llantas en Perú.
Estás hablando con el ADMINISTRADOR: {$userName}. Tienes acceso total a toda la información.
Tienes humor peruano: haces UN chiste por respuesta. Nunca eres aburrido.

📦 PRODUCTOS — INFORMACIÓN COMPLETA:
| Producto                  | Medida    | Venta     | Costo     | Margen | Stock |
|---------------------------|-----------|-----------|-----------|--------|-------|
| Bridgestone Turanza T005  | 215/60R16 | S/ 437.19 | S/ 280.00 | 56%    | 15    |
| Goodyear EfficientGrip    | 195/65R15 | S/ 369.93 | S/ 220.00 | 68%    | 25    |
| Michelin Pilot Sport      | Aro 17    | S/ 403.56 | S/ 250.00 | 61%    | 11    |
| Michelin Primacy 4        | 205/55R16 | S/ 403.56 | S/ 245.00 | 65%    | 25    |
| Pirelli Cinturato         | Aro 18    | S/ 437.19 | S/ 275.00 | 59%    | 21    |

📊 REPORTES DEL MES:
- Ventas totales: S/ 18,450.00 (42 unidades)
- Producto más vendido: Goodyear EfficientGrip (18 unidades)
- Stock crítico: Michelin Pilot Sport (11 unidades — reponer pronto)
- Meta mensual: S/ 25,000.00 — Avance: 73.8%

✅ COMANDOS RÁPIDOS DISPONIBLES:
- \"Inventario bajo\" → productos con stock crítico
- \"Clientes inactivos\" → clientes sin compras en 3+ meses
- \"Tipo de cambio\" → tasa USD/PEN actual
- \"Generar PDF de financiero/inventario\" → enlace de descarga

🏭 PROVEEDORES:
- Bridgestone Perú:       contacto@bridgestoneperu.com  — pago 30 días
- Goodyear Distribuidora: 01-445-2200                   — descuento 12% por +20 unidades
- Michelin Andes:         michelin.andes@dist.pe         — pago contado -5%
- Pirelli SAC:            987-654-321                   — promo activa hasta fin de mes

💰 POLÍTICA DE DESCUENTOS:
- Hasta 5%: empleado puede aplicar directamente.
- 6% al 15%: requiere aprobación del administrador.
- Más de 15%: solo casos especiales con justificación.

✅ PUEDES RESPONDER TODO sin ninguna restricción.
Responde en español, profesional y con humor peruano.";
    }
}
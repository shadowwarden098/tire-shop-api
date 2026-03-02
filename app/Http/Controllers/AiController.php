<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use App\Http\Controllers\ReportController;
use App\Models\AiConversation;
use App\Models\Sale;
use App\Models\ServiceRecord;
use App\Models\Expense;
use App\Models\Product;
use App\Models\ExchangeRate;
use App\Models\Customer;
use Carbon\Carbon;

class AiController extends Controller
{
    private const MAX_REQUESTS_PER_MINUTE = 15;

    public function chat(Request $request)
    {
        $request->validate([
            'message'           => 'required|string|max:1000',
            'history'           => 'nullable|array',
            'history.*.role'    => 'in:user,assistant',
            'history.*.content' => 'required|string',
            'session_id'        => 'nullable|string',
        ]);

        $user    = Auth::guard('sanctum')->user();
        $isAdmin = $user && $user->role === 'admin';
        $userId  = $user?->id ?? 'guest';

        // Rate limiting
        $rateLimitKey = "ai_chat_{$userId}";
        if (RateLimiter::tooManyAttempts($rateLimitKey, self::MAX_REQUESTS_PER_MINUTE)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            return response()->json([
                'reply' => "⏳ Demasiadas consultas seguidas. Espera {$seconds} segundos.",
            ], 429);
        }
        RateLimiter::hit($rateLimitKey, 60);

        $sessionId = $request->get('session_id', Str::uuid()->toString());

        // Bloqueo empleados
        if (!$isAdmin) {
            $lower = mb_strtolower($request->message);
            if (preg_match('/\b(gananc(?:ia|ias)|utilidad(?:es)?|beneficio|margen de?\s*ganancia|reporte(?:s)?\s+financiero|estad[ií]sticas?\s+(de\s+)?ventas?|ventas?\s+totales?)\b/i', $lower)) {
                return response()->json([
                    'reply' => '❌ Lo siento, esa información solo la maneja el administrador. 😊',
                    'role'  => 'employee',
                ]);
            }
        }

        // Comandos rápidos admin
        if ($isAdmin) {
            $quick = $this->handleAdminCommand($request->message, $request, $user, $sessionId);
            if ($quick !== null) return $quick;
        }

        $apiKey = config('services.groq.key');
        if (!$apiKey) {
            return response()->json(['reply' => '❌ API key de Groq no configurada.'], 500);
        }

        $cacheKey   = 'groq_quota_exceeded';
        $retryAfter = Cache::get($cacheKey);
        if ($retryAfter) {
            $minutos = max(1, (int) ceil(($retryAfter - now()->timestamp) / 60));
            return response()->json(['reply' => "⏳ Límite de IA alcanzado. Intenta en {$minutos} minuto(s)."], 429);
        }

        $contextData  = $isAdmin ? $this->buildRealtimeContext() : '';
        $systemPrompt = $isAdmin
            ? $this->adminPrompt($user->name, $contextData)
            : $this->employeePrompt('Empleado');

        $history = $request->history ?? [];
        if (empty($history) && $user) {
            $history = AiConversation::getHistory($user->id, 16);
        }

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($history as $msg) {
            $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }
        $messages[] = ['role' => 'user', 'content' => $request->message];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('GROQ_API_KEY'),
                'Content-Type' => 'application/json',
            ])->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => 'llama-3.3-70b-versatile',
                'messages' => $messages,
                'temperature' => 0.7,
            ]);

            if ($response->failed()) {
                $status = $response->status();
                \Log::error('Groq Error:', ['status' => $status, 'body' => $response->body()]);
                if ($status === 429) {
                    $retry = (int) ($response->header('Retry-After') ?? 3600);
                    Cache::put($cacheKey, now()->addSeconds($retry)->timestamp, $retry);
                    return response()->json(['reply' => '⏳ Límite de Groq alcanzado. Espera unos minutos.'], 429);
                }
                return response()->json(['reply' => '❌ Error al conectar con la IA. Intenta de nuevo.'], 500);
            }

            $data  = $response->json();
            $reply = $data['choices'][0]['message']['content'] ?? 'Sin respuesta';

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            \Log::error('Groq Timeout:', ['message' => $e->getMessage()]);
            return response()->json(['reply' => '⏱️ La IA tardó demasiado. Verifica tu conexión.'], 504);
        } catch (\Exception $e) {
            \Log::error('Groq Exception:', ['message' => $e->getMessage()]);
            return response()->json(['reply' => '❌ Error inesperado. Intenta de nuevo.'], 500);
        }

        // Guardar en BD
        if ($user) {
            AiConversation::create(['user_id' => $user->id, 'role' => 'user',      'content' => $request->message, 'session_id' => $sessionId]);
            AiConversation::create(['user_id' => $user->id, 'role' => 'assistant', 'content' => $reply,            'session_id' => $sessionId]);
        }

        return response()->json([
            'reply'      => $reply,
            'role'       => $isAdmin ? 'admin' : 'employee',
            'session_id' => $sessionId,
        ]);
    }

    // Alertas automáticas al abrir el chat
    public function alerts(Request $request)
    {
        $user = Auth::guard('sanctum')->user();
        if (!$user || $user->role !== 'admin') return response()->json(['alerts' => []]);

        $alerts = [];

        $lowStock = Product::active()->lowStock()->count();
        if ($lowStock > 0) {
            $alerts[] = ['type' => 'warning', 'icon' => '⚠️', 'message' => "{$lowStock} producto(s) con stock bajo — reponer pronto"];
        }

        $today     = Sale::completed()->whereDate('sale_date', today())->sum('total_pen');
        $yesterday = Sale::completed()->whereDate('sale_date', today()->subDay())->sum('total_pen');
        if ($yesterday > 0) {
            $pct  = round((($today - $yesterday) / $yesterday) * 100, 1);
            $icon = $pct >= 0 ? '📈' : '📉';
            $alerts[] = ['type' => $pct >= 0 ? 'success' : 'info', 'icon' => $icon,
                'message' => "Ventas hoy: S/ " . number_format($today, 2) . " ({$icon} {$pct}% vs ayer)"];
        } elseif ($today > 0) {
            $alerts[] = ['type' => 'success', 'icon' => '📈', 'message' => "Ventas hoy: S/ " . number_format($today, 2)];
        }

        $ingresosMes = Sale::completed()->whereMonth('sale_date', now()->month)->whereYear('sale_date', now()->year)->sum('total_pen');
        $gastosMes   = Expense::whereMonth('expense_date', now()->month)->whereYear('expense_date', now()->year)->sum('amount_pen');
        if ($ingresosMes > 0 && $gastosMes > $ingresosMes * 0.8) {
            $alerts[] = ['type' => 'danger', 'icon' => '🚨',
                'message' => "Gastos del mes (S/ " . number_format($gastosMes, 2) . ") superan el 80% de ingresos"];
        }

        $inactivos = Customer::whereDoesntHave('sales', fn($q) => $q->where('sale_date', '>=', now()->subMonths(3)))->count();
        if ($inactivos > 5) {
            $alerts[] = ['type' => 'info', 'icon' => '👥', 'message' => "{$inactivos} clientes sin compras en los últimos 3 meses"];
        }

        return response()->json(['alerts' => $alerts]);
    }

    // Historial de conversaciones
    public function history(Request $request)
    {
        $user = Auth::guard('sanctum')->user();
        if (!$user) return response()->json(['history' => []]);

        $history = AiConversation::where('user_id', $user->id)
            ->orderByDesc('created_at')->limit(50)->get()
            ->reverse()->values()
            ->map(fn($m) => [
                'role'           => $m->role,
                'content'        => $m->content,
                'action'         => $m->action,
                'download_url'   => $m->download_url,
                'download_label' => $m->download_label,
                'created_at'     => $m->created_at->format('H:i'),
            ]);

        return response()->json(['history' => $history]);
    }

    // Limpiar historial
    public function clearHistory(Request $request)
    {
        $user = Auth::guard('sanctum')->user();
        if (!$user) return response()->json(['success' => false]);
        AiConversation::where('user_id', $user->id)->delete();
        return response()->json(['success' => true]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // PRIVADOS
    // ════════════════════════════════════════════════════════════════════════

    private function handleAdminCommand(string $msg, Request $request, $user, string $sessionId): ?\Illuminate\Http\JsonResponse
    {
        $lower = mb_strtolower($msg);

        // PDF
        if (preg_match('/pdf|generar\s+pdf|descargar\s+pdf/i', $lower)) {
            $type  = str_contains($lower, 'inventario') ? 'inventory' : 'financial';
            $label = $type === 'inventory' ? 'Inventario' : 'Financiero';
            if (!class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
                return response()->json(['reply' => "⚠️ Instala DomPDF:\n```\ncomposer require barryvdh/laravel-dompdf\n```", 'role' => 'admin']);
            }
            $downloadUrl = url("api/reports/download/{$type}");
            if ($user) {
                AiConversation::create(['user_id' => $user->id, 'role' => 'assistant', 'content' => "✅ ¡PDF de {$label} listo!",
                    'action' => 'download_pdf', 'download_url' => $downloadUrl, 'download_label' => "📄 Descargar PDF de {$label}", 'session_id' => $sessionId]);
            }
            return response()->json([
                'reply' => "✅ ¡PDF de {$label} listo! Haz clic para descargarlo.",
                'role'  => 'admin', 'action' => 'download_pdf',
                'download_url' => $downloadUrl, 'label' => "📄 Descargar PDF de {$label}", 'session_id' => $sessionId,
            ]);
        }

        // Stock bajo
        if (str_contains($lower, 'stock bajo') || str_contains($lower, 'inventario bajo')) {
            $products = Product::active()->lowStock()->get(['name', 'brand', 'stock', 'min_stock']);
            if ($products->isEmpty()) return response()->json(['reply' => '✅ Todos los productos tienen stock suficiente.', 'role' => 'admin']);
            $tabla = "⚠️ **Productos con stock bajo:**\n\n| Producto | Stock | Mínimo |\n|---|---|---|\n";
            foreach ($products as $p) $tabla .= "| {$p->brand} {$p->name} | {$p->stock} | {$p->min_stock} |\n";
            return response()->json(['reply' => $tabla, 'role' => 'admin']);
        }

        // Ventas hoy
        if (preg_match('/ventas?\s+(de\s+)?hoy|resumen\s+(de\s+)?hoy/i', $lower)) {
            $hoy      = Sale::completed()->whereDate('sale_date', today())->get();
            $ayer     = Sale::completed()->whereDate('sale_date', today()->subDay())->sum('total_pen');
            $total    = $hoy->sum('total_pen');
            $cantidad = $hoy->count();
            $ticket   = $cantidad > 0 ? round($total / $cantidad, 2) : 0;
            $pct      = $ayer > 0 ? round((($total - $ayer) / $ayer) * 100, 1) : 0;
            $tend     = $pct >= 0 ? "📈 +{$pct}%" : "📉 {$pct}%";
            $reply    = "📊 **Ventas de hoy — " . now()->format('d/m/Y') . "**\n\n" .
                        "💰 Total: **S/ " . number_format($total, 2) . "**\n" .
                        "🛒 Cantidad: **{$cantidad}**\n" .
                        "🎫 Ticket promedio: **S/ " . number_format($ticket, 2) . "**\n" .
                        "📈 vs ayer: **{$tend}**\n\n" .
                        ($total === 0.0 ? "😴 Sin ventas registradas hoy todavía." : "¡Vamos bien, jefe! 💪");
            return response()->json(['reply' => $reply, 'role' => 'admin']);
        }

        // Tipo de cambio
        if (str_contains($lower, 'tipo de cambio') || str_contains($lower, 'tasa de cambio') || str_contains($lower, 'dolar')) {
            $rate  = ExchangeRate::getCurrentRate();
            $reply = "💱 **Tipo de cambio actual**\n\n🟢 Compra: **S/ {$rate->buy_rate}**\n🔴 Venta: **S/ {$rate->sell_rate}**\n📅 Actualizado: {$rate->date->format('d/m/Y')}";
            return response()->json(['reply' => $reply, 'role' => 'admin']);
        }

        // Clientes inactivos
        if (str_contains($lower, 'clientes inactivos') || str_contains($lower, 'clientes sin compras')) {
            $count = Customer::whereDoesntHave('sales', fn($q) => $q->where('sale_date', '>=', now()->subMonths(3)))->count();
            $reply = "👥 **Clientes inactivos (últimos 3 meses)**\n\nTotal: **{$count} clientes**\n\n" .
                     ($count > 0 ? "💡 Considera enviarles una promoción para reactivarlos. 😄" : "✅ ¡Todos los clientes han comprado recientemente!");
            return response()->json(['reply' => $reply, 'role' => 'admin']);
        }

        // Gastos vs ingresos
        if (preg_match('/gastos?\s+vs?\.?\s+ingresos?|comparar\s+gastos?/i', $lower)) {
            $mes      = now()->month; $anio = now()->year;
            $ingresos = Sale::completed()->whereMonth('sale_date', $mes)->whereYear('sale_date', $anio)->sum('total_pen')
                      + ServiceRecord::completed()->whereMonth('service_date', $mes)->whereYear('service_date', $anio)->sum('total_pen');
            $gastos   = Expense::whereMonth('expense_date', $mes)->whereYear('expense_date', $anio)->sum('amount_pen');
            $neto     = $ingresos - $gastos;
            $margen   = $ingresos > 0 ? round(($neto / $ingresos) * 100, 1) : 0;
            $reply    = "💸 **Gastos vs Ingresos — " . now()->format('F Y') . "**\n\n" .
                        "✅ Ingresos: **S/ " . number_format($ingresos, 2) . "**\n" .
                        "❌ Gastos: **S/ " . number_format($gastos, 2) . "**\n" .
                        "💰 Neto: **S/ " . number_format($neto, 2) . "**\n" .
                        "📊 Margen: **{$margen}%**\n\n" .
                        ($neto >= 0 ? "¡El negocio está en positivo! 🎉" : "⚠️ Los gastos superan los ingresos este mes.");
            return response()->json(['reply' => $reply, 'role' => 'admin']);
        }

        // Conversión de moneda
        if (preg_match('/conviert[ea]?\s+(\d+(?:\.\d+)?)\s*(usd|dolares?|soles?|pen)/i', $lower, $matches)) {
            $amount   = (float) $matches[1];
            $currency = mb_strtolower($matches[2]);
            $rate     = ExchangeRate::getCurrentRate();
            if (str_contains($currency, 'usd') || str_contains($currency, 'dolar')) {
                $result = round($amount * $rate->sell_rate, 2);
                $reply  = "💱 **$ " . number_format($amount, 2) . " USD = S/ " . number_format($result, 2) . " PEN**\n(Tipo de venta: S/ {$rate->sell_rate})";
            } else {
                $result = round($amount / $rate->buy_rate, 2);
                $reply  = "💱 **S/ " . number_format($amount, 2) . " PEN = $ " . number_format($result, 2) . " USD**\n(Tipo de compra: S/ {$rate->buy_rate})";
            }
            return response()->json(['reply' => $reply, 'role' => 'admin']);
        }

        return null;
    }

    private function buildRealtimeContext(): string
    {
        try {
            $hoy   = today(); $mes = now()->month; $anio = now()->year;
            $vHoy  = Sale::completed()->whereDate('sale_date', $hoy)->sum('total_pen');
            $vAyer = Sale::completed()->whereDate('sale_date', $hoy->copy()->subDay())->sum('total_pen');
            $vMes  = Sale::completed()->whereMonth('sale_date', $mes)->whereYear('sale_date', $anio)->sum('total_pen');
            $cHoy  = Sale::completed()->whereDate('sale_date', $hoy)->count();
            $gMes  = Expense::whereMonth('expense_date', $mes)->whereYear('expense_date', $anio)->sum('amount_pen');
            $sMes  = ServiceRecord::completed()->whereMonth('service_date', $mes)->whereYear('service_date', $anio)->sum('total_pen');
            $sLow  = Product::active()->lowStock()->count();
            $cTot  = Customer::count();
            $cInac = Customer::whereDoesntHave('sales', fn($q) => $q->where('sale_date', '>=', now()->subMonths(3)))->count();
            $rate  = ExchangeRate::getCurrentRate();
            $pct   = $vAyer > 0 ? round((($vHoy - $vAyer) / $vAyer) * 100, 1) : 0;
            $neto  = ($vMes + $sMes) - $gMes;
            $top   = DB::table('sale_items')
                ->join('products', 'sale_items.product_id', '=', 'products.id')
                ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
                ->whereMonth('sales.sale_date', $mes)->whereYear('sales.sale_date', $anio)->where('sales.status', 'completed')
                ->select('products.name', 'products.brand', DB::raw('SUM(sale_items.quantity) as qty'))
                ->groupBy('products.id', 'products.name', 'products.brand')->orderByDesc('qty')->limit(3)->get()
                ->map(fn($p) => "{$p->brand} {$p->name} ({$p->qty} uds)")->implode(', ');

            return "\n\n📊 DATOS EN TIEMPO REAL:\n" .
                   "- Ventas hoy: S/ " . number_format($vHoy, 2) . " ({$cHoy} ventas, {$pct}% vs ayer)\n" .
                   "- Ventas del mes: S/ " . number_format($vMes, 2) . "\n" .
                   "- Servicios del mes: S/ " . number_format($sMes, 2) . "\n" .
                   "- Gastos del mes: S/ " . number_format($gMes, 2) . "\n" .
                   "- Utilidad neta del mes: S/ " . number_format($neto, 2) . "\n" .
                   "- Productos con stock bajo: {$sLow}\n" .
                   "- Clientes: {$cTot} total ({$cInac} inactivos)\n" .
                   "- Tipo de cambio: S/ {$rate->buy_rate} compra / S/ {$rate->sell_rate} venta\n" .
                   "- Top 3 productos del mes: {$top}\n";
        } catch (\Exception $e) {
            \Log::warning('AiController realtime context error: ' . $e->getMessage());
            return '';
        }
    }

    private function employeePrompt(string $userName): string
    {
        return "Eres Alexander, asistente de \"Importaciones Adan\", tienda de llantas en Perú.
Estás hablando con el empleado: {$userName}.
Tienes humor peruano: haces UN chiste corto por respuesta. Nunca eres aburrido.
Cuando muestres listas de productos, usa formato de tabla Markdown.

📦 PRODUCTOS DISPONIBLES:
| Producto | Medida | Precio S/ | Stock |
|---|---|---|---|
| Bridgestone Turanza T005 | 215/60R16 | 437.19 | 15 |
| Goodyear EfficientGrip | 195/65R15 | 369.93 | 25 |
| Michelin Pilot Sport | Aro 17 | 403.56 | 11 |
| Michelin Primacy 4 | 205/55R16 | 403.56 | 25 |
| Pirelli Cinturato | Aro 18 | 437.19 | 21 |

✅ PUEDES INFORMAR: precios, stock, características, medida correcta por vehículo.
🚫 NUNCA REVELES: costos, márgenes, ganancias, proveedores, reportes financieros.
Si preguntan algo restringido: \"Eso solo lo maneja el administrador, ¡yo solo sé de llantas! 😄\"
Responde en español, profesional y con humor peruano.";
    }

    private function adminPrompt(string $userName, string $realtimeData): string
    {
        return "Eres Alexander, asesor de negocios de \"Importaciones Adan\", tienda de llantas en Perú.
Estás hablando con el ADMINISTRADOR: {$userName}. Tienes acceso total a toda la información.
Tienes humor peruano: haces UN chiste corto por respuesta. Nunca eres aburrido.
Cuando muestres datos numéricos o comparativas, usa tablas Markdown.
Cuando el admin pregunte por tendencias, analiza los datos y da recomendaciones concretas.
{$realtimeData}

📦 PRODUCTOS — INFORMACIÓN COMPLETA:
| Producto | Medida | Venta S/ | Costo S/ | Margen | Stock |
|---|---|---|---|---|---|
| Bridgestone Turanza T005 | 215/60R16 | 437.19 | 280.00 | 56% | 15 |
| Goodyear EfficientGrip | 195/65R15 | 369.93 | 220.00 | 68% | 25 |
| Michelin Pilot Sport | Aro 17 | 403.56 | 250.00 | 61% | 11 |
| Michelin Primacy 4 | 205/55R16 | 403.56 | 245.00 | 65% | 25 |
| Pirelli Cinturato | Aro 18 | 437.19 | 275.00 | 59% | 21 |

🏭 PROVEEDORES:
- Bridgestone Perú: contacto@bridgestoneperu.com — pago 30 días
- Goodyear: 01-445-2200 — descuento 12% por +20 unidades
- Michelin Andes: michelin.andes@dist.pe — pago contado -5%
- Pirelli SAC: 987-654-321 — promo activa hasta fin de mes

💰 POLÍTICA DE DESCUENTOS: hasta 5% directo | 6-15% aprobación admin | más de 15% solo casos especiales.
✅ PUEDES RESPONDER TODO sin restricciones. Usa los datos en tiempo real para análisis precisos.
Responde en español, profesional y con humor peruano.";
    }
}
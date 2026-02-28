<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\ServiceRecord;
use App\Models\Expense;
use App\Models\Product;
use App\Models\ExchangeRate;
use App\Models\Customer;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReportController extends Controller
{
    /**
     * Adaptador que decide qué vista de dashboard enviar.
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();

        if ($user && $user->role === 'admin') {
            return $this->adminDashboard($request);
        }

        return $this->staffDashboard($request);
    }

    /**
     * Dashboard estratégico (solo admin)
     */
    public function adminDashboard(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $period = $request->get('period', 'today');
        $exchangeRate = ExchangeRate::getCurrentRate();

        [$startDate, $endDate] = $this->getDateRange($period);

        // --- VENTAS ---
        $sales = Sale::completed()
            ->whereBetween('sale_date', [$startDate, $endDate])
            ->with('items')
            ->get();
        $salesRevenue = $sales->sum('total_pen');
        $salesCount   = $sales->count();
        $salesProfit  = $sales->sum(fn($sale) => $sale->profit);

        // --- SERVICIOS ---
        $services        = ServiceRecord::completed()->whereBetween('service_date', [$startDate, $endDate])->get();
        $servicesRevenue = $services->sum('total_pen');
        $servicesCount   = $services->count();

        // --- GASTOS ---
        $expenses           = Expense::whereBetween('expense_date', [$startDate, $endDate])->get();
        $totalExpenses      = $expenses->sum('amount_pen');
        $expensesByCategory = $expenses->groupBy('category')->map(fn($g) => [
            'count' => $g->count(),
            'total' => $g->sum('amount_pen'),
        ]);

        // --- INVENTARIO ---
        $inventoryValueUSD = Product::active()->get()->sum(fn($p) => $p->cost_usd * $p->stock);
        $inventoryValuePEN = $inventoryValueUSD * $exchangeRate->buy_rate;
        $lowStockCount     = Product::active()->lowStock()->count();

        // --- FINANCIERO ---
        $totalRevenue  = $salesRevenue + $servicesRevenue;
        $grossProfit   = $salesProfit + $servicesRevenue;
        $netProfit     = $grossProfit - $totalExpenses;
        $profitMargin  = $totalRevenue > 0 ? ($netProfit / $totalRevenue) * 100 : 0;

        // --- PERÍODO ANTERIOR ---
        [$prevStart, $prevEnd] = $this->getDateRange($this->getPreviousPeriod($period));
        $prevSalesRevenue = Sale::completed()->whereBetween('sale_date', [$prevStart, $prevEnd])->sum('total_pen');
        $revenueGrowth    = $prevSalesRevenue > 0
            ? (($salesRevenue - $prevSalesRevenue) / $prevSalesRevenue) * 100
            : 0;

        // --- KPIs ---
        $totalLifetime  = Sale::completed()->sum('total_pen') + ServiceRecord::completed()->sum('total_pen');
        $customerCount  = Customer::count() ?: 1;
        $clv            = $totalLifetime / $customerCount;

        $threshold      = Carbon::now()->subMonths(3);
        $inactive       = Customer::whereDoesntHave('sales', fn($q) => $q->where('sale_date', '>=', $threshold))
                                  ->whereDoesntHave('serviceRecords', fn($q) => $q->where('service_date', '>=', $threshold))
                                  ->count();
        $churnRate      = ($inactive / $customerCount) * 100;

        $today       = Carbon::now();
        $projection  = $today->day > 0 ? ($totalRevenue / $today->day) * $today->daysInMonth : $totalRevenue;

        return response()->json([
            'success' => true,
            'data' => [
                'period'     => $period,
                'date_range' => ['start' => $startDate->format('Y-m-d'), 'end' => $endDate->format('Y-m-d')],
                'exchange_rate' => ['buy' => $exchangeRate->buy_rate, 'sell' => $exchangeRate->sell_rate, 'date' => $exchangeRate->date->format('Y-m-d')],
                'sales' => [
                    'count'          => $salesCount,
                    'revenue'        => round($salesRevenue, 2),
                    'profit'         => round($salesProfit, 2),
                    'average_ticket' => $salesCount > 0 ? round($salesRevenue / $salesCount, 2) : 0,
                ],
                'services'    => ['count' => $servicesCount, 'revenue' => round($servicesRevenue, 2)],
                'total_revenue' => round($totalRevenue, 2),
                'expenses'    => ['total' => round($totalExpenses, 2), 'by_category' => $expensesByCategory],
                'profitability' => [
                    'gross_profit'           => round($grossProfit, 2),
                    'net_profit'             => round($netProfit, 2),
                    'profit_margin_percentage' => round($profitMargin, 2),
                ],
                'inventory' => [
                    'value_usd'      => round($inventoryValueUSD, 2),
                    'value_pen'      => round($inventoryValuePEN, 2),
                    'low_stock_items' => $lowStockCount,
                    'total_products' => Product::active()->count(),
                ],
                'growth' => [
                    'revenue_growth_percentage' => round($revenueGrowth, 2),
                    'previous_revenue'          => round($prevSalesRevenue, 2),
                ],
                'clv'              => round($clv, 2),
                'churn_rate'       => round($churnRate, 2),
                'flow_projection'  => round($projection, 2),
                'revenue_composition' => [
                    'ventas'    => round($salesRevenue, 2),
                    'servicios' => round($servicesRevenue, 2),
                ],
            ],
        ]);
    }

    /**
     * Dashboard operativo para empleados (sin datos sensibles)
     */
    public function staffDashboard(Request $request)
    {
        $period = $request->get('period', 'today');
        [$startDate, $endDate] = $this->getDateRange($period);

        return response()->json([
            'success' => true,
            'data' => [
                'period'    => $period,
                'sales'     => ['count' => Sale::completed()->whereBetween('sale_date', [$startDate, $endDate])->count()],
                'services'  => ['count' => ServiceRecord::completed()->whereBetween('service_date', [$startDate, $endDate])->count()],
                'inventory' => [
                    'low_stock_items' => Product::active()->lowStock()->count(),
                    'total_products'  => Product::active()->count(),
                ],
            ],
        ]);
    }

    /**
     * Reporte financiero detallado
     */
    public function financial(Request $request)
    {
        $startDate    = $request->get('start_date', Carbon::now()->startOfMonth());
        $endDate      = $request->get('end_date', Carbon::now());
        $exchangeRate = ExchangeRate::getCurrentRate();

        $sales = Sale::completed()->whereBetween('sale_date', [$startDate, $endDate])->with('items.product')->get();

        $salesByDay = $sales->groupBy(fn($s) => $s->sale_date->format('Y-m-d'))->map(fn($g) => [
            'count'   => $g->count(),
            'revenue' => $g->sum('total_pen'),
            'profit'  => $g->sum(fn($s) => $s->profit),
        ]);

        $salesByPaymentMethod = $sales->groupBy('payment_method')->map(fn($g) => [
            'count' => $g->count(),
            'total' => $g->sum('total_pen'),
        ]);

        $topProducts = DB::table('sale_items')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->whereBetween('sales.sale_date', [$startDate, $endDate])
            ->where('sales.status', 'completed')
            ->select(
                'products.id', 'products.name', 'products.brand', 'products.model',
                DB::raw('SUM(sale_items.quantity) as total_quantity'),
                DB::raw('SUM(sale_items.total_pen) as total_revenue')
            )
            ->groupBy('products.id', 'products.name', 'products.brand', 'products.model')
            ->orderByDesc('total_revenue')
            ->limit(20)
            ->get();

        $services     = ServiceRecord::completed()->whereBetween('service_date', [$startDate, $endDate])->with('service')->get();
        $servicesByType = $services->groupBy('service.name')->map(fn($g) => [
            'count'   => $g->count(),
            'revenue' => $g->sum('total_pen'),
        ]);

        $expenses           = Expense::whereBetween('expense_date', [$startDate, $endDate])->get();
        $expensesByCategory = $expenses->groupBy('category')->map(fn($g) => [
            'count' => $g->count(),
            'total' => $g->sum('amount_pen'),
            'items' => $g->map(fn($e) => [
                'date'        => $e->expense_date->format('Y-m-d'),
                'description' => $e->description,
                'amount'      => $e->amount_pen,
            ]),
        ]);
        $expensesByDay = $expenses->groupBy(fn($e) => $e->expense_date->format('Y-m-d'))->map(fn($g) => $g->sum('amount_pen'));

        $totalIncome  = $sales->sum('total_pen') + $services->sum('total_pen');
        $totalCosts   = $sales->sum(fn($sale) => $sale->items->sum(fn($item) => $item->unit_cost_usd * $item->quantity * $sale->exchange_rate));
        $totalExpenses = $expenses->sum('amount_pen');
        $grossProfit  = $totalIncome - $totalCosts;
        $netProfit    = $grossProfit - $totalExpenses;

        return response()->json([
            'success' => true,
            'data' => [
                'date_range' => ['start' => Carbon::parse($startDate)->format('Y-m-d'), 'end' => Carbon::parse($endDate)->format('Y-m-d')],
                'summary' => [
                    'total_income'   => round($totalIncome, 2),
                    'total_costs'    => round($totalCosts, 2),
                    'total_expenses' => round($totalExpenses, 2),
                    'gross_profit'   => round($grossProfit, 2),
                    'net_profit'     => round($netProfit, 2),
                    'profit_margin'  => $totalIncome > 0 ? round(($netProfit / $totalIncome) * 100, 2) : 0,
                ],
                'sales' => [
                    'total'             => round($sales->sum('total_pen'), 2),
                    'count'             => $sales->count(),
                    'by_day'            => $salesByDay,
                    'by_payment_method' => $salesByPaymentMethod,
                    'top_products'      => $topProducts,
                ],
                'services' => [
                    'total'   => round($services->sum('total_pen'), 2),
                    'count'   => $services->count(),
                    'by_type' => $servicesByType,
                ],
                'expenses' => [
                    'total'       => round($totalExpenses, 2),
                    'by_category' => $expensesByCategory,
                    'by_day'      => $expensesByDay,
                ],
            ],
        ]);
    }

    /**
     * Reporte de inventario valorizado
     */
    public function inventory()
    {
        $exchangeRate = ExchangeRate::getCurrentRate();
        $products     = Product::active()->get();

        $inventory = $products->map(fn($p) => [
            'id'             => $p->id,
            'name'           => $p->name,
            'brand'          => $p->brand,
            'model'          => $p->model,
            'size'           => $p->size,
            'stock'          => $p->stock,
            'cost_usd'       => $p->cost_usd,
            'price_usd'      => $p->price_usd,
            'cost_pen'       => round($p->cost_usd * $exchangeRate->buy_rate, 2),
            'price_pen'      => round($p->price_usd * $exchangeRate->sell_rate, 2),
            'stock_value_usd' => round($p->cost_usd * $p->stock, 2),
            'stock_value_pen' => round($p->cost_usd * $p->stock * $exchangeRate->buy_rate, 2),
            'is_low_stock'   => $p->isLowStock(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'products' => $inventory,
                'summary' => [
                    'total_products'  => $products->count(),
                    'total_stock'     => $products->sum('stock'),
                    'total_value_usd' => round($inventory->sum('stock_value_usd'), 2),
                    'total_value_pen' => round($inventory->sum('stock_value_pen'), 2),
                    'low_stock_count' => $inventory->where('is_low_stock', true)->count(),
                ],
                'exchange_rate' => ['buy' => $exchangeRate->buy_rate, 'sell' => $exchangeRate->sell_rate],
            ],
        ]);
    }

    /**
     * Export data to PDF
     * Requiere: composer require barryvdh/laravel-dompdf
     */
    public function export(Request $request)
    {
        $type      = $request->get('type', 'financial');
        $startDate = $request->get('start_date', Carbon::now()->startOfMonth());
        $endDate   = $request->get('end_date', Carbon::now());

        // Verificar que la librería PDF esté instalada
        if (!class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return response()->json([
                'success' => false,
                'message' => 'Librería PDF no instalada. Ejecuta: composer require barryvdh/laravel-dompdf',
            ], 500);
        }

        $payload = [];
        if ($type === 'financial') {
            $resp    = $this->financial($request);
            $payload = $resp->getData(true)['data'] ?? [];
        } elseif ($type === 'inventory') {
            $resp    = $this->inventory();
            $payload = $resp->getData(true)['data'] ?? [];
        }

        $pdf      = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.' . $type, ['data' => $payload]);
        $filename = "report_{$type}_" . now()->format('YmdHis') . ".pdf";
        Storage::put('public/reports/' . $filename, $pdf->output());
        $url = url('storage/reports/' . $filename);

        return response()->json(['success' => true, 'url' => $url]);
    }

    private function getDateRange($period)
    {
        $today = Carbon::today();
        return match ($period) {
            'today'      => [$today, $today->copy()->endOfDay()],
            'yesterday'  => [$today->copy()->subDay(), $today->copy()->subDay()->endOfDay()],
            'this_week'  => [$today->copy()->startOfWeek(), $today->copy()->endOfWeek()],
            'last_week'  => [$today->copy()->subWeek()->startOfWeek(), $today->copy()->subWeek()->endOfWeek()],
            'this_month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
            'last_month' => [$today->copy()->subMonth()->startOfMonth(), $today->copy()->subMonth()->endOfMonth()],
            'this_year'  => [$today->copy()->startOfYear(), $today->copy()->endOfYear()],
            default      => [$today, $today->copy()->endOfDay()],
        };
    }

    private function getPreviousPeriod($period)
    {
        return match ($period) {
            'today'      => 'yesterday',
            'this_week'  => 'last_week',
            'this_month' => 'last_month',
            default      => 'yesterday',
        };
    }
}
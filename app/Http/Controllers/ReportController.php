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

class ReportController extends Controller
{
    /**
     * Dashboard principal con métricas clave
     */
    public function dashboard(Request $request)
    {
        $period = $request->get('period', 'today');
        $exchangeRate = ExchangeRate::getCurrentRate();
        
        // Configurar rango de fechas según período
        [$startDate, $endDate] = $this->getDateRange($period);
        
        // VENTAS
        $sales = Sale::completed()
            ->whereBetween('sale_date', [$startDate, $endDate])
            ->with('items')
            ->get();
        
        $salesRevenue = $sales->sum('total_pen');
        $salesCount = $sales->count();
        $salesProfit = $sales->sum(function($sale) {
            return $sale->profit;
        });
        
        // SERVICIOS
        $services = ServiceRecord::completed()
            ->whereBetween('service_date', [$startDate, $endDate])
            ->get();
        
        $servicesRevenue = $services->sum('total_pen');
        $servicesCount = $services->count();
        
        // GASTOS
        $expenses = Expense::whereBetween('expense_date', [$startDate, $endDate])->get();
        $totalExpenses = $expenses->sum('amount_pen');
        
        $expensesByCategory = $expenses->groupBy('category')->map(function($group) {
            return [
                'count' => $group->count(),
                'total' => $group->sum('amount_pen'),
            ];
        });
        
        // INVENTARIO
        $inventoryValueUSD = Product::active()->get()->sum(function($product) {
            return $product->cost_usd * $product->stock;
        });
        
        $inventoryValuePEN = $inventoryValueUSD * $exchangeRate->buy_rate;
        
        $lowStockCount = Product::active()->lowStock()->count();
        
        // CÁLCULOS FINANCIEROS
        $totalRevenue = $salesRevenue + $servicesRevenue;
        $grossProfit = $salesProfit + $servicesRevenue; // Servicios son ganancia pura
        $netProfit = $grossProfit - $totalExpenses;
        $profitMargin = $totalRevenue > 0 ? ($netProfit / $totalRevenue) * 100 : 0;
        
        // COMPARACIÓN CON PERÍODO ANTERIOR
        $previousPeriod = $this->getPreviousPeriod($period);
        [$prevStartDate, $prevEndDate] = $this->getDateRange($previousPeriod);
        
        $prevSalesRevenue = Sale::completed()
            ->whereBetween('sale_date', [$prevStartDate, $prevEndDate])
            ->sum('total_pen');
        
        $revenueGrowth = $prevSalesRevenue > 0 
            ? (($salesRevenue - $prevSalesRevenue) / $prevSalesRevenue) * 100 
            : 0;
        
        $dashboard = [
            'period' => $period,
            'date_range' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'exchange_rate' => [
                'buy' => $exchangeRate->buy_rate,
                'sell' => $exchangeRate->sell_rate,
                'date' => $exchangeRate->date->format('Y-m-d'),
            ],
            'sales' => [
                'count' => $salesCount,
                'revenue' => round($salesRevenue, 2),
                'profit' => round($salesProfit, 2),
                'average_ticket' => $salesCount > 0 ? round($salesRevenue / $salesCount, 2) : 0,
            ],
            'services' => [
                'count' => $servicesCount,
                'revenue' => round($servicesRevenue, 2),
            ],
            'total_revenue' => round($totalRevenue, 2),
            'expenses' => [
                'total' => round($totalExpenses, 2),
                'by_category' => $expensesByCategory,
            ],
            'profitability' => [
                'gross_profit' => round($grossProfit, 2),
                'net_profit' => round($netProfit, 2),
                'profit_margin_percentage' => round($profitMargin, 2),
            ],
            'inventory' => [
                'value_usd' => round($inventoryValueUSD, 2),
                'value_pen' => round($inventoryValuePEN, 2),
                'low_stock_items' => $lowStockCount,
                'total_products' => Product::active()->count(),
            ],
            'growth' => [
                'revenue_growth_percentage' => round($revenueGrowth, 2),
                'previous_revenue' => round($prevSalesRevenue, 2),
            ],
        ];
        
        return response()->json([
            'success' => true,
            'data' => $dashboard
        ]);
    }

    /**
     * Reporte financiero detallado
     */
    public function financial(Request $request)
    {
        $startDate = $request->get('start_date', Carbon::now()->startOfMonth());
        $endDate = $request->get('end_date', Carbon::now());
        
        $exchangeRate = ExchangeRate::getCurrentRate();
        
        // INGRESOS DETALLADOS
        $sales = Sale::completed()
            ->whereBetween('sale_date', [$startDate, $endDate])
            ->with('items.product')
            ->get();
        
        $salesByDay = $sales->groupBy(function($sale) {
            return $sale->sale_date->format('Y-m-d');
        })->map(function($group) {
            return [
                'count' => $group->count(),
                'revenue' => $group->sum('total_pen'),
                'profit' => $group->sum(function($sale) {
                    return $sale->profit;
                }),
            ];
        });
        
        $salesByPaymentMethod = $sales->groupBy('payment_method')->map(function($group) {
            return [
                'count' => $group->count(),
                'total' => $group->sum('total_pen'),
            ];
        });
        
        $topProducts = DB::table('sale_items')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->whereBetween('sales.sale_date', [$startDate, $endDate])
            ->where('sales.status', 'completed')
            ->select(
                'products.id',
                'products.name',
                'products.brand',
                'products.model',
                DB::raw('SUM(sale_items.quantity) as total_quantity'),
                DB::raw('SUM(sale_items.total_pen) as total_revenue')
            )
            ->groupBy('products.id', 'products.name', 'products.brand', 'products.model')
            ->orderByDesc('total_revenue')
            ->limit(20)
            ->get();
        
        // SERVICIOS DETALLADOS
        $services = ServiceRecord::completed()
            ->whereBetween('service_date', [$startDate, $endDate])
            ->with('service')
            ->get();
        
        $servicesByType = $services->groupBy('service.name')->map(function($group) {
            return [
                'count' => $group->count(),
                'revenue' => $group->sum('total_pen'),
            ];
        });
        
        // GASTOS DETALLADOS
        $expenses = Expense::whereBetween('expense_date', [$startDate, $endDate])->get();
        
        $expensesByCategory = $expenses->groupBy('category')->map(function($group) {
            return [
                'count' => $group->count(),
                'total' => $group->sum('amount_pen'),
                'items' => $group->map(function($expense) {
                    return [
                        'date' => $expense->expense_date->format('Y-m-d'),
                        'description' => $expense->description,
                        'amount' => $expense->amount_pen,
                    ];
                }),
            ];
        });
        
        $expensesByDay = $expenses->groupBy(function($expense) {
            return $expense->expense_date->format('Y-m-d');
        })->map(function($group) {
            return $group->sum('amount_pen');
        });
        
        // RESUMEN FINANCIERO
        $totalIncome = $sales->sum('total_pen') + $services->sum('total_pen');
        $totalCosts = $sales->sum(function($sale) {
            return $sale->items->sum(function($item) use ($sale) {
                return $item->unit_cost_usd * $item->quantity * $sale->exchange_rate;
            });
        });
        $totalExpenses = $expenses->sum('amount_pen');
        $grossProfit = $totalIncome - $totalCosts;
        $netProfit = $grossProfit - $totalExpenses;
        
        $report = [
            'date_range' => [
                'start' => Carbon::parse($startDate)->format('Y-m-d'),
                'end' => Carbon::parse($endDate)->format('Y-m-d'),
            ],
            'summary' => [
                'total_income' => round($totalIncome, 2),
                'total_costs' => round($totalCosts, 2),
                'total_expenses' => round($totalExpenses, 2),
                'gross_profit' => round($grossProfit, 2),
                'net_profit' => round($netProfit, 2),
                'profit_margin' => $totalIncome > 0 ? round(($netProfit / $totalIncome) * 100, 2) : 0,
            ],
            'sales' => [
                'total' => round($sales->sum('total_pen'), 2),
                'count' => $sales->count(),
                'by_day' => $salesByDay,
                'by_payment_method' => $salesByPaymentMethod,
                'top_products' => $topProducts,
            ],
            'services' => [
                'total' => round($services->sum('total_pen'), 2),
                'count' => $services->count(),
                'by_type' => $servicesByType,
            ],
            'expenses' => [
                'total' => round($totalExpenses, 2),
                'by_category' => $expensesByCategory,
                'by_day' => $expensesByDay,
            ],
        ];
        
        return response()->json([
            'success' => true,
            'data' => $report
        ]);
    }

    /**
     * Reporte de inventario valorizado
     */
    public function inventory()
    {
        $exchangeRate = ExchangeRate::getCurrentRate();
        
        $products = Product::active()->get();
        
        $inventory = $products->map(function($product) use ($exchangeRate) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'brand' => $product->brand,
                'model' => $product->model,
                'size' => $product->size,
                'stock' => $product->stock,
                'cost_usd' => $product->cost_usd,
                'price_usd' => $product->price_usd,
                'cost_pen' => round($product->cost_usd * $exchangeRate->buy_rate, 2),
                'price_pen' => round($product->price_usd * $exchangeRate->sell_rate, 2),
                'stock_value_usd' => round($product->cost_usd * $product->stock, 2),
                'stock_value_pen' => round($product->cost_usd * $product->stock * $exchangeRate->buy_rate, 2),
                'is_low_stock' => $product->isLowStock(),
            ];
        });
        
        $totalValueUSD = $inventory->sum('stock_value_usd');
        $totalValuePEN = $inventory->sum('stock_value_pen');
        
        return response()->json([
            'success' => true,
            'data' => [
                'products' => $inventory,
                'summary' => [
                    'total_products' => $products->count(),
                    'total_stock' => $products->sum('stock'),
                    'total_value_usd' => round($totalValueUSD, 2),
                    'total_value_pen' => round($totalValuePEN, 2),
                    'low_stock_count' => $inventory->where('is_low_stock', true)->count(),
                ],
                'exchange_rate' => [
                    'buy' => $exchangeRate->buy_rate,
                    'sell' => $exchangeRate->sell_rate,
                ],
            ]
        ]);
    }

    /**
     * Obtener rango de fechas según período
     */
    private function getDateRange($period)
    {
        $today = Carbon::today();
        
        switch ($period) {
            case 'today':
                return [$today, $today->copy()->endOfDay()];
            case 'yesterday':
                $yesterday = $today->copy()->subDay();
                return [$yesterday, $yesterday->copy()->endOfDay()];
            case 'this_week':
                return [$today->copy()->startOfWeek(), $today->copy()->endOfWeek()];
            case 'last_week':
                return [
                    $today->copy()->subWeek()->startOfWeek(),
                    $today->copy()->subWeek()->endOfWeek()
                ];
            case 'this_month':
                return [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()];
            case 'last_month':
                return [
                    $today->copy()->subMonth()->startOfMonth(),
                    $today->copy()->subMonth()->endOfMonth()
                ];
            case 'this_year':
                return [$today->copy()->startOfYear(), $today->copy()->endOfYear()];
            default:
                return [$today, $today->copy()->endOfDay()];
        }
    }

    /**
     * Obtener período anterior
     */
    private function getPreviousPeriod($period)
    {
        $map = [
            'today' => 'yesterday',
            'this_week' => 'last_week',
            'this_month' => 'last_month',
        ];
        
        return $map[$period] ?? 'yesterday';
    }
}
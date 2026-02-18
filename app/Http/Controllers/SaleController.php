<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Product;
use App\Models\Customer;
use App\Models\ExchangeRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SaleController extends Controller
{
    /**
     * Listar ventas con filtros y paginación
     */
    public function index(Request $request)
    {
        $query = Sale::with(['customer', 'items.product', 'creator']);
        
        // Filtros
        if ($request->has('search')) {
            $query->search($request->search);
        }
        
        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }
        
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }
        
        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }
        
        // Filtros de fecha
        if ($request->has('date_from')) {
            $query->where('sale_date', '>=', $request->date_from);
        }
        
        if ($request->has('date_to')) {
            $query->where('sale_date', '<=', $request->date_to);
        }
        
        // Filtros rápidos
        if ($request->has('period')) {
            switch ($request->period) {
                case 'today':
                    $query->today();
                    break;
                case 'this_month':
                    $query->thisMonth();
                    break;
                case 'this_year':
                    $query->thisYear();
                    break;
            }
        }
        
        // Ordenamiento
        $sortBy = $request->get('sort_by', 'sale_date');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);
        
        // Paginación
        $perPage = $request->get('per_page', 15);
        $sales = $query->paginate($perPage);
        
        return response()->json([
            'success' => true,
            'data' => $sales
        ]);
    }

    /**
     * Crear nueva venta
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price_pen' => 'required|numeric|min:0',
            'items.*.discount_pen' => 'nullable|numeric|min:0',
            'payment_method' => 'required|string|in:efectivo,tarjeta,transferencia,credito',
            'payment_status' => 'nullable|string|in:paid,pending,partial',
            'amount_paid' => 'nullable|numeric|min:0',
            'discount_pen' => 'nullable|numeric|min:0',
            'invoice_type' => 'nullable|string|in:boleta,factura',
            'invoice_number' => 'nullable|string',
            'notes' => 'nullable|string',
            'sale_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        
        try {
            // Obtener tipo de cambio actual
            $exchangeRate = ExchangeRate::getCurrentRate();
            
            // Calcular totales
            $subtotal = 0;
            $itemsData = [];
            
            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);
                
                // Verificar stock
                if ($product->stock < $item['quantity']) {
                    throw new \Exception("Stock insuficiente para el producto: {$product->name}");
                }
                
                $itemSubtotal = $item['unit_price_pen'] * $item['quantity'];
                $itemDiscount = $item['discount_pen'] ?? 0;
                $itemTotal = $itemSubtotal - $itemDiscount;
                
                $subtotal += $itemTotal;
                
                $itemsData[] = [
                    'product' => $product,
                    'quantity' => $item['quantity'],
                    'unit_price_pen' => $item['unit_price_pen'],
                    'unit_price_usd' => $product->price_usd,
                    'unit_cost_usd' => $product->cost_usd,
                    'subtotal_pen' => $itemSubtotal,
                    'discount_pen' => $itemDiscount,
                    'total_pen' => $itemTotal,
                ];
            }
            
            $discount = $request->discount_pen ?? 0;
            $subtotalAfterDiscount = $subtotal - $discount;
            
            // Calcular IGV (18%)
            $tax = $subtotalAfterDiscount * 0.18;
            $total = $subtotalAfterDiscount + $tax;
            
            // Crear venta
            $sale = Sale::create([
                'customer_id' => $request->customer_id,
                'subtotal_pen' => $subtotal,
                'discount_pen' => $discount,
                'tax_pen' => $tax,
                'total_pen' => $total,
                'exchange_rate' => $exchangeRate->sell_rate,
                'payment_method' => $request->payment_method,
                'payment_status' => $request->payment_status ?? 'paid',
                'amount_paid' => $request->amount_paid ?? $total,
                'status' => 'completed',
                'invoice_type' => $request->invoice_type,
                'invoice_number' => $request->invoice_number,
                'notes' => $request->notes,
                'sale_date' => $request->sale_date ?? Carbon::now(),
                'created_by' => auth()->id(),
            ]);
            
            // Crear items de venta y reducir stock
            foreach ($itemsData as $itemData) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $itemData['product']->id,
                    'quantity' => $itemData['quantity'],
                    'unit_price_usd' => $itemData['unit_price_usd'],
                    'unit_cost_usd' => $itemData['unit_cost_usd'],
                    'unit_price_pen' => $itemData['unit_price_pen'],
                    'subtotal_pen' => $itemData['subtotal_pen'],
                    'discount_pen' => $itemData['discount_pen'],
                    'total_pen' => $itemData['total_pen'],
                ]);
                
                // Reducir stock
                $itemData['product']->reduceStock($itemData['quantity']);
            }
            
            DB::commit();
            
            // Cargar relaciones
            $sale->load(['customer', 'items.product']);
            
            return response()->json([
                'success' => true,
                'message' => 'Venta registrada exitosamente',
                'data' => $sale
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar la venta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ver detalle de venta
     */
    public function show($id)
    {
        $sale = Sale::with(['customer', 'items.product', 'creator'])->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $sale,
            'profit' => $sale->profit,
            'profit_margin' => $sale->profit_margin,
            'balance' => $sale->balance,
        ]);
    }

    /**
     * Cancelar venta
     */
    public function cancel($id)
    {
        DB::beginTransaction();
        
        try {
            $sale = Sale::findOrFail($id);
            
            if ($sale->status === 'cancelled') {
                return response()->json([
                    'success' => false,
                    'message' => 'La venta ya está cancelada'
                ], 400);
            }
            
            // Devolver stock
            foreach ($sale->items as $item) {
                $item->product->increaseStock($item->quantity);
            }
            
            // Actualizar estado
            $sale->status = 'cancelled';
            $sale->save();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Venta cancelada exitosamente',
                'data' => $sale
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Error al cancelar la venta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de ventas
     */
    public function stats(Request $request)
    {
        $period = $request->get('period', 'today');
        
        $query = Sale::completed();
        
        switch ($period) {
            case 'today':
                $query->today();
                break;
            case 'this_month':
                $query->thisMonth();
                break;
            case 'this_year':
                $query->thisYear();
                break;
        }
        
        $sales = $query->with('items')->get();
        
        $stats = [
            'total_sales' => $sales->count(),
            'total_revenue' => $sales->sum('total_pen'),
            'total_profit' => $sales->sum(function($sale) {
                return $sale->profit;
            }),
            'average_sale' => $sales->count() > 0 ? $sales->avg('total_pen') : 0,
            'payment_methods' => $sales->groupBy('payment_method')->map(function($group) {
                return [
                    'count' => $group->count(),
                    'total' => $group->sum('total_pen'),
                ];
            }),
            'top_customers' => Customer::withCount('sales')
                ->having('sales_count', '>', 0)
                ->orderByDesc('sales_count')
                ->limit(10)
                ->get(),
            'top_products' => Product::withCount('saleItems')
                ->having('sale_items_count', '>', 0)
                ->orderByDesc('sale_items_count')
                ->limit(10)
                ->get(),
        ];
        
        return response()->json([
            'success' => true,
            'period' => $period,
            'data' => $stats
        ]);
    }
}
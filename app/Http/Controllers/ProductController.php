<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ExchangeRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    /**
     * Listar productos con paginación y filtros
     */
    public function index(Request $request)
    {
        $query = Product::with('saleItems');
        
        // Filtros
        if ($request->has('search')) {
            $query->search($request->search);
        }
        
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }
        
        if ($request->has('brand')) {
            $query->where('brand', $request->brand);
        }
        
        if ($request->has('size')) {
            $query->where('size', $request->size);
        }
        
        if ($request->has('low_stock') && $request->low_stock) {
            $query->lowStock();
        }
        
        if ($request->has('active')) {
            $query->where('is_active', $request->active);
        }
        
        // Ordenamiento
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);
        
        // Paginación
        $perPage = $request->get('per_page', 15);
        $products = $query->paginate($perPage);
        
        // Agregar tipo de cambio actual
        $exchangeRate = ExchangeRate::getCurrentRate();
        
        return response()->json([
            'success' => true,
            'data' => $products,
            'exchange_rate' => $exchangeRate,
        ]);
    }

    /**
     * Crear nuevo producto
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'brand' => 'required|string|max:100',
            'model' => 'required|string|max:100',
            'size' => 'required|string|max:50',
            'category' => 'required|string|max:50',
            'cost_usd' => 'required|numeric|min:0',
            'price_usd' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'min_stock' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
            'sku' => 'nullable|string|unique:products,sku',
            'supplier' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $product = Product::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Producto creado exitosamente',
            'data' => $product
        ], 201);
    }

    /**
     * Ver detalle de producto
     */
    public function show($id)
    {
        $product = Product::with('saleItems.sale')->findOrFail($id);
        $exchangeRate = ExchangeRate::getCurrentRate();
        
        return response()->json([
            'success' => true,
            'data' => $product,
            'exchange_rate' => $exchangeRate,
            'price_pen' => $product->price_pen,
            'cost_pen' => $product->cost_pen,
        ]);
    }

    /**
     * Actualizar producto
     */
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'brand' => 'sometimes|required|string|max:100',
            'model' => 'sometimes|required|string|max:100',
            'size' => 'sometimes|required|string|max:50',
            'category' => 'sometimes|required|string|max:50',
            'cost_usd' => 'sometimes|required|numeric|min:0',
            'price_usd' => 'sometimes|required|numeric|min:0',
            'stock' => 'sometimes|required|integer|min:0',
            'min_stock' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
            'sku' => 'nullable|string|unique:products,sku,' . $id,
            'supplier' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $product->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Producto actualizado exitosamente',
            'data' => $product
        ]);
    }

    /**
     * Eliminar producto (soft delete)
     */
    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Producto eliminado exitosamente'
        ]);
    }

    /**
     * Actualizar stock
     */
    public function updateStock(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'stock' => 'required|integer|min:0',
            'operation' => 'required|in:set,add,subtract',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $product = Product::findOrFail($id);
        
        switch ($request->operation) {
            case 'set':
                $product->stock = $request->stock;
                break;
            case 'add':
                $product->increaseStock($request->stock);
                break;
            case 'subtract':
                $product->reduceStock($request->stock);
                break;
        }

        return response()->json([
            'success' => true,
            'message' => 'Stock actualizado exitosamente',
            'data' => $product
        ]);
    }

    /**
     * Obtener productos con bajo stock
     */
    public function lowStock()
    {
        $products = Product::active()->lowStock()->get();
        
        return response()->json([
            'success' => true,
            'data' => $products,
            'count' => $products->count()
        ]);
    }

    /**
     * Obtener estadísticas de productos
     */
    public function stats()
    {
        $exchangeRate = ExchangeRate::getCurrentRate();
        
        $stats = [
            'total_products' => Product::active()->count(),
            'total_stock' => Product::active()->sum('stock'),
            'low_stock_products' => Product::active()->lowStock()->count(),
            'inventory_value_usd' => Product::active()->get()->sum(function($product) {
                return $product->cost_usd * $product->stock;
            }),
            'inventory_value_pen' => Product::active()->get()->sum(function($product) use ($exchangeRate) {
                return $product->cost_usd * $product->stock * $exchangeRate->buy_rate;
            }),
            'categories' => Product::active()
                ->selectRaw('category, COUNT(*) as count')
                ->groupBy('category')
                ->get(),
            'brands' => Product::active()
                ->selectRaw('brand, COUNT(*) as count')
                ->groupBy('brand')
                ->orderByDesc('count')
                ->limit(10)
                ->get(),
        ];
        
        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}
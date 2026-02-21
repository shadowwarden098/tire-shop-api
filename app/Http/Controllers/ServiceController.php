<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\ServiceRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ServiceController extends Controller
{
    /**
     
     */
    public function index(Request $request)
    {
        $query = Service::query();
        
        if ($request->has('search')) {
            $query->search($request->search);
        }
        
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }
        
        if ($request->has('active')) {
            $query->where('is_active', $request->active);
        }
        
        $services = $query->orderBy('name')->get();
        
        return response()->json([
            'success' => true,
            'data' => $services
        ]);
    }

    /**
     * Crear servicio
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:services,code|max:20',
            'description' => 'nullable|string',
            'price_pen' => 'required|numeric|min:0',
            'duration_minutes' => 'nullable|integer|min:0',
            'category' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $service = Service::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Servicio creado exitosamente',
            'data' => $service
        ], 201);
    }

    /**
     * Ver servicio
     */
    public function show($id)
    {
        $service = Service::with('serviceRecords')->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $service,
            'stats' => [
                'total_performed' => $service->getTotalTimesPerformed(),
                'total_revenue' => $service->getTotalRevenue(),
            ]
        ]);
    }

    /**
     * Actualizar servicio
     */
    public function update(Request $request, $id)
    {
        $service = Service::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|required|string|max:20|unique:services,code,' . $id,
            'description' => 'nullable|string',
            'price_pen' => 'sometimes|required|numeric|min:0',
            'duration_minutes' => 'nullable|integer|min:0',
            'category' => 'sometimes|required|string|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $service->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Servicio actualizado exitosamente',
            'data' => $service
        ]);
    }

    /**
     * Eliminar servicio
     */
    public function destroy($id)
    {
        $service = Service::findOrFail($id);
        $service->delete();

        return response()->json([
            'success' => true,
            'message' => 'Servicio eliminado exitosamente'
        ]);
    }

    /**
     * Listar registros de servicios realizados
     */
    public function records(Request $request)
    {
        $query = ServiceRecord::with(['customer', 'vehicle', 'service', 'creator']);
        
        if ($request->has('search')) {
            $query->search($request->search);
        }
        
        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }
        
        if ($request->has('service_id')) {
            $query->where('service_id', $request->service_id);
        }
        
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
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
        
        $sortBy = $request->get('sort_by', 'service_date');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);
        
        $perPage = $request->get('per_page', 15);
        $records = $query->paginate($perPage);
        
        return response()->json([
            'success' => true,
            'data' => $records
        ]);
    }

    /**
     * Registrar servicio realizado
     */
    public function storeRecord(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id',
            'vehicle_id' => 'required|exists:vehicles,id',
            'service_id' => 'required|exists:services,id',
            'price_pen' => 'required|numeric|min:0',
            'discount_pen' => 'nullable|numeric|min:0',
            'payment_method' => 'required|string|in:efectivo,tarjeta,transferencia',
            'mileage' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
            'technician_notes' => 'nullable|string',
            'service_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $discount = $request->discount_pen ?? 0;
        $total = $request->price_pen - $discount;

        $record = ServiceRecord::create([
            'customer_id' => $request->customer_id,
            'vehicle_id' => $request->vehicle_id,
            'service_id' => $request->service_id,
            'price_pen' => $request->price_pen,
            'discount_pen' => $discount,
            'total_pen' => $total,
            'payment_method' => $request->payment_method,
            'status' => 'completed',
            'mileage' => $request->mileage,
            'notes' => $request->notes,
            'technician_notes' => $request->technician_notes,
            'service_date' => $request->service_date ?? Carbon::now(),
            'created_by' => auth()->id(),
        ]);

        $record->load(['customer', 'vehicle', 'service']);

        return response()->json([
            'success' => true,
            'message' => 'Servicio registrado exitosamente',
            'data' => $record
        ], 201);
    }

    /**
     * Ver registro de servicio
     */
    public function showRecord($id)
    {
        $record = ServiceRecord::with(['customer', 'vehicle', 'service', 'creator'])
            ->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $record
        ]);
    }
}
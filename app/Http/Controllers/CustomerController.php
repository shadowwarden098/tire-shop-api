<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CustomerController extends Controller
{
    /**
     * Listar clientes
     */
    public function index(Request $request)
    {
        $query = Customer::with(['vehicles', 'sales', 'serviceRecords']);
        
        if ($request->has('search')) {
            $query->search($request->search);
        }
        
        if ($request->has('customer_type')) {
            $query->where('customer_type', $request->customer_type);
        }
        
        if ($request->has('active')) {
            $query->where('is_active', $request->active);
        }
        
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);
        
        $perPage = $request->get('per_page', 15);
        $customers = $query->paginate($perPage);
        
        return response()->json([
            'success' => true,
            'data' => $customers
        ]);
    }

    /**
     * Crear cliente
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'document_type' => 'required|string|in:DNI,RUC,CE',
            'document_number' => 'required|string|unique:customers,document_number',
            'email' => 'nullable|email|max:255',
            'phone' => 'required|string|max:20',
            'phone_secondary' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'birth_date' => 'nullable|date',
            'customer_type' => 'required|in:individual,company',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $customer = Customer::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Cliente creado exitosamente',
            'data' => $customer
        ], 201);
    }

    /**
     * Ver cliente
     */
    public function show($id)
    {
        $customer = Customer::with(['vehicles', 'sales.items.product', 'serviceRecords.service'])
            ->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $customer,
            'stats' => [
                'total_purchases' => $customer->total_purchases,
                'total_services' => $customer->total_services,
                'is_frequent' => $customer->isFrequentCustomer(),
            ]
        ]);
    }

    /**
     * Actualizar cliente
     */
    public function update(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'document_type' => 'sometimes|required|string|in:DNI,RUC,CE',
            'document_number' => 'sometimes|required|string|unique:customers,document_number,' . $id,
            'email' => 'nullable|email|max:255',
            'phone' => 'sometimes|required|string|max:20',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $customer->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Cliente actualizado exitosamente',
            'data' => $customer
        ]);
    }

    /**
     * Eliminar cliente
     */
    public function destroy($id)
    {
        $customer = Customer::findOrFail($id);
        $customer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cliente eliminado exitosamente'
        ]);
    }
}
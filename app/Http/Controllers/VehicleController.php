<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    /**
     * Listar todos los vehículos
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => Vehicle::with('customer')->get()
        ]);
    }

    /**
     * Crear vehículo
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id'  => 'required|exists:customers,id',
            'plate'        => 'required|unique:vehicles,plate',
            'brand'        => 'required|string|max:100',
            'model'        => 'required|string|max:100',
            'year'         => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'color'        => 'nullable|string|max:50',
            'mileage'      => 'nullable|integer|min:0',
            'tire_size'    => 'nullable|string|max:50',
            'vehicle_type' => 'nullable|string|max:50',
        ]);

        $validated['tire_size']    = $validated['tire_size']    ?? 'N/A';
        $validated['vehicle_type'] = $validated['vehicle_type'] ?? 'Automóvil';

        $vehicle = Vehicle::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Vehículo creado exitosamente',
            'data'    => $vehicle->load('customer')
        ], 201);
    }

    /**
     * Ver vehículo por ID
     */
    public function show($id)
    {
        $vehicle = Vehicle::with(['customer', 'serviceRecords.service'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $vehicle,
            'stats'   => [
                'total_services' => $vehicle->getTotalServicesCount(),
                'last_service'   => $vehicle->getLastService(),
            ]
        ]);
    }

    /**
     * Actualizar vehículo
     */
    public function update(Request $request, $id)
    {
        $vehicle = Vehicle::findOrFail($id);

        $validated = $request->validate([
            'plate'        => 'sometimes|required|unique:vehicles,plate,' . $id,
            'brand'        => 'sometimes|required|string|max:100',
            'model'        => 'sometimes|required|string|max:100',
            'year'         => 'sometimes|required|integer|min:1900|max:' . (date('Y') + 1),
            'color'        => 'nullable|string|max:50',
            'mileage'      => 'nullable|integer|min:0',
            'tire_size'    => 'nullable|string|max:50',
            'vehicle_type' => 'nullable|string|max:50',
        ]);

        $vehicle->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Vehículo actualizado exitosamente',
            'data'    => $vehicle->load('customer')
        ]);
    }

    /**
     * Eliminar vehículo
     */
    public function destroy($id)
    {
        $vehicle = Vehicle::findOrFail($id);
        $vehicle->delete();

        return response()->json([
            'success' => true,
            'message' => 'Vehículo eliminado exitosamente'
        ]);
    }
}
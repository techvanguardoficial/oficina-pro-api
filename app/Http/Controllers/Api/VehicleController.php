<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\AuthorizesCompany;
use App\Http\Traits\ChecksPlanLimits;
use App\Http\Traits\HasRoleAndPermissions;
use App\Models\Vehicle;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    use AuthorizesCompany, ChecksPlanLimits, HasRoleAndPermissions;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //$this->authorizePermission('view_vehicles');

        $vehicles = Vehicle::with(['client', 'carModel', 'company'])
            ->paginate(15);
        return response()->json($vehicles);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //$this->authorizePermission('create_vehicle');

        // Check plan limit for vehicles
        $limitCheck = $this->checkPlanLimit('vehicles');
        if ($limitCheck) {
            return $limitCheck;
        }

        $validated = $request->validate([
            'placa' => 'required|string|max:7|unique:vehicles,placa',
            'info' => 'nullable|string|max:255',
            'chassis' => 'nullable|string|max:255|unique:vehicles,chassis',
            'year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'color' => 'nullable|string|max:255',
            'km' => 'nullable|numeric|min:0',
            'car_models_id' => 'required|exists:car_models,id',
            'clients_id' => 'required|exists:clients,id',
        ]);

        // Get company from authenticated user
        $validated['company_id'] = $request->user()->company_id;

        $vehicle = Vehicle::create($validated);
        $vehicle->load(['client', 'carModel', 'company']);

        return response()->json($vehicle, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Vehicle $vehicle)
    {
        //$this->authorizePermission('view_vehicles');

        $this->authorizeCompany($vehicle);
        return response()->json($vehicle->load(['client', 'carModel.maker', 'company', 'orderServices', 'mileages']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Vehicle $vehicle)
    {
        $this->authorizeCompany($vehicle);
        $this->authorizePermission('edit_vehicle');

        $validated = $request->validate([
            'placa' => 'sometimes|string|max:7|unique:vehicles,placa,' . $vehicle->placa . ',placa',
            'info' => 'nullable|string|max:255',
            'chassis' => 'nullable|string|max:255|unique:vehicles,chassis,' . $vehicle->placa . ',placa',
            'year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'color' => 'nullable|string|max:255',
            'km' => 'nullable|numeric|min:0',
            'car_models_id' => 'sometimes|exists:car_models,id',
            'clients_id' => 'nullable|exists:clients,id',
        ]);

        $vehicle->update($validated);
        $vehicle->load(['client', 'carModel', 'company']);

        return response()->json($vehicle);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Vehicle $vehicle)
    {
        //$this->authorizePermission('delete_vehicle');

        $this->authorizeCompany($vehicle);
        $vehicle->delete();

        return response()->json(null, 204);
    }
}

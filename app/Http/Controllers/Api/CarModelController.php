<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CarModel;
use App\Models\CarMaker;
use Illuminate\Http\Request;

class CarModelController extends Controller
{
    /**
     * Display a listing of all car models.
     */
    public function index()
    {
        $carModels = CarModel::with('maker')->get();
        return response()->json($carModels);
    }

    /**
     * Get car models by maker ID.
     */
    public function getByMaker($makerId)
    {
        $maker = CarMaker::findOrFail($makerId);
        $carModels = CarModel::where('car_makers_id', $makerId)->get();

        return response()->json([
            'maker' => $maker,
            'models' => $carModels,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'model' => 'required|string|max:100',
            'car_makers_id' => 'required|exists:car_makers,id',
        ]);

        $carModel = CarModel::create($validated);

        return response()->json($carModel, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $carModel = CarModel::with('maker')->findOrFail($id);
        return response()->json($carModel);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $carModel = CarModel::findOrFail($id);

        $validated = $request->validate([
            'model' => 'sometimes|string|max:100',
            'car_makers_id' => 'sometimes|exists:car_makers,id',
        ]);

        $carModel->update($validated);

        return response()->json($carModel);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $carModel = CarModel::findOrFail($id);
        $carModel->delete();

        return response()->json(null, 204);
    }
}

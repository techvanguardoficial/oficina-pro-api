<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CarModel;
use App\Models\CarMaker;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
     * Cadastra modelo na fabricante. Compartilhado entre oficinas, como a fabricante.
     */
    public function storeForMaker(Request $request, CarMaker $carMaker)
    {
        if (is_string($request->input('model'))) {
            $request->merge(['model' => CarMaker::normalizeName($request->input('model'))]);
        }

        $validated = $request->validate([
            'model' => [
                'required', 'string', 'max:100',
                Rule::unique('car_models', 'model')
                    ->where('car_makers_id', $carMaker->id)
                    ->whereNull('deleted_at'),
            ],
        ], [
            'model.required' => 'Informe o nome do modelo.',
            'model.max' => 'O nome do modelo pode ter no máximo 100 caracteres.',
            'model.unique' => "Esse modelo já está cadastrado em {$carMaker->manufacturer}.",
        ]);

        $carModel = CarModel::create([
            'model' => $validated['model'],
            'car_makers_id' => $carMaker->id,
        ]);

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

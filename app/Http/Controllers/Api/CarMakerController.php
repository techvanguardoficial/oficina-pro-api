<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CarMaker;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CarMakerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $carMakers = CarMaker::with('models')->orderBy('manufacturer')->get();
        return response()->json($carMakers);
    }

    /**
     * Cadastra fabricante. O cadastro é compartilhado: qualquer oficina vê.
     * Por isso não há edição nem exclusão pela API.
     */
    public function store(Request $request)
    {
        if (is_string($request->input('manufacturer'))) {
            $request->merge(['manufacturer' => CarMaker::normalizeName($request->input('manufacturer'))]);
        }

        $validated = $request->validate([
            'manufacturer' => [
                'required', 'string', 'max:100',
                Rule::unique('car_makers', 'manufacturer')->whereNull('deleted_at'),
            ],
        ], [
            'manufacturer.required' => 'Informe o nome da fabricante.',
            'manufacturer.max' => 'O nome da fabricante pode ter no máximo 100 caracteres.',
            'manufacturer.unique' => 'Essa fabricante já está cadastrada.',
        ]);

        $carMaker = CarMaker::create($validated);

        return response()->json($carMaker->load('models'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $carMaker = CarMaker::findOrFail($id);
        return response()->json($carMaker);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $carMaker = CarMaker::findOrFail($id);

        $validated = $request->validate([
            'manufacturer' => 'required|string|max:255|unique:car_makers,manufacturer,' . $carMaker->id,
        ]);

        $carMaker->update($validated);

        return response()->json($carMaker);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $carMaker = CarMaker::findOrFail($id);
        $carMaker->delete();

        return response()->json(null, 204);
    }
}

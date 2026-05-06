<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CarMaker;
use Illuminate\Http\Request;

class CarMakerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $carMakers = CarMaker::all();
        return response()->json($carMakers);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'manufacturer' => 'required|string|max:255|unique:car_makers',
        ]);

        $carMaker = CarMaker::create($validated);

        return response()->json($carMaker, 201);
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

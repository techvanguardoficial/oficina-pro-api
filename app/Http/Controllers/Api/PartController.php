<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Part;
use Illuminate\Http\Request;

class PartController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $parts = Part::with(['orderService'])
            ->paginate(15);
        return response()->json($parts);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'description' => 'required|string|max:255',
            'quantity' => 'required|numeric|min:0.01',
            'unit_price' => 'required|numeric|min:0',
            'information' => 'nullable|string',
            'orders_id' => 'nullable|exists:order_services,id',
        ]);

        // Price will be calculated automatically by the model
        $part = Part::create($validated);
        $part->load(['orderService']);

        return response()->json($part, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Part $part)
    {
        return response()->json($part->load(['orderService']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Part $part)
    {
        $validated = $request->validate([
            'description' => 'sometimes|string|max:255',
            'quantity' => 'sometimes|numeric|min:0.01',
            'unit_price' => 'sometimes|numeric|min:0',
            'information' => 'nullable|string',
            'orders_id' => 'nullable|exists:order_services,id',
        ]);

        $part->update($validated);
        $part->load(['orderService']);

        return response()->json($part);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Part $part)
    {
        $part->delete();

        return response()->json(null, 204);
    }
}

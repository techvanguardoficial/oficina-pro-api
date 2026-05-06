<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderType;
use Illuminate\Http\Request;

class OrderTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $orderTypes = OrderType::all();
        return response()->json($orderTypes);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string|max:255|unique:orders_types,type',
        ]);

        $orderType = OrderType::create($validated);

        return response()->json($orderType, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(OrderType $orderType)
    {
        return response()->json($orderType);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, OrderType $orderType)
    {
        $validated = $request->validate([
            'type' => 'sometimes|string|max:255|unique:orders_types,type,' . $orderType->id,
        ]);

        $orderType->update($validated);

        return response()->json($orderType);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(OrderType $orderType)
    {
        $orderType->delete();

        return response()->json(null, 204);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderStatus;
use Illuminate\Http\Request;

class OrderStatusController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $orderStatuses = OrderStatus::all();
        return response()->json($orderStatuses);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'status' => 'required|string|max:255|unique:orders_status,status',
        ]);

        $orderStatus = OrderStatus::create($validated);

        return response()->json($orderStatus, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(OrderStatus $orderStatus)
    {
        return response()->json($orderStatus);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, OrderStatus $orderStatus)
    {
        $validated = $request->validate([
            'status' => 'sometimes|string|max:255|unique:orders_status,status,' . $orderStatus->id,
        ]);

        $orderStatus->update($validated);

        return response()->json($orderStatus);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(OrderStatus $orderStatus)
    {
        $orderStatus->delete();

        return response()->json(null, 204);
    }
}

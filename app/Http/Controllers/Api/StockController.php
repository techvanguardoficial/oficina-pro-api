<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\AuthorizesCompany;
use App\Models\Stock;
use Illuminate\Http\Request;

class StockController extends Controller
{
    use AuthorizesCompany;
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $stocks = Stock::with(['company', 'category', 'supplier'])
            ->where('company_id', auth()->user()->company_id)
            ->paginate(15);
        return response()->json($stocks);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:255|unique:stocks',
            'name' => 'required|string|max:255',
            'unit_of_measurement' => 'required|string|max:50',
            'current_stock' => 'required|integer|min:0',
            'minimum_stock' => 'required|integer|min:0',
            'purchase_price' => 'required|numeric|min:0',
            'sale_price' => 'required|numeric|min:0',
            'category_id' => 'required|exists:categories,id',
            'supplier_id' => 'required|exists:suppliers,id',
        ]);

        $validated['company_id'] = auth()->user()->company_id;

        $stock = Stock::create($validated);
        $stock->load(['company', 'category', 'supplier']);

        return response()->json($stock, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Stock $stock)
    {
        $this->authorizeCompany($stock);
        return response()->json($stock->load(['company', 'category', 'supplier']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Stock $stock)
    {
        $this->authorizeCompany($stock);

        $validated = $request->validate([
            'code' => 'sometimes|string|max:255|unique:stocks,code,' . $stock->id,
            'name' => 'sometimes|string|max:255',
            'unit_of_measurement' => 'sometimes|string|max:50',
            'current_stock' => 'sometimes|integer|min:0',
            'minimum_stock' => 'sometimes|integer|min:0',
            'purchase_price' => 'sometimes|numeric|min:0',
            'sale_price' => 'sometimes|numeric|min:0',
            'category_id' => 'sometimes|exists:categories,id',
            'supplier_id' => 'sometimes|exists:suppliers,id',
        ]);

        $stock->update($validated);
        $stock->load(['company', 'category', 'supplier']);

        return response()->json($stock);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Stock $stock)
    {
        $this->authorizeCompany($stock);
        $stock->delete();

        return response()->json(null, 204);
    }
}

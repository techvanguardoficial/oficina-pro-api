<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\AuthorizesCompany;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    use AuthorizesCompany;
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $suppliers = Supplier::with(['company', 'stocks'])
            ->where('company_id', auth()->user()->company_id)
            ->paginate(15);
        return response()->json($suppliers);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email',
        ]);

        $validated['company_id'] = auth()->user()->company_id;

        $supplier = Supplier::create($validated);
        $supplier->load(['company', 'stocks']);

        return response()->json($supplier, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Supplier $supplier)
    {
        $this->authorizeCompany($supplier);
        return response()->json($supplier->load(['company', 'stocks']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Supplier $supplier)
    {
        $this->authorizeCompany($supplier);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email',
        ]);

        $supplier->update($validated);
        $supplier->load(['company', 'stocks']);

        return response()->json($supplier);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Supplier $supplier)
    {
        $this->authorizeCompany($supplier);
        $supplier->delete();

        return response()->json(null, 204);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\AuthorizesCompany;
use App\Http\Traits\HasRoleAndPermissions;
use App\Models\Income;
use Illuminate\Http\Request;

class IncomeController extends Controller
{
    use AuthorizesCompany, HasRoleAndPermissions;
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //$this->authorizePermission('view_income');

        $incomes = Income::paginate(20);
        return response()->json($incomes);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorizePermission('create_income');

        $validated = $request->validate([
            'description' => 'required|string|max:255',
            'value' => 'required|numeric|min:0.01',
            'date' => 'required|date',
        ]);

        // Get company from authenticated user
        $validated['company_id'] = $request->user()->company_id;

        $income = Income::create($validated);

        return response()->json($income, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Income $income)
    {
        //$this->authorizePermission('view_income');

        $this->authorizeCompany($income);
        return response()->json($income);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Income $income)
    {
        $this->authorizeCompany($income);
        $this->authorizePermission('edit_income');

        $validated = $request->validate([
            'description' => 'sometimes|string|max:255',
            'value' => 'sometimes|numeric|min:0.01',
            'date' => 'sometimes|date',
        ]);

        $income->update($validated);

        return response()->json($income);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Income $income)
    {
        //$this->authorizePermission('delete_income');

        $this->authorizeCompany($income);
        $income->delete();

        return response()->json(null, 204);
    }
}

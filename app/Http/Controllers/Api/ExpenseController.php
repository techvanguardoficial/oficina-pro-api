<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\AuthorizesCompany;
use App\Http\Traits\HasRoleAndPermissions;
use App\Models\Expense;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    use AuthorizesCompany, HasRoleAndPermissions;
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //$this->authorizePermission('view_expense');

        $expenses = Expense::paginate(20);
        return response()->json($expenses);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //$this->authorizePermission('create_expense');

        $validated = $request->validate([
            'description' => 'required|string|max:255',
            'value' => 'required|numeric|min:0.01',
            'date' => 'required|date',
        ]);

        // Get company from authenticated user
        $validated['company_id'] = $request->user()->company_id;

        $expense = Expense::create($validated);

        return response()->json($expense, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Expense $expense)
    {
        //$this->authorizePermission('view_expense');

        $this->authorizeCompany($expense);
        return response()->json($expense);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Expense $expense)
    {
        //$this->authorizePermission('edit_expense');

        $this->authorizeCompany($expense);

        $validated = $request->validate([
            'description' => 'sometimes|string|max:255',
            'value' => 'sometimes|numeric|min:0.01',
            'date' => 'sometimes|date',
        ]);

        $expense->update($validated);

        return response()->json($expense);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Expense $expense)
    {
        //$this->authorizePermission('delete_expense');

        $this->authorizeCompany($expense);
        $expense->delete();

        return response()->json(null, 204);
    }
}

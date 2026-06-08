<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExpenseType;
use Illuminate\Http\Request;

class ExpenseTypeController extends Controller
{
    public function index()
    {
        return response()->json(ExpenseType::all());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string|max:255|unique:expense_types,type',
        ]);

        $expenseType = ExpenseType::create($validated);

        return response()->json($expenseType, 201);
    }

    public function update(Request $request, ExpenseType $expenseType)
    {
        $validated = $request->validate([
            'type' => 'required|string|max:255|unique:expense_types,type,' . $expenseType->id,
        ]);

        $expenseType->update($validated);

        return response()->json($expenseType);
    }

    public function destroy(ExpenseType $expenseType)
    {
        $expenseType->delete();

        return response()->json(null, 204);
    }
}

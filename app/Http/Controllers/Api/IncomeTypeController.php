<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IncomeType;
use Illuminate\Http\Request;

class IncomeTypeController extends Controller
{
    public function index()
    {
        return response()->json(IncomeType::all());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string|max:255|unique:income_types,type',
        ]);

        $incomeType = IncomeType::create($validated);

        return response()->json($incomeType, 201);
    }

    public function update(Request $request, IncomeType $incomeType)
    {
        $validated = $request->validate([
            'type' => 'required|string|max:255|unique:income_types,type,' . $incomeType->id,
        ]);

        $incomeType->update($validated);

        return response()->json($incomeType);
    }

    public function destroy(IncomeType $incomeType)
    {
        $incomeType->delete();

        return response()->json(null, 204);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function show(Request $request)
    {
        $company = $request->user()->company;
        return response()->json($company);
    }

    public function update(Request $request)
    {
        $company = $request->user()->company;

        $validated = $request->validate([
            'name'         => 'sometimes|string|max:255',
            'fantasy_name' => 'sometimes|string|max:255|nullable',
            'email'        => 'sometimes|email|max:255|nullable',
            'phone'        => 'sometimes|string|max:30|nullable',
        ]);

        $company->update($validated);

        return response()->json(['message' => 'Configurações salvas com sucesso.', 'company' => $company]);
    }
}

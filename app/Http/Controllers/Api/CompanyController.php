<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompanyController extends Controller
{
    public function show(Request $request)
    {
        return response()->json($request->user()->company);
    }

    public function update(Request $request)
    {
        $company = $request->user()->company;

        $validated = $request->validate([
            'name'         => 'sometimes|string|max:255',
            'fantasy_name' => 'sometimes|string|max:255|nullable',
            'email'        => 'sometimes|email|max:255|nullable',
            'phone'        => 'sometimes|string|max:30|nullable',
            'address'      => 'sometimes|string|max:255|nullable',
            'cnpj'         => ['sometimes', 'nullable', 'string', 'max:14',
                               \Illuminate\Validation\Rule::unique('companies', 'cnpj')->ignore($company->id)],
        ]);

        $company->update($validated);

        return response()->json([
            'message' => 'Configurações salvas com sucesso.',
            'company' => $company->fresh(),
        ]);
    }

    public function uploadLogo(Request $request)
    {
        $request->validate(['logo' => 'required|image|max:5120']);

        $company = $request->user()->company;

        if ($company->logo) {
            Storage::disk('supabase')->delete($company->logo);
        }

        $path = $request->file('logo')->store('company-logos', 'supabase');
        $company->update(['logo' => $path]);

        return response()->json(['logo_url' => $company->fresh()->logo_url]);
    }
}

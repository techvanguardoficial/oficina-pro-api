<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Phone;
use Illuminate\Http\Request;

class PhoneController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $phones = Phone::all();
        return response()->json($phones);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'phone_one' => 'required|string|max:20',
            'phone_two' => 'nullable|string|max:20',
            'phone_three' => 'nullable|string|max:20',
            'clients_id' => 'required|exists:clients,id',
        ]);

        $phone = Phone::create($validated);

        return response()->json($phone, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $phone = Phone::findOrFail($id);
        return response()->json($phone);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $phone = Phone::findOrFail($id);

        $validated = $request->validate([
            'phone_one' => 'sometimes|string|max:20',
            'phone_two' => 'nullable|string|max:20',
            'phone_three' => 'nullable|string|max:20',
            'clients_id' => 'sometimes|exists:clients,id',
        ]);

        $phone->update($validated);

        return response()->json($phone);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $phone = Phone::findOrFail($id);
        $phone->delete();

        return response()->json(null, 204);
    }
}

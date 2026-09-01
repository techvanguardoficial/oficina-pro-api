<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\AuthorizesCompany;
use App\Http\Traits\ChecksPlanLimits;
use App\Http\Traits\HasRoleAndPermissions;
use App\Models\Client;
use App\Models\Phone;
use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    use AuthorizesCompany, ChecksPlanLimits, HasRoleAndPermissions;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //$this->authorizePermission('view_clients');

        $query = Client::with(['address', 'phone', 'vehicles']);

        if ($request->filled('search')) {
            $term = trim($request->input('search'));
            $words = array_filter(explode(' ', $term));

            $query->where(function ($q) use ($term, $words) {
                // Termo completo num único campo (ex: "Robson")
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('lastname', 'like', "%{$term}%");

                // Cada palavra deve aparecer em name ou lastname
                // (cobre "Robson Gomes Pedreira" dividido entre os dois campos)
                if (count($words) > 1) {
                    $q->orWhere(function ($sub) use ($words) {
                        foreach ($words as $word) {
                            $sub->where(function ($inner) use ($word) {
                                $inner->where('name', 'like', "%{$word}%")
                                      ->orWhere('lastname', 'like', "%{$word}%");
                            });
                        }
                    });
                }
            });
        }

        $clients = $query->paginate($request->input('per_page', 15));
        return response()->json($clients);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //$this->authorizePermission('create_client');

        // Check plan limit for clients
        $limitCheck = $this->checkPlanLimit('clients');
        if ($limitCheck) {
            return $limitCheck;
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:clients',
            'cpf_cnpj' => 'required|string|max:20|unique:clients',
            'status' => 'boolean',
            'phone_one' => 'nullable|string|max:20',
            'phone_two' => 'nullable|string|max:20',
            'phone_three' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'number' => 'nullable|string|max:20',
            'complement' => 'nullable|string|max:255',
            'zipcode' => 'nullable|string|max:20',
            'district' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'uf' => 'nullable|string|max:2',
        ]);

        // Assign company from authenticated user
        $validated['company_id'] = $request->user()->company_id;

        if (isset($validated['status'])) {
            $validated['status'] = $validated['status'] ? '1' : '0';
        } else {
            $validated['status'] = '1'; // Default active
        }

        $client = Client::create($validated);

        // Create phone if provided
        if ($this->hasPhoneData($validated)) {
            Phone::create([
                'clients_id' => $client->id,
                'phone_one' => $validated['phone_one'] ?? null,
                'phone_two' => $validated['phone_two'] ?? null,
                'phone_three' => $validated['phone_three'] ?? null,
            ]);
        }

        // Create address if provided
        if ($this->hasAddressData($validated)) {
            Address::create([
                'clients_id' => $client->id,
                'address' => $validated['address'] ?? null,
                'number' => $validated['number'] ?? null,
                'complement' => $validated['complement'] ?? null,
                'zipcode' => $validated['zipcode'] ?? null,
                'district' => $validated['district'] ?? null,
                'city' => $validated['city'] ?? null,
                'uf' => $validated['uf'] ?? null,
            ]);
        }

        return response()->json([
            'message' => 'Cliente cadastrado com sucesso',
            'client_id' => $client->id,
            'client' => $client->load(['phone', 'address']),
        ], 201);
    }

    private function hasPhoneData(array $data): bool
    {
        return isset($data['phone_one']) || isset($data['phone_two']) || isset($data['phone_three']);
    }

    private function hasAddressData(array $data): bool
    {
        return isset($data['address']) || isset($data['number']) || isset($data['zipcode']) || isset($data['city']);
    }

    /**
     * Display the specified resource.
     */
    public function show(Client $client)
    {
        //$this->authorizePermission('view_clients');

        $this->authorizeCompany($client);
        return response()->json($client->load(['address', 'phone', 'vehicles', 'vehicles.carModel.maker', 'vehicles.mileages']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Client $client)
    {
        //$this->authorizePermission('edit_client');

        $this->authorizeCompany($client);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'lastname' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('clients')->ignore($client->id)],
            'cpf_cnpj' => ['sometimes', 'string', 'max:20', Rule::unique('clients')->ignore($client->id)],
            'status' => 'sometimes|boolean',
        ]);

        if (isset($validated['status'])) {
            $validated['status'] = $validated['status'] ? '1' : '0';
        }

        $client->update($validated);

        return response()->json($client);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Client $client)
    {
        //$this->authorizePermission('delete_client');

        $this->authorizeCompany($client);
        $client->delete();

        return response()->json(null, 204);
    }

    public function getVehicles(Client $client)
    {
        //$this->authorizePermission('view_clients');
        //$this->authorizeCompany($client);

        $vehicles = $client->vehicles()->with(['carModel.maker', 'company'])->get();

        return response()->json([
            'client_id' => $client->id,
            'client_name' => $client->getFullNameAttribute(),
            'vehicles' => $vehicles,
        ]);
    }
}

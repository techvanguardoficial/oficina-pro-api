<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\AuthorizesCompany;
use App\Models\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    use AuthorizesCompany;
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $services = Service::with(['orderService'])->paginate(15);
        return response()->json($services);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'description' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'information' => 'nullable|string',
            'orders_id' => 'nullable|exists:order_services,id',
        ]);

        // Get company from authenticated user
        $validated['company_id'] = $request->user()->company_id;

        $service = Service::create($validated);
        $service->load(['orderService']);

        return response()->json($service, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Service $service)
    {
        $this->authorizeCompany($service);
        return response()->json($service->load(['orderService']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Service $service)
    {
        $this->authorizeCompany($service);

        $validated = $request->validate([
            'description' => 'sometimes|string|max:255',
            'price' => 'sometimes|numeric|min:0',
            'information' => 'nullable|string',
            'orders_id' => 'nullable|exists:order_services,id',
        ]);

        $service->update($validated);
        $service->load(['orderService']);

        return response()->json($service);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Service $service)
    {
        $this->authorizeCompany($service);
        $service->delete();

        return response()->json(null, 204);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\AuthorizesCompany;
use App\Http\Traits\ChecksPlanLimits;
use App\Http\Traits\HasRoleAndPermissions;
use App\Models\OrderService;
use App\Models\Part;
use App\Models\Service;
use App\Models\CarMileage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderServiceController extends Controller
{
    use AuthorizesCompany, ChecksPlanLimits, HasRoleAndPermissions;

    public function index(Request $request)
    {
        $query = OrderService::with(['vehicle.carModel.maker', 'type', 'status', 'parts', 'services', 'mileages'])
            ->where('company_id', auth()->user()->company_id)
            ->orderBy('id', 'desc');

        if ($request->filled('placa')) {
            // `vehicle_placa` foi removida da tabela na migração 2026_06_15_000004.
            // Resolvemos o vehicle_id pelo par (company_id, placa) e filtramos por ele.
            $companyId = auth()->user()->company_id;
            $vehicleId = \App\Models\Vehicle::withoutGlobalScope(\App\Models\Scopes\CompanyScope::class)
                ->where('company_id', $companyId)
                ->whereRaw('UPPER(placa) = ?', [strtoupper($request->input('placa'))])
                ->value('id');

            if ($vehicleId) {
                $query->where('vehicle_id', $vehicleId);
            } else {
                // Placa não encontrada nesta empresa — retorna vazio
                $query->whereRaw('1 = 0');
            }
        }

        return response()->json($query->paginate(25));
    }

    public function listAll()
    {
        $query = OrderService::with(['vehicle.carModel.maker', 'type', 'status', 'parts', 'services', 'mileages', 'vehicle.client'])
            ->where('company_id', auth()->user()->company_id)
            ->orderBy('id', 'desc');

        return response()->json($query->paginate(25));
    }

    public function store(Request $request)
    {
        $limitCheck = $this->checkPlanLimit('orders');
        if ($limitCheck) {
            return $limitCheck;
        }

        $validated = $request->validate([
            'vehicle_placa' => [
                'required',
                'string',
                \Illuminate\Validation\Rule::exists('vehicles', 'placa')
                    ->where('company_id', $request->user()->company_id),
            ],
            'orders_types_id' => 'required|integer|exists:orders_types,id',
            'orders_status_id' => 'required|integer|exists:orders_status,id',
            'info' => 'nullable|string|max:1000',
            'mileage' => 'nullable|numeric|min:0',
            'parts' => 'nullable|array',
            'parts.*.description' => 'required_with:parts|string|max:255',
            'parts.*.quantity' => 'required_with:parts|numeric|min:0',
            'parts.*.unit_price' => 'required_with:parts|numeric|min:0',
            'parts.*.information' => 'nullable|string|max:500',
            'services' => 'nullable|array',
            'services.*.description' => 'required_with:services|string|max:255',
            'services.*.price' => 'required_with:services|numeric|min:0',
            'services.*.information' => 'nullable|string|max:500',
        ]);

        $companyId = $request->user()->company_id;

        // `placa` não é mais identificador único globalmente — cada empresa
        // pode ter seu próprio veículo com a mesma placa. Resolvemos o
        // vehicle_id pelo par (company_id, placa) desta empresa.
        $vehicleId = \App\Models\Vehicle::withoutGlobalScope(\App\Models\Scopes\CompanyScope::class)
            ->where('company_id', $companyId)
            ->where('placa', $validated['vehicle_placa'])
            ->value('id');

        $order = DB::transaction(function () use ($validated, $companyId, $vehicleId) {
            $order = OrderService::create([
                'company_id' => $companyId,
                'vehicle_id' => $vehicleId,
                'orders_types_id' => $validated['orders_types_id'],
                'orders_status_id' => $validated['orders_status_id'],
                'info' => $validated['info'] ?? null,
            ]);

            foreach ($validated['parts'] ?? [] as $part) {
                Part::create([
                    'orders_id' => $order->id,
                    'company_id' => $companyId,
                    'description' => $part['description'],
                    'quantity' => $part['quantity'],
                    'unit_price' => $part['unit_price'],
                    'information' => $part['information'] ?? null,
                ]);
            }

            foreach ($validated['services'] ?? [] as $service) {
                Service::create([
                    'orders_id' => $order->id,
                    'company_id' => $companyId,
                    'description' => $service['description'],
                    'price' => $service['price'],
                    'information' => $service['information'] ?? null,
                ]);
            }

            if (!empty($validated['mileage'])) {
                CarMileage::create([
                    'order_services_id' => $order->id,
                    'vehicle_id' => $vehicleId,
                    'company_id' => $companyId,
                    'mileage' => $validated['mileage'],
                ]);
            }

            return $order;
        });

        return response()->json([
            'message' => 'Ordem de serviço criada com sucesso',
            'order' => $order->load(['vehicle', 'type', 'status', 'parts', 'services', 'mileages']),
        ], 201);
    }

    public function show(OrderService $orderService)
    {
        $this->authorizeCompany($orderService);

        return response()->json(
            $orderService->load(['vehicle', 'type', 'status', 'parts', 'services', 'mileages'])
        );
    }

    public function update(Request $request, OrderService $orderService)
    {
        $this->authorizeCompany($orderService);

        $validated = $request->validate([
            'orders_types_id' => 'sometimes|integer|exists:orders_types,id',
            'orders_status_id' => 'sometimes|integer|exists:orders_status,id',
            'info' => 'nullable|string|max:1000',
        ]);

        $orderService->update($validated);

        return response()->json(
            $orderService->load(['vehicle', 'type', 'status', 'parts', 'services', 'mileages'])
        );
    }

    public function destroy(OrderService $orderService)
    {
        $this->authorizeCompany($orderService);
        $orderService->delete();

        return response()->json(null, 204);
    }
}

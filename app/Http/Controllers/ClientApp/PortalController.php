<?php

namespace App\Http\Controllers\ClientApp;

use App\Http\Controllers\Controller;
use App\Models\ClientAppUser;
use App\Models\OrderService;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PortalController extends Controller
{
    private function appUser(): ClientAppUser
    {
        return auth('client')->user();
    }

    /**
     * Dados do usuário logado.
     */
    public function me()
    {
        $user = $this->appUser();
        return response()->json([
            'id'                   => $user->id,
            'name'                 => $user->name,
            'email'                => $user->email,
            'phone'                => $user->phone,
            'cpf'                  => $user->cpf,
            'onboarding_completed' => $user->onboarding_completed,
        ]);
    }

    /**
     * Atualiza dados pessoais.
     */
    public function updateMe(Request $request)
    {
        $user = $this->appUser();

        $request->validate([
            'name'     => 'sometimes|string|max:100',
            'email'    => 'sometimes|email|unique:client_app_users,email,' . $user->id,
            'phone'    => 'sometimes|string|max:20',
            'cpf'      => 'sometimes|nullable|string|max:14',
            'password' => 'sometimes|string|min:6|confirmed',
        ]);

        $data = $request->only(['name', 'email', 'phone', 'cpf']);
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return response()->json(['message' => 'Dados atualizados.', 'user' => $user->fresh()]);
    }

    /**
     * Lista todos os veículos vinculados ao usuário (cross-oficina).
     */
    public function vehicles()
    {
        $clientIds = $this->appUser()->clientIds();

        $vehicles = Vehicle::withoutGlobalScopes()
            ->whereIn('clients_id', $clientIds)
            ->with(['carModel.maker'])
            ->get()
            ->map(fn($v) => $this->vehicleResource($v));

        return response()->json($vehicles);
    }

    /**
     * Detalhe de um veículo pela placa.
     */
    public function vehicle(string $placa)
    {
        $clientIds = $this->appUser()->clientIds();

        $vehicle = Vehicle::withoutGlobalScopes()
            ->whereIn('clients_id', $clientIds)
            ->where('placa', strtoupper($placa))
            ->with(['carModel.maker'])
            ->firstOrFail();

        return response()->json($this->vehicleResource($vehicle));
    }

    /**
     * Histórico completo de manutenção de um veículo.
     */
    /**
     * Lista as oficinas que atenderam este veículo.
     */
    public function vehicleWorkshops(string $placa)
    {
        $clientIds = $this->appUser()->clientIds();

        $vehicle = Vehicle::withoutGlobalScopes()
            ->whereIn('clients_id', $clientIds)
            ->where('placa', strtoupper($placa))
            ->firstOrFail();

        $workshops = OrderService::withoutGlobalScopes()
            ->where('vehicle_id', $vehicle->id)
            ->with('company:id,fantasy_name,name')
            ->select('company_id')
            ->selectRaw('COUNT(*) as total_os')
            ->selectRaw('MAX(created_at) as last_visit')
            ->groupBy('company_id')
            ->orderByDesc('last_visit')
            ->get()
            ->map(fn($o) => [
                'company_id' => $o->company_id,
                'name'       => $o->company?->fantasy_name ?: $o->company?->name ?? 'Oficina',
                'total_os'   => $o->total_os,
                'last_visit' => $o->last_visit,
            ]);

        return response()->json([
            'vehicle'   => $this->vehicleResource($vehicle),
            'workshops' => $workshops,
        ]);
    }

    public function vehicleHistory(Request $request, string $placa)
    {
        $clientIds = $this->appUser()->clientIds();

        $vehicle = Vehicle::withoutGlobalScopes()
            ->whereIn('clients_id', $clientIds)
            ->where('placa', strtoupper($placa))
            ->firstOrFail();

        $perPage   = (int) $request->query('per_page', 15);
        $companyId = $request->query('company_id');

        $query = OrderService::withoutGlobalScopes()
            ->where('vehicle_id', $vehicle->id);

        if ($companyId) {
            $query->where('company_id', (int) $companyId);
        }

        $orders = $query
            ->with([
                'parts',
                'services' => fn($q) => $q->withoutGlobalScopes(),
                'status',
                'type',
                'mileages',
                'company:id,fantasy_name,name',
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'vehicle'      => $this->vehicleResource($vehicle),
            'history'      => collect($orders->items())->map(fn($o) => $this->orderResource($o)),
            'current_page' => $orders->currentPage(),
            'last_page'    => $orders->lastPage(),
            'total'        => $orders->total(),
        ]);
    }

    private function vehicleResource(Vehicle $v): array
    {
        return [
            'placa'  => $v->placa,
            'make'   => $v->carModel?->maker?->manufacturer ?? '',
            'model'  => $v->carModel?->model ?? '',
            'year'   => $v->year,
            'color'  => $v->color,
            'km'     => $v->current_km,
            'vin'    => $v->chassis,
        ];
    }

    private function orderResource(OrderService $o): array
    {
        // Mirror workshop frontend: quantity × unit_price (price field may be stale on old records)
        $totalParts    = $o->parts->sum(fn($p) => (float)$p->quantity * (float)$p->unit_price);
        $totalServices = $o->services->sum('price');

        return [
            'id'         => $o->id,
            'date'       => $o->created_at?->toDateString(),
            'status'     => $o->status?->status ?? '',
            'type'       => $o->type?->type ?? '',
            'km'         => $o->mileages->last()?->mileage ?? 0,
            'info'       => $o->info,
            'oficina'    => $o->company?->fantasy_name ?? $o->company?->name ?? '',
            'total'      => $totalParts + $totalServices,
            'parts'      => $o->parts->map(fn($p) => [
                'description' => $p->description,
                'quantity'    => (int) $p->quantity,
                'unit_value'  => (float) $p->unit_price,
                'total'       => (float) $p->quantity * (float) $p->unit_price,
            ]),
            'services'   => $o->services->map(fn($s) => [
                'description' => $s->description,
                'value'       => $s->price,
            ]),
        ];
    }
}

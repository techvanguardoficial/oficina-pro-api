<?php

namespace App\Http\Controllers\ClientApp;

use App\Http\Controllers\Controller;
use App\Models\Scopes\CompanyScope;
use App\Models\Vehicle;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    // Lista todos os veículos do cliente em todas as oficinas, agrupados por placa
    public function index(Request $request)
    {
        $clientIds = $request->user()->clientIds();

        $vehicles = Vehicle::withoutGlobalScope(CompanyScope::class)
            ->whereIn('clients_id', $clientIds)
            ->with(['carModel.carMaker'])
            ->get()
            ->groupBy('placa')
            ->map(function ($group) {
                $first = $group->first();
                return [
                    'placa'           => $first->placa,
                    'formatted_placa' => $first->formatted_placa,
                    'color'           => $first->color,
                    'year'            => $first->year,
                    'info'            => $first->info,
                    'current_km'      => $first->current_km,
                    'car_model'       => $first->carModel,
                    'workshops_count' => $group->count(),
                ];
            })
            ->values();

        return response()->json($vehicles);
    }

    // Detalhe do veículo com histórico de OS unificado de todas as oficinas
    public function show(Request $request, string $placa)
    {
        $clientIds = $request->user()->clientIds();

        // Placas são armazenadas em maiúsculas no banco — normalizar para
        // garantir match independente de como o app envia o parâmetro.
        // Como o mesmo veículo físico pode ter registros em várias oficinas
        // (company_id diferentes) após a migração de PK, usamos `whereIn`
        // nos client_ids do usuário para trazer todos os registros relevantes.
        $vehicles = Vehicle::withoutGlobalScope(CompanyScope::class)
            ->whereRaw('UPPER(placa) = ?', [strtoupper($placa)])
            ->whereIn('clients_id', $clientIds)
            ->with([
                'carModel.carMaker',
                'client.company:id,name,fantasy_name',
                'orderServices' => function ($q) {
                    // OrderService não tem CompanyScope, mas carregamos sem
                    // filtro de company para unificar o histórico cross-oficina.
                    $q->with(['status', 'type'])
                      ->orderByDesc('created_at');
                },
            ])
            ->get();

        if ($vehicles->isEmpty()) {
            return response()->json([
                'error'   => true,
                'message' => 'Veículo não encontrado.',
                'code'    => 'NOT_FOUND',
            ], 404);
        }

        $first      = $vehicles->first();
        $allOrders  = $vehicles->flatMap(fn($v) => $v->orderServices)
            ->sortByDesc('created_at')
            ->values();

        return response()->json([
            'placa'           => $first->placa,
            'formatted_placa' => $first->formatted_placa,
            'color'           => $first->color,
            'year'            => $first->year,
            'info'            => $first->info,
            'current_km'      => $first->current_km,
            'car_model'       => $first->carModel,
            'workshops'       => $vehicles->map(fn($v) => [
                'company_id'    => $v->company_id,
                'company_name'  => $v->client->company->fantasy_name ?? $v->client->company->name,
                'orders_count'  => $v->orderServices->count(),
            ]),
            'order_services'  => $allOrders,
        ]);
    }
}

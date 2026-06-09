<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Endpoints agregados para os gráficos do dashboard.
 *
 * Importante: os cálculos são feitos via GROUP BY no banco (não em memória
 * sobre uma página de resultados), garantindo que o "Top N" reflita o
 * histórico completo da empresa, não apenas a primeira página de ordens.
 *
 * Nota: os JOINs usam `os.vehicle_id = v.id` após a migração 2026_06_15_*
 * que substituiu a FK baseada em `placa` pela FK numérica `vehicle_id`.
 */
class DashboardController extends Controller
{
    /**
     * Modelos de veículos mais atendidos (quantidade de O.S. por modelo).
     * GET /api/v1/dashboard/top-vehicle-models?limit=5
     */
    public function topVehicleModels(Request $request)
    {
        $companyId = $request->user()->company_id;
        $limit = (int) $request->query('limit', 5);
        $limit = $limit > 0 ? min($limit, 50) : 5;

        $rows = DB::table('order_services as os')
            ->join('vehicles as v', 'v.id', '=', 'os.vehicle_id')
            ->join('car_models as cm', 'cm.id', '=', 'v.car_models_id')
            ->where('os.company_id', $companyId)
            ->whereNull('os.deleted_at')
            ->select('cm.model as model', DB::raw('COUNT(*) as total'))
            ->groupBy('cm.id', 'cm.model')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        return response()->json(
            $rows->map(fn ($r) => ['model' => $r->model, 'total' => (int) $r->total])
        );
    }

    /**
     * Clientes/veículos que mais estiveram na oficina (quantidade de O.S. por cliente).
     * GET /api/v1/dashboard/top-clients?limit=3
     */
    public function topClients(Request $request)
    {
        $companyId = $request->user()->company_id;
        $limit = (int) $request->query('limit', 3);
        $limit = $limit > 0 ? min($limit, 50) : 3;

        $rows = DB::table('order_services as os')
            ->join('vehicles as v', 'v.id', '=', 'os.vehicle_id')
            ->join('clients as c', 'c.id', '=', 'v.clients_id')
            ->where('os.company_id', $companyId)
            ->whereNull('os.deleted_at')
            ->select(
                'c.id as client_id',
                DB::raw("TRIM(CONCAT(c.name, ' ', COALESCE(c.lastname, ''))) as name"),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('c.id', 'c.name', 'c.lastname')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        return response()->json(
            $rows->map(fn ($r) => ['name' => $r->name, 'total' => (int) $r->total])
        );
    }

    /**
     * Ordens de serviço atualmente "Em andamento" — para a área de
     * acompanhamento em tempo real no dashboard.
     * GET /api/v1/dashboard/in-progress-orders?limit=10
     */
    public function inProgressOrders(Request $request)
    {
        $companyId = $request->user()->company_id;
        $limit = (int) $request->query('limit', 10);
        $limit = $limit > 0 ? min($limit, 100) : 10;

        $rows = DB::table('order_services as os')
            ->join('orders_status as st', 'st.id', '=', 'os.orders_status_id')
            ->join('vehicles as v', 'v.id', '=', 'os.vehicle_id')
            ->leftJoin('clients as c', 'c.id', '=', 'v.clients_id')
            ->leftJoin('car_models as cm', 'cm.id', '=', 'v.car_models_id')
            ->where('os.company_id', $companyId)
            ->whereNull('os.deleted_at')
            ->where('st.status', 'Em andamento')
            ->select(
                'os.id as id',
                'os.created_at as created_at',
                'v.placa as placa',
                'c.id as client_id',
                DB::raw('COALESCE(cm.model, "") as model'),
                DB::raw("TRIM(CONCAT(COALESCE(c.name, ''), ' ', COALESCE(c.lastname, ''))) as client_name")
            )
            ->orderByDesc('os.id')
            ->limit($limit)
            ->get();

        return response()->json(
            $rows->map(fn ($r) => [
                'id'         => (int) $r->id,
                'placa'      => $r->placa,
                'clientId'   => $r->client_id !== null ? (int) $r->client_id : null,
                'model'      => $r->model,
                'clientName' => $r->client_name,
                'createdAt'  => $r->created_at,
            ])
        );
    }
}

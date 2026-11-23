<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\AuthorizesCompany;
use App\Http\Traits\ChecksPlanLimits;
use App\Http\Traits\HasRoleAndPermissions;
use App\Models\OrderService;
use App\Models\OrderStatus;
use App\Models\Part;
use App\Models\Service;
use App\Models\CarMileage;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderServiceController extends Controller
{
    use AuthorizesCompany, ChecksPlanLimits, HasRoleAndPermissions;

    public function index(Request $request)
    {
        $query = OrderService::with(['vehicle.carModel.maker', 'vehicle.client', 'type', 'status', 'parts', 'services', 'mileages'])
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

    /**
     * Lista paginada de todas as OS da oficina.
     *
     * Filtro opcional `status`:
     * - `open`: tudo que não está concluído nem cancelado;
     * - `1` ou `1,6,7`: um ou mais IDs de orders_status.
     */
    public function listAll(Request $request)
    {
        $request->validate([
            'status' => ['nullable', 'string', 'regex:/^(open|\d+(,\d+){0,19})$/'],
        ]);

        $query = OrderService::with(['vehicle.carModel.maker', 'type', 'status', 'parts', 'services', 'mileages', 'vehicle.client'])
            ->where('company_id', auth()->user()->company_id)
            ->orderBy('id', 'desc');

        $status = $request->input('status');

        if ($status === 'open') {
            $query->whereNotIn('orders_status_id', OrderService::closedStatusIds());
        } elseif ($status !== null && $status !== '') {
            $query->whereIn('orders_status_id', array_map('intval', explode(',', $status)));
        }

        return response()->json($query->paginate(25));
    }

    /**
     * Quantidade de OS da oficina por status, para os botões de filtro da listagem.
     * Uma única consulta agrupada, coberta pelo índice (company_id, orders_status_id, ...).
     */
    public function statusCounts()
    {
        // toBase(): sem passar pelo modelo, cujo acessor `total` (valor da OS) sobrescreveria a contagem
        $counts = OrderService::where('company_id', auth()->user()->company_id)
            ->toBase()
            ->selectRaw('orders_status_id, COUNT(*) as orders_count')
            ->groupBy('orders_status_id')
            ->pluck('orders_count', 'orders_status_id')
            ->map(fn ($count) => (int) $count);

        $statuses = OrderStatus::orderBy('id')
            ->get(['id', 'status'])
            ->map(fn (OrderStatus $s) => [
                'id' => $s->id,
                'status' => $s->status,
                'total' => $counts->get($s->id, 0),
            ])
            ->values();

        return response()->json([
            'all' => $counts->sum(),
            'open' => $counts->except(OrderService::closedStatusIds())->sum(),
            'statuses' => $statuses,
        ]);
    }

    public function store(Request $request)
    {
        $limitCheck = $this->checkPlanLimit('orders');
        if ($limitCheck) {
            return $limitCheck;
        }

        $this->dropBlankItems($request);

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
            'parts.*.quantity' => 'required_with:parts|numeric|min:1',
            'parts.*.unit_price' => 'required_with:parts|numeric|min:0',
            'parts.*.information' => 'nullable|string|max:500',
            'parts.*.stock_id' => [
                'nullable', 'integer',
                \Illuminate\Validation\Rule::exists('stocks', 'id')->where('company_id', $request->user()->company_id),
            ],
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
                    'stock_id' => $part['stock_id'] ?? null,
                ]);

                // Dar baixa no estoque se for item vinculado
                if (!empty($part['stock_id'])) {
                    Stock::where('id', $part['stock_id'])
                        ->where('company_id', $companyId)
                        ->decrement('current_stock', $part['quantity']);
                }
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
        $this->dropBlankItems($request);

        $validated = $request->validate([
            'orders_types_id'          => 'sometimes|integer|exists:orders_types,id',
            'orders_status_id'         => 'sometimes|integer|exists:orders_status,id',
            'info'                     => 'nullable|string|max:1000',
            'mileage'                  => 'nullable|numeric|min:0',
            'parts'                    => 'nullable|array',
            'parts.*.description'      => 'required_with:parts|string|max:255',
            'parts.*.quantity'         => 'required_with:parts|numeric|min:1',
            'parts.*.unit_price'       => 'required_with:parts|numeric|min:0',
            'parts.*.information'      => 'nullable|string|max:500',
            'parts.*.stock_id'         => [
                'nullable', 'integer',
                \Illuminate\Validation\Rule::exists('stocks', 'id')->where('company_id', $request->user()->company_id),
            ],
            'services'                 => 'nullable|array',
            'services.*.description'   => 'required_with:services|string|max:255',
            'services.*.price'         => 'required_with:services|numeric|min:0',
            'services.*.information'   => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($validated, $orderService, $request) {
            $companyId = $request->user()->company_id;

            $orderService->update([
                'orders_types_id'  => $validated['orders_types_id']  ?? $orderService->orders_types_id,
                'orders_status_id' => $validated['orders_status_id'] ?? $orderService->orders_status_id,
                'info'             => $validated['info']              ?? $orderService->info,
            ]);

            // Substitui peças: devolve estoque das antigas, dá baixa nas novas
            if (array_key_exists('parts', $validated)) {
                // Devolve estoque das peças que serão removidas
                $oldParts = $orderService->parts()->whereNotNull('stock_id')->get();
                foreach ($oldParts as $oldPart) {
                    Stock::where('id', $oldPart->stock_id)
                        ->where('company_id', $companyId)
                        ->increment('current_stock', $oldPart->quantity);
                }

                $orderService->parts()->delete();

                foreach ($validated['parts'] ?? [] as $part) {
                    Part::create([
                        'orders_id'   => $orderService->id,
                        'company_id'  => $companyId,
                        'description' => $part['description'],
                        'quantity'    => $part['quantity'],
                        'unit_price'  => $part['unit_price'],
                        'information' => $part['information'] ?? null,
                        'stock_id'    => $part['stock_id'] ?? null,
                    ]);

                    // Dar baixa no estoque se for item vinculado
                    if (!empty($part['stock_id'])) {
                        Stock::where('id', $part['stock_id'])
                            ->where('company_id', $companyId)
                            ->decrement('current_stock', $part['quantity']);
                    }
                }
            }

            // Substitui serviços: remove os antigos e recria
            if (array_key_exists('services', $validated)) {
                $orderService->services()->delete();
                foreach ($validated['services'] ?? [] as $service) {
                    Service::create([
                        'orders_id'   => $orderService->id,
                        'company_id'  => $companyId,
                        'description' => $service['description'],
                        'price'       => $service['price'],
                        'information' => $service['information'] ?? null,
                    ]);
                }
            }

            // Registra nova leitura de KM se fornecida
            if (!empty($validated['mileage'])) {
                CarMileage::create([
                    'order_services_id' => $orderService->id,
                    'vehicle_id'        => $orderService->vehicle_id,
                    'company_id'        => $companyId,
                    'mileage'           => $validated['mileage'],
                ]);
            }
        });

        return response()->json(
            $orderService->load(['vehicle', 'type', 'status', 'parts', 'services', 'mileages'])
        );
    }

    public function destroy(OrderService $orderService)
    {
        $this->authorizeCompany($orderService);

        $companyId = auth()->user()->company_id;

        DB::transaction(function () use ($orderService, $companyId) {
            // Devolve estoque das peças vinculadas antes de excluir a OS
            $orderService->parts()->whereNotNull('stock_id')->each(function ($part) use ($companyId) {
                Stock::where('id', $part->stock_id)
                    ->where('company_id', $companyId)
                    ->increment('current_stock', $part->quantity);
            });

            $orderService->delete();
        });

        return response()->json(null, 204);
    }

    /**
     * Linha de peça ou serviço sem descrição e sem valor é uma linha vazia do formulário
     * (ou o item fantasma de OS antigas), não um item: some antes da validação.
     * Regressão: editar essas OS respondia 422 "parts.0.description is required".
     */
    private function dropBlankItems(Request $request): void
    {
        foreach (['parts' => 'unit_price', 'services' => 'price'] as $key => $priceField) {
            $items = $request->input($key);
            if (!is_array($items)) {
                continue;
            }

            $request->merge([$key => array_values(array_filter($items, fn($item) => !(
                is_array($item)
                && trim((string) ($item['description'] ?? '')) === ''
                && (float) ($item[$priceField] ?? 0) <= 0
                && empty($item['stock_id'])
            )))]);
        }
    }
}

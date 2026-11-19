<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Income;
use App\Models\Expense;
use App\Models\OrderService;
use App\Models\OrderStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    /**
     * Listar todas as transações (receitas manuais + ordens concluídas + despesas) de um período
     * para geração de extrato PDF no frontend.
     */
    public function list(Request $request)
    {
        $companyId = $request->user()->company_id;
        $startDate = $request->query('start_date');
        $endDate   = $request->query('end_date');

        if (!$startDate || !$endDate) {
            return response()->json(['error' => 'Informe start_date e end_date'], 400);
        }

        $start = Carbon::parse($startDate)->startOfDay();
        $end   = Carbon::parse($endDate)->endOfDay();

        // Receitas manuais
        $incomesRaw = Income::where('company_id', $companyId)
            ->whereBetween('date', [$start, $end])
            ->with('type')
            ->orderBy('date')
            ->get();

        $incomes = $incomesRaw->map(fn($i) => [
            'id'          => 'income_' . $i->id,
            'type'        => 'Receita',
            'description' => $i->info ?? $i->description ?? 'Receita',
            'amount'      => (float) ($i->value ?? $i->amount ?? 0),
            'date'        => $i->date instanceof \Carbon\Carbon ? $i->date->toDateString() : $i->date,
            'source'      => 'manual',
        ]);

        // Receitas de ordens concluídas (filtradas pela data de conclusão)
        $completedStatusId = $this->completedStatusId();
        $orderIncomes = collect();

        if ($completedStatusId) {
            $orders = OrderService::where('company_id', $companyId)
                ->where('orders_status_id', $completedStatusId)
                ->whereBetween('completed_at', [$start, $end])
                ->with(['parts', 'services', 'vehicle'])
                ->orderBy('completed_at')
                ->get();

            $orderIncomes = $orders->map(function ($order) {
                $placa = $order->vehicle->placa ?? $order->placa ?? '';
                $total = $order->total ?? 0;
                return [
                    'id'          => 'order_' . $order->id,
                    'type'        => 'Receita',
                    'description' => "OS #{$order->id}" . ($placa ? " — {$placa}" : ''),
                    'amount'      => (float) $total,
                    'date'        => $order->completed_at ? $order->completed_at->toDateString() : now()->toDateString(),
                    'source'      => 'order',
                ];
            });
        }

        // Despesas
        $expensesRaw = Expense::where('company_id', $companyId)
            ->whereBetween('date', [$start, $end])
            ->with('type')
            ->orderBy('date')
            ->get();

        $expenses = $expensesRaw->map(fn($e) => [
            'id'          => 'expense_' . $e->id,
            'type'        => 'Despesa',
            'description' => $e->info ?? $e->description ?? 'Despesa',
            'amount'      => (float) ($e->value ?? $e->amount ?? 0),
            'date'        => $e->date instanceof \Carbon\Carbon ? $e->date->toDateString() : $e->date,
            'source'      => 'manual',
        ]);

        $all = $incomes->concat($orderIncomes)->concat($expenses)
            ->sortBy('date')
            ->values();

        return response()->json($all);
    }

    public function period(Request $request)
    {
        $companyId = $request->user()->company_id;

        $date      = $request->query('date');
        $startDate = $request->query('start_date');
        $endDate   = $request->query('end_date');

        if (!$date && !($startDate && $endDate)) {
            return response()->json([
                'error' => 'Forneça um "date" para um dia específico ou "start_date" e "end_date" para um período',
            ], 400);
        }

        if ($date) {
            $parsedDate  = Carbon::parse($date)->toDateString();
            $periodStart = Carbon::parse($parsedDate);
            $periodEnd   = Carbon::parse($parsedDate);
        } else {
            $periodStart = Carbon::parse($startDate);
            $periodEnd   = Carbon::parse($endDate);
        }

        $incomes         = Income::where('company_id', $companyId)
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->sum('value');

        $completedOrders = array_sum($this->completedOrdersByDay(
            $companyId, $this->completedStatusId(), $periodStart->toDateString(), $periodEnd->toDateString()));
        $totalIncomes    = $incomes + $completedOrders;

        $expenses = Expense::where('company_id', $companyId)
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->sum('value');

        return response()->json([
            'period' => [
                'start' => $periodStart->format('Y-m-d'),
                'end'   => $periodEnd->format('Y-m-d'),
            ],
            'incomes'  => (float) $totalIncomes,
            'expenses' => (float) $expenses,
            'balance'  => (float) ($totalIncomes - $expenses),
            'breakdown' => [
                'incomes_from_records'         => (float) $incomes,
                'incomes_from_completed_orders' => (float) $completedOrders,
            ],
        ]);
    }

    /**
     * Resumo (hoje/semana/mês/ano + gráfico de 12 meses).
     * Busca os totais por dia de todo o intervalo em 3 consultas agregadas e
     * monta cada período em PHP, em vez de uma consulta por período/mês.
     */
    public function summary(Request $request)
    {
        $companyId  = $request->user()->company_id;
        $now        = Carbon::now();
        $today      = $now->toDateString();
        $weekStart  = $now->copy()->startOfWeek()->toDateString();
        $monthStart = $now->copy()->startOfMonth()->toDateString();
        $yearStart  = $now->copy()->startOfYear()->toDateString();
        $chartStart = $now->copy()->startOfMonth()->subMonths(11)->toDateString();

        $from = min($weekStart, $yearStart, $chartStart);
        $to   = $today;

        $completedStatusId = $this->completedStatusId();
        $incomeByDay  = $this->dailyTotals(Income::where('company_id', $companyId), 'date', 'value', $from, $to);
        $expenseByDay = $this->dailyTotals(Expense::where('company_id', $companyId), 'date', 'value', $from, $to);
        $ordersByDay  = $this->completedOrdersByDay($companyId, $completedStatusId, $from, $to);

        $sumRange = function (array $byDay, string $start) use ($to) {
            $total = 0.0;
            foreach ($byDay as $day => $value) {
                if ($day >= $start && $day <= $to) $total += $value;
            }
            return $total;
        };

        $periods = ['today' => $today, 'week' => $weekStart, 'month' => $monthStart, 'year' => $yearStart];
        $incomes = $expenses = [];
        foreach ($periods as $key => $start) {
            $incomes[$key]  = $sumRange($incomeByDay, $start) + $sumRange($ordersByDay, $start);
            $expenses[$key] = $sumRange($expenseByDay, $start);
        }

        $monthly = [];
        foreach ([$incomeByDay, $ordersByDay] as $byDay) {
            foreach ($byDay as $day => $v) $monthly[substr($day, 0, 7)]['income'] = ($monthly[substr($day, 0, 7)]['income'] ?? 0) + $v;
        }
        foreach ($expenseByDay as $day => $v) $monthly[substr($day, 0, 7)]['expense'] = ($monthly[substr($day, 0, 7)]['expense'] ?? 0) + $v;

        $monthlyData = [];
        for ($i = 11; $i >= 0; $i--) {
            // Parte do 1º do mês atual para evitar overflow em meses curtos
            $date   = $now->copy()->startOfMonth()->subMonths($i);
            $key    = $date->format('Y-m');
            $income = (float) ($monthly[$key]['income'] ?? 0);
            $expense = (float) ($monthly[$key]['expense'] ?? 0);
            $monthlyData[] = [
                'month'   => $key,
                'label'   => $date->format('M/Y'),
                'income'  => $income,
                'expense' => $expense,
                'balance' => $income - $expense,
            ];
        }

        return response()->json([
            'incomes'       => $incomes,
            'expenses'      => $expenses,
            'monthly_chart' => $monthlyData,
        ]);
    }

    private function completedStatusId(): ?int
    {
        return OrderService::completedStatusIds()[0] ?? null;
    }

    /** Soma de $valueCol agrupada por dia (Y-m-d => float) entre $from e $to. */
    private function dailyTotals($query, string $dateCol, string $valueCol, string $from, string $to): array
    {
        return $query->whereBetween(DB::raw("DATE({$dateCol})"), [$from, $to])
            ->selectRaw("DATE({$dateCol}) as day, SUM({$valueCol}) as total")
            ->groupBy('day')
            ->pluck('total', 'day')
            ->map(fn($v) => (float) $v)
            ->all();
    }

    /**
     * Receita das OS concluídas por dia (data = completed_at), calculada no banco
     * com a mesma regra de OrderService::total:
     * peças = (unit_price ?? price ?? 0) × max(1, quantidade); serviços = price.
     */
    private function completedOrdersByDay(int $companyId, ?int $statusId, string $from, string $to): array
    {
        if (!$statusId) return [];

        $orders = DB::table('order_services')
            ->where('company_id', $companyId)
            ->where('orders_status_id', $statusId)
            ->whereNull('deleted_at')
            ->whereBetween('completed_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->select('id', DB::raw('DATE(completed_at) as day'));

        $parts = DB::table('parts as p')
            ->joinSub($orders, 'o', 'o.id', '=', 'p.orders_id')
            ->whereNull('p.deleted_at')
            ->selectRaw("o.day, SUM(COALESCE(p.unit_price, p.price, 0) * GREATEST(1, CAST(COALESCE(NULLIF(p.quantity, ''), '1') AS DECIMAL(12,3)))) as total")
            ->groupBy('o.day');

        $services = DB::table('services as s')
            ->joinSub($orders, 'o', 'o.id', '=', 's.orders_id')
            ->whereNull('s.deleted_at')
            ->selectRaw('o.day, SUM(COALESCE(s.price, 0)) as total')
            ->groupBy('o.day');

        $byDay = [];
        foreach ($parts->unionAll($services)->get() as $row) {
            $byDay[$row->day] = ($byDay[$row->day] ?? 0) + (float) $row->total;
        }
        return $byDay;
    }
}

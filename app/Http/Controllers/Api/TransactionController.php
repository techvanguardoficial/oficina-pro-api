<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Income;
use App\Models\Expense;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    /**
     * Listar todas as transações (receitas manuais + pagamentos de OS + despesas) de um período
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

        // Receitas de OS: cada pagamento conta na data em que foi pago
        $orderIncomes = $this->orderPaymentsQuery($companyId, $start->toDateString(), $end->toDateString())
            ->leftJoin('vehicles as v', 'v.id', '=', 'os.vehicle_id')
            ->select('op.id', 'op.amount', 'op.paid_at', 'os.id as order_id', 'v.placa')
            ->orderBy('op.paid_at')
            ->orderBy('op.id')
            ->get()
            ->map(fn ($p) => [
                'id'          => 'payment_' . $p->id,
                'type'        => 'Receita',
                'description' => "OS #{$p->order_id}" . ($p->placa ? " — {$p->placa}" : ''),
                'amount'      => (float) $p->amount,
                'date'        => $p->paid_at,
                'source'      => 'order',
                'order_id'    => (int) $p->order_id,
            ]);

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

        $orderPayments = array_sum($this->orderPaymentsByDay(
            $companyId, $periodStart->toDateString(), $periodEnd->toDateString()));
        $totalIncomes  = $incomes + $orderPayments;

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
                'incomes_from_order_payments'  => (float) $orderPayments,
                // Nome antigo, mantido para o frontend: hoje é a soma dos pagamentos de OS
                'incomes_from_completed_orders' => (float) $orderPayments,
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

        $incomeByDay  = $this->dailyTotals(Income::where('company_id', $companyId), 'date', 'value', $from, $to);
        $expenseByDay = $this->dailyTotals(Expense::where('company_id', $companyId), 'date', 'value', $from, $to);
        $ordersByDay  = $this->orderPaymentsByDay($companyId, $from, $to);

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

    /** Pagamentos de OS (não excluídas) entre $from e $to, por data de pagamento. */
    private function orderPaymentsQuery(int $companyId, string $from, string $to)
    {
        return DB::table('order_service_payments as op')
            ->join('order_services as os', 'os.id', '=', 'op.order_service_id')
            ->where('op.company_id', $companyId)
            ->whereNull('op.deleted_at')
            ->whereNull('os.deleted_at')
            ->whereBetween('op.paid_at', [$from, $to]);
    }

    /** Receita das OS por dia (Y-m-d => float): soma dos pagamentos pela data paga. */
    private function orderPaymentsByDay(int $companyId, string $from, string $to): array
    {
        return $this->orderPaymentsQuery($companyId, $from, $to)
            ->selectRaw('op.paid_at as day, SUM(op.amount) as total')
            ->groupBy('op.paid_at')
            ->pluck('total', 'day')
            ->mapWithKeys(fn ($v, $day) => [substr((string) $day, 0, 10) => (float) $v])
            ->all();
    }
}

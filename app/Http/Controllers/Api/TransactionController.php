<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Income;
use App\Models\Expense;
use App\Models\OrderService;
use App\Models\OrderStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
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

        $completedStatus = OrderStatus::where('status', 'Concluído')->first();

        $incomes         = Income::where('company_id', $companyId)
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->sum('value');

        $completedOrders = $this->getCompletedOrdersTotal($companyId, $periodStart, $completedStatus, $periodEnd);
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

    public function summary(Request $request)
    {
        $companyId   = $request->user()->company_id;
        $today       = Carbon::today();
        $weekStart   = Carbon::now()->startOfWeek();
        $monthStart  = Carbon::now()->startOfMonth();
        $yearStart   = Carbon::now()->startOfYear();
        $completedStatus = OrderStatus::where('status', 'Concluído')->first();

        $incomes = [
            'today' => Income::where('company_id', $companyId)->whereDate('date', $today)->sum('value')
                + $this->getCompletedOrdersTotal($companyId, $today, $completedStatus),
            'week'  => Income::where('company_id', $companyId)->whereBetween('date', [$weekStart, now()])->sum('value')
                + $this->getCompletedOrdersTotal($companyId, $weekStart, $completedStatus, now()),
            'month' => Income::where('company_id', $companyId)->whereBetween('date', [$monthStart, now()])->sum('value')
                + $this->getCompletedOrdersTotal($companyId, $monthStart, $completedStatus, now()),
            'year'  => Income::where('company_id', $companyId)->whereBetween('date', [$yearStart, now()])->sum('value')
                + $this->getCompletedOrdersTotal($companyId, $yearStart, $completedStatus, now()),
        ];

        $expenses = [
            'today' => Expense::where('company_id', $companyId)->whereDate('date', $today)->sum('value'),
            'week'  => Expense::where('company_id', $companyId)->whereBetween('date', [$weekStart, now()])->sum('value'),
            'month' => Expense::where('company_id', $companyId)->whereBetween('date', [$monthStart, now()])->sum('value'),
            'year'  => Expense::where('company_id', $companyId)->whereBetween('date', [$yearStart, now()])->sum('value'),
        ];

        $monthlyData = $this->getMonthlyData($companyId);

        return response()->json([
            'incomes'       => $incomes,
            'expenses'      => $expenses,
            'monthly_chart' => $monthlyData,
        ]);
    }

    private function getCompletedOrdersTotal(int $companyId, $startDate, $completedStatus, $endDate = null): float
    {
        if (!$completedStatus) {
            return 0;
        }

        $query = OrderService::where('company_id', $companyId)
            ->where('orders_status_id', $completedStatus->id)
            ->with(['parts', 'services']);

        if ($endDate) {
            $query->whereBetween('updated_at', [$startDate, $endDate]);
        } else {
            $query->whereDate('updated_at', $startDate);
        }

        $total = 0;
        foreach ($query->get() as $order) {
            $total += $order->total;
        }

        return (float) $total;
    }

    private function getMonthlyData(int $companyId): array
    {
        $months          = [];
        $completedStatus = OrderStatus::where('status', 'Concluído')->first();

        for ($i = 11; $i >= 0; $i--) {
            // Parte do 1º do mês atual para evitar overflow em meses curtos
            // (ex: 31/ago - 11 meses = 31/set → transborda para 01/out)
            $date       = Carbon::now()->startOfMonth()->subMonths($i);
            $monthKey   = $date->format('Y-m');
            $monthLabel = $date->format('M/Y');

            $income = Income::where('company_id', $companyId)
                ->whereYear('date', $date->year)
                ->whereMonth('date', $date->month)
                ->sum('value');

            $completedOrdersIncome = 0;
            if ($completedStatus) {
                $completedOrdersIncome = $this->getCompletedOrdersTotal(
                    $companyId,
                    $date->copy()->startOfMonth(),
                    $completedStatus,
                    $date->copy()->endOfMonth()
                );
            }

            $expense = Expense::where('company_id', $companyId)
                ->whereYear('date', $date->year)
                ->whereMonth('date', $date->month)
                ->sum('value');

            $totalIncome = $income + $completedOrdersIncome;

            $months[] = [
                'month'   => $monthKey,
                'label'   => $monthLabel,
                'income'  => (float) $totalIncome,
                'expense' => (float) $expense,
                'balance' => (float) ($totalIncome - $expense),
            ];
        }

        return $months;
    }
}

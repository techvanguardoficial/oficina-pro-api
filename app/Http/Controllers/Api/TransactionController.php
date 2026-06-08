<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Income;
use App\Models\Expense;
use App\Models\OrderService;
use App\Models\OrderStatus;
use Carbon\Carbon;

class TransactionController extends Controller
{
    public function period()
    {
        $date = request()->query('date');
        $startDate = request()->query('start_date');
        $endDate = request()->query('end_date');

        if (!$date && !($startDate && $endDate)) {
            return response()->json([
                'error' => 'Forneça um "date" para um dia específico ou "start_date" e "end_date" para um período',
            ], 400);
        }

        if ($date) {
            $parsedDate = Carbon::parse($date)->toDateString();
            $periodStart = Carbon::parse($parsedDate);
            $periodEnd = Carbon::parse($parsedDate);
        } else {
            $periodStart = Carbon::parse($startDate);
            $periodEnd = Carbon::parse($endDate);
        }

        $completedStatus = OrderStatus::where('status', 'Concluído')->first();

        $incomes = Income::whereBetween('date', [$periodStart, $periodEnd])->sum('value');
        $completedOrders = $this->getCompletedOrdersTotal($periodStart, $completedStatus, $periodEnd);
        $totalIncomes = $incomes + $completedOrders;

        $expenses = Expense::whereBetween('date', [$periodStart, $periodEnd])->sum('value');

        return response()->json([
            'period' => [
                'start' => $periodStart->format('Y-m-d'),
                'end' => $periodEnd->format('Y-m-d'),
            ],
            'incomes' => (float) $totalIncomes,
            'expenses' => (float) $expenses,
            'balance' => (float) ($totalIncomes - $expenses),
            'breakdown' => [
                'incomes_from_records' => (float) $incomes,
                'incomes_from_completed_orders' => (float) $completedOrders,
            ],
        ]);
    }

    public function summary()
    {
        $today = Carbon::today();
        $weekStart = Carbon::now()->startOfWeek();
        $monthStart = Carbon::now()->startOfMonth();
        $yearStart = Carbon::now()->startOfYear();
        $completedStatus = OrderStatus::where('status', 'Concluído')->first();

        $incomes = [
            'today' => Income::whereDate('date', $today)->sum('value') + $this->getCompletedOrdersTotal($today, $completedStatus),
            'week' => Income::whereBetween('date', [$weekStart, now()])->sum('value') + $this->getCompletedOrdersTotal($weekStart, $completedStatus, now()),
            'month' => Income::whereBetween('date', [$monthStart, now()])->sum('value') + $this->getCompletedOrdersTotal($monthStart, $completedStatus, now()),
            'year' => Income::whereBetween('date', [$yearStart, now()])->sum('value') + $this->getCompletedOrdersTotal($yearStart, $completedStatus, now()),
        ];

        $expenses = [
            'today' => Expense::whereDate('date', $today)->sum('value'),
            'week' => Expense::whereBetween('date', [$weekStart, now()])->sum('value'),
            'month' => Expense::whereBetween('date', [$monthStart, now()])->sum('value'),
            'year' => Expense::whereBetween('date', [$yearStart, now()])->sum('value'),
        ];

        $monthlyData = $this->getMonthlyData();

        return response()->json([
            'incomes' => $incomes,
            'expenses' => $expenses,
            'monthly_chart' => $monthlyData,
        ]);
    }

    private function getCompletedOrdersTotal($startDate, $completedStatus, $endDate = null)
    {
        if (!$completedStatus) {
            return 0;
        }

        $query = OrderService::where('orders_status_id', $completedStatus->id)
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

        return $total;
    }

    private function getMonthlyData()
    {
        $months = [];
        $completedStatus = OrderStatus::where('status', 'Concluído')->first();

        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthKey = $date->format('Y-m');
            $monthLabel = $date->format('M/Y');

            $income = Income::whereYear('date', $date->year)
                ->whereMonth('date', $date->month)
                ->sum('value');

            $completedOrdersIncome = 0;
            if ($completedStatus) {
                $completedOrdersIncome = $this->getCompletedOrdersTotal(
                    $date->copy()->startOfMonth(),
                    $completedStatus,
                    $date->copy()->endOfMonth()
                );
            }

            $expense = Expense::whereYear('date', $date->year)
                ->whereMonth('date', $date->month)
                ->sum('value');

            $totalIncome = $income + $completedOrdersIncome;

            $months[] = [
                'month' => $monthKey,
                'label' => $monthLabel,
                'income' => (float) $totalIncome,
                'expense' => (float) $expense,
                'balance' => (float) ($totalIncome - $expense),
            ];
        }

        return $months;
    }
}

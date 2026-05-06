<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Income;
use App\Models\Expense;
use Carbon\Carbon;

class TransactionController extends Controller
{
    public function summary()
    {
        $today = Carbon::today();
        $weekStart = Carbon::now()->startOfWeek();
        $monthStart = Carbon::now()->startOfMonth();
        $yearStart = Carbon::now()->startOfYear();

        $incomes = [
            'today' => Income::whereDate('date', $today)->sum('value'),
            'week' => Income::whereBetween('date', [$weekStart, now()])->sum('value'),
            'month' => Income::whereBetween('date', [$monthStart, now()])->sum('value'),
            'year' => Income::whereBetween('date', [$yearStart, now()])->sum('value'),
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

    private function getMonthlyData()
    {
        $months = [];

        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthKey = $date->format('Y-m');
            $monthLabel = $date->format('M/Y');

            $income = Income::whereYear('date', $date->year)
                ->whereMonth('date', $date->month)
                ->sum('value');

            $expense = Expense::whereYear('date', $date->year)
                ->whereMonth('date', $date->month)
                ->sum('value');

            $months[] = [
                'month' => $monthKey,
                'label' => $monthLabel,
                'income' => (float) $income,
                'expense' => (float) $expense,
                'balance' => (float) ($income - $expense),
            ];
        }

        return $months;
    }
}

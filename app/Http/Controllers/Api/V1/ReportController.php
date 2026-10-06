<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Household;
use App\Models\Saving;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Financial Summary Report.
     */
    public function summary(Request $request, Household $household): JsonResponse
    {
        $startDate = $request->input('start_date', date('Y-m-01'));
        $endDate = $request->input('end_date', date('Y-m-t'));

        $incomeQuery = Transaction::where('household_id', $household->id)
            ->where('type', 'income')
            ->whereDate('transaction_date', '>=', $startDate)
            ->whereDate('transaction_date', '<=', $endDate);

        $expenseQuery = Transaction::where('household_id', $household->id)
            ->where('type', 'expense')
            ->whereDate('transaction_date', '>=', $startDate)
            ->whereDate('transaction_date', '<=', $endDate);

        $totalIncome = (float) $incomeQuery->sum('amount');
        $totalExpense = (float) $expenseQuery->sum('amount');
        $netCashflow = $totalIncome - $totalExpense;

        $accounts = Account::where('household_id', $household->id)
            ->where('is_active', true)
            ->get();

        $totalAccountBalance = 0.0;
        foreach ($accounts as $acc) {
            $totalAccountBalance += (float) $acc->calculateBalance();
        }

        $activeBudgetsCount = Budget::where('household_id', $household->id)
            ->where('is_active', true)
            ->count();

        $activeSavingsCount = Saving::where('household_id', $household->id)
            ->where('is_completed', false)
            ->count();

        return response()->json([
            'summary' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'total_income' => number_format($totalIncome, 2, '.', ''),
                'total_expense' => number_format($totalExpense, 2, '.', ''),
                'net_cashflow' => number_format($netCashflow, 2, '.', ''),
                'total_account_balance' => number_format($totalAccountBalance, 2, '.', ''),
                'active_budgets_count' => $activeBudgetsCount,
                'active_savings_count' => $activeSavingsCount,
            ]
        ], 200);
    }

    /**
     * Income vs Expense Trend Report.
     */
    public function incomeVsExpense(Request $request, Household $household): JsonResponse
    {
        $startDate = $request->input('start_date', date('Y-m-01'));
        $endDate = $request->input('end_date', date('Y-m-t'));

        $transactions = Transaction::where('household_id', $household->id)
            ->whereIn('type', ['income', 'expense'])
            ->whereDate('transaction_date', '>=', $startDate)
            ->whereDate('transaction_date', '<=', $endDate)
            ->orderBy('transaction_date', 'asc')
            ->get();

        $grouped = [];
        foreach ($transactions as $t) {
            $date = $t->transaction_date instanceof \DateTimeInterface 
                ? $t->transaction_date->format('Y-m-d') 
                : (string) $t->transaction_date;

            if (! isset($grouped[$date])) {
                $grouped[$date] = [
                    'date' => $date,
                    'income' => 0.0,
                    'expense' => 0.0,
                ];
            }

            if ($t->type === 'income') {
                $grouped[$date]['income'] += (float) $t->amount;
            } else {
                $grouped[$date]['expense'] += (float) $t->amount;
            }
        }

        $result = [];
        foreach ($grouped as $date => $val) {
            $val['income'] = number_format($val['income'], 2, '.', '');
            $val['expense'] = number_format($val['expense'], 2, '.', '');
            $val['net'] = number_format((float) $val['income'] - (float) $val['expense'], 2, '.', '');
            $result[] = $val;
        }

        return response()->json([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'data' => $result,
        ], 200);
    }

    /**
     * Category Breakdown Report.
     */
    public function categoryBreakdown(Request $request, Household $household): JsonResponse
    {
        $startDate = $request->input('start_date', date('Y-m-01'));
        $endDate = $request->input('end_date', date('Y-m-t'));
        $type = $request->input('type', 'expense');

        $query = Transaction::where('household_id', $household->id)
            ->where('type', $type)
            ->whereDate('transaction_date', '>=', $startDate)
            ->whereDate('transaction_date', '<=', $endDate);

        $totalAmount = (float) $query->sum('amount');

        // Check if category_id column exists
        $breakdown = [];
        if (\Illuminate\Support\Facades\Schema::hasColumn('transactions', 'category_id')) {
            $categories = Transaction::select('category_id', DB::raw('SUM(amount) as total'))
                ->where('household_id', $household->id)
                ->where('type', $type)
                ->whereDate('transaction_date', '>=', $startDate)
                ->whereDate('transaction_date', '<=', $endDate)
                ->groupBy('category_id')
                ->get();

            foreach ($categories as $cat) {
                $catId = $cat->category_id;
                $catTotal = (float) $cat->total;
                $pct = $totalAmount > 0 ? round(($catTotal / $totalAmount) * 100, 2) : 0.0;
                $categoryModel = $catId ? \App\Models\Category::find($catId) : null;

                $breakdown[] = [
                    'category_id' => $catId,
                    'category_name' => $categoryModel ? $categoryModel->name : 'Uncategorized',
                    'total' => number_format($catTotal, 2, '.', ''),
                    'percentage' => $pct,
                ];
            }
        } else {
            $breakdown[] = [
                'category_id' => null,
                'category_name' => 'General ' . ucfirst($type),
                'total' => number_format($totalAmount, 2, '.', ''),
                'percentage' => 100.0,
            ];
        }

        return response()->json([
            'type' => $type,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_amount' => number_format($totalAmount, 2, '.', ''),
            'categories' => $breakdown,
        ], 200);
    }

    /**
     * Account Balances Report.
     */
    public function accountBalances(Request $request, Household $household): JsonResponse
    {
        $accounts = Account::where('household_id', $household->id)->get();

        $data = [];
        foreach ($accounts as $acc) {
            $data[] = [
                'id' => $acc->id,
                'name' => $acc->name,
                'type' => $acc->type,
                'is_active' => $acc->is_active,
                'initial_balance' => number_format((float) $acc->initial_balance, 2, '.', ''),
                'current_balance' => $acc->calculateBalance(),
            ];
        }

        return response()->json(['accounts' => $data], 200);
    }

    /**
     * Month Comparison Report.
     */
    public function monthComparison(Request $request, Household $household): JsonResponse
    {
        $selectedMonth = $request->input('month', date('Y-m'));

        $selectedStart = $selectedMonth . '-01';
        $selectedEnd = date('Y-m-t', strtotime($selectedStart));

        $prevMonth = date('Y-m', strtotime('-1 month', strtotime($selectedStart)));
        $prevStart = $prevMonth . '-01';
        $prevEnd = date('Y-m-t', strtotime($prevStart));

        $currentIncome = (float) Transaction::where('household_id', $household->id)
            ->where('type', 'income')
            ->whereDate('transaction_date', '>=', $selectedStart)
            ->whereDate('transaction_date', '<=', $selectedEnd)
            ->sum('amount');

        $currentExpense = (float) Transaction::where('household_id', $household->id)
            ->where('type', 'expense')
            ->whereDate('transaction_date', '>=', $selectedStart)
            ->whereDate('transaction_date', '<=', $selectedEnd)
            ->sum('amount');

        $currentNet = $currentIncome - $currentExpense;

        $prevIncome = (float) Transaction::where('household_id', $household->id)
            ->where('type', 'income')
            ->whereDate('transaction_date', '>=', $prevStart)
            ->whereDate('transaction_date', '<=', $prevEnd)
            ->sum('amount');

        $prevExpense = (float) Transaction::where('household_id', $household->id)
            ->where('type', 'expense')
            ->whereDate('transaction_date', '>=', $prevStart)
            ->whereDate('transaction_date', '<=', $prevEnd)
            ->sum('amount');

        $prevNet = $prevIncome - $prevExpense;

        $incomeDiff = $currentIncome - $prevIncome;
        $incomePct = $prevIncome > 0 ? round(($incomeDiff / $prevIncome) * 100, 2) : ($currentIncome > 0 ? 100.0 : 0.0);

        $expenseDiff = $currentExpense - $prevExpense;
        $expensePct = $prevExpense > 0 ? round(($expenseDiff / $prevExpense) * 100, 2) : ($currentExpense > 0 ? 100.0 : 0.0);

        $netDiff = $currentNet - $prevNet;
        $netPct = $prevNet != 0 ? round(($netDiff / abs($prevNet)) * 100, 2) : ($currentNet != 0 ? 100.0 : 0.0);

        return response()->json([
            'selected_month' => $selectedMonth,
            'previous_month' => $prevMonth,
            'current_month' => [
                'income' => number_format($currentIncome, 2, '.', ''),
                'expense' => number_format($currentExpense, 2, '.', ''),
                'net' => number_format($currentNet, 2, '.', ''),
            ],
            'previous_month_data' => [
                'income' => number_format($prevIncome, 2, '.', ''),
                'expense' => number_format($prevExpense, 2, '.', ''),
                'net' => number_format($prevNet, 2, '.', ''),
            ],
            'comparison' => [
                'income_diff' => number_format($incomeDiff, 2, '.', ''),
                'income_change_percentage' => $incomePct,
                'expense_diff' => number_format($expenseDiff, 2, '.', ''),
                'expense_change_percentage' => $expensePct,
                'net_diff' => number_format($netDiff, 2, '.', ''),
                'net_change_percentage' => $netPct,
            ],
        ], 200);
    }

    /**
     * Dedicated Savings Goals Report.
     */
    public function savings(Request $request, Household $household): JsonResponse
    {
        $query = Saving::where('household_id', $household->id);

        if ($request->has('status')) {
            $status = $request->input('status');
            if ($status === 'completed') {
                $query->where(function ($q) {
                    $q->where('is_completed', true)
                      ->orWhereRaw('current_amount >= target_amount');
                });
            } elseif ($status === 'active') {
                $query->where('is_completed', false)
                      ->whereRaw('current_amount < target_amount');
            }
        }

        $goals = $query->orderBy('id', 'desc')->get();

        $totalGoalsCount = $goals->count();
        $completedGoalsCount = $goals->filter(fn($g) => $g->is_completed || (float)$g->current_amount >= (float)$g->target_amount)->count();

        $totalTarget = 0.0;
        $totalCurrent = 0.0;

        $transformedGoals = $goals->map(function ($goal) use (&$totalTarget, &$totalCurrent) {
            $target = (float) $goal->target_amount;
            $current = (float) $goal->current_amount;
            $remaining = max(0.0, $target - $current);
            $pct = $target > 0 ? round(($current / $target) * 100, 2) : 0.0;

            $totalTarget += $target;
            $totalCurrent += $current;

            return [
                'id' => $goal->id,
                'name' => $goal->name,
                'target_amount' => number_format($target, 2, '.', ''),
                'current_amount' => number_format($current, 2, '.', ''),
                'remaining_amount' => number_format($remaining, 2, '.', ''),
                'progress_percentage' => $pct,
                'target_date' => $goal->target_date ? (string) $goal->target_date : null,
                'is_completed' => (bool) $goal->is_completed,
                'created_at' => $goal->created_at ? $goal->created_at->toIso8601String() : null,
            ];
        });

        $totalRemaining = max(0.0, $totalTarget - $totalCurrent);
        $overallPct = $totalTarget > 0 ? round(($totalCurrent / $totalTarget) * 100, 2) : 0.0;

        return response()->json([
            'summary' => [
                'total_goals_count' => $totalGoalsCount,
                'completed_goals_count' => $completedGoalsCount,
                'total_target_amount' => number_format($totalTarget, 2, '.', ''),
                'total_current_amount' => number_format($totalCurrent, 2, '.', ''),
                'total_remaining_amount' => number_format($totalRemaining, 2, '.', ''),
                'overall_progress_percentage' => $overallPct,
            ],
            'goals' => $transformedGoals,
        ], 200);
    }
}

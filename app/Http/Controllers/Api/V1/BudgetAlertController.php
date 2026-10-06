<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\Household;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BudgetAlertController extends Controller
{
    /**
     * Get real-time budget status and overbudget/warning alerts for household.
     */
    public function alerts(Request $request, Household $household): JsonResponse
    {
        $budgets = Budget::with('category:id,name,type')
            ->where('household_id', $household->id)
            ->where('is_active', true)
            ->get();

        $alerts = [];
        $totalOverbudgetCount = 0;
        $totalWarningCount = 0;

        foreach ($budgets as $budget) {
            $spentQuery = Transaction::where('household_id', $household->id)
                ->where('type', 'expense');

            if ($budget->category_id && \Illuminate\Support\Facades\Schema::hasColumn('transactions', 'category_id')) {
                $spentQuery->where('category_id', $budget->category_id);
            }

            if ($budget->start_date && $budget->end_date) {
                $spentQuery->whereDate('transaction_date', '>=', $budget->start_date)
                    ->whereDate('transaction_date', '<=', $budget->end_date);
            } else {
                $spentQuery->whereBetween('transaction_date', [
                    now()->startOfMonth()->toDateString(),
                    now()->endOfMonth()->toDateString()
                ]);
            }

            $spent = (float) $spentQuery->sum('amount');
            $allocated = (float) $budget->amount;
            $percentage = $allocated > 0 ? round(($spent / $allocated) * 100, 2) : 0;

            $status = 'normal';
            if ($percentage >= 100) {
                $status = 'exceeded';
                $totalOverbudgetCount++;
            } elseif ($percentage >= 80) {
                $status = 'warning';
                $totalWarningCount++;
            }

            $alerts[] = [
                'budget_id' => $budget->id,
                'name' => $budget->name,
                'category_name' => $budget->category ? $budget->category->name : 'Semua Kategori',
                'allocated_amount' => $allocated,
                'spent_amount' => $spent,
                'remaining_amount' => max(0, $allocated - $spent),
                'percentage_used' => $percentage,
                'status' => $status,
                'is_overbudget' => $spent > $allocated,
            ];
        }

        return response()->json([
            'summary' => [
                'total_budgets' => count($budgets),
                'overbudget_count' => $totalOverbudgetCount,
                'warning_count' => $totalWarningCount,
            ],
            'alerts' => $alerts,
        ]);
    }
}

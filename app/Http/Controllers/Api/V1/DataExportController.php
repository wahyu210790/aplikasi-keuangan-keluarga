<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Household;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataExportController extends Controller
{
    /**
     * Export transactions list as CSV or JSON.
     */
    public function transactions(Request $request, Household $household)
    {
        $query = Transaction::with(['account:id,name', 'toAccount:id,name'])
            ->where('household_id', $household->id);

        if ($request->filled('start_date')) {
            $query->whereDate('transaction_date', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('transaction_date', '<=', $request->input('end_date'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        $transactions = $query->orderBy('transaction_date', 'desc')->get();
        $format = strtolower($request->input('format', 'json'));

        if ($format === 'csv') {
            $fileName = "transactions_household_{$household->id}_" . date('Y-m-d') . ".csv";

            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            ];

            $callback = function () use ($transactions) {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['ID', 'Tanggal', 'Tipe', 'Jumlah', 'Deskripsi', 'Akun', 'Akun Tujuan']);

                foreach ($transactions as $t) {
                    fputcsv($file, [
                        $t->id,
                        $t->transaction_date,
                        $t->type,
                        $t->amount,
                        $t->description,
                        $t->account ? $t->account->name : '',
                        $t->toAccount ? $t->toAccount->name : '',
                    ]);
                }
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        }

        return response()->json([
            'household_id' => $household->id,
            'exported_at' => now()->toIso8601String(),
            'total_records' => count($transactions),
            'transactions' => $transactions,
        ]);
    }

    /**
     * Export financial summary report as CSV or JSON.
     */
    public function summary(Request $request, Household $household)
    {
        $income = (float) Transaction::where('household_id', $household->id)->where('type', 'income')->sum('amount');
        $expense = (float) Transaction::where('household_id', $household->id)->where('type', 'expense')->sum('amount');
        $netFlow = $income - $expense;

        $accounts = Account::where('household_id', $household->id)->where('is_active', true)->get();
        $totalBalance = (float) $accounts->sum(fn ($acc) => (float) $acc->calculateBalance());

        $data = [
            'household_name' => $household->name,
            'exported_at' => now()->toIso8601String(),
            'total_income' => $income,
            'total_expense' => $expense,
            'net_cash_flow' => $netFlow,
            'total_balance' => $totalBalance,
            'accounts_count' => count($accounts),
        ];

        $format = strtolower($request->input('format', 'json'));

        if ($format === 'csv') {
            $fileName = "financial_summary_household_{$household->id}_" . date('Y-m-d') . ".csv";

            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            ];

            $callback = function () use ($data) {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['Metrik', 'Nilai']);
                fputcsv($file, ['Nama Household', $data['household_name']]);
                fputcsv($file, ['Total Pemasukan', $data['total_income']]);
                fputcsv($file, ['Total Pengeluaran', $data['total_expense']]);
                fputcsv($file, ['Arus Kas Bersih', $data['net_cash_flow']]);
                fputcsv($file, ['Total Saldo Rekening', $data['total_balance']]);
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        }

        return response()->json($data);
    }
}

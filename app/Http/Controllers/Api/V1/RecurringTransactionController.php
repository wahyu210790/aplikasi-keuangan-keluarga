<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Household;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RecurringTransactionController extends Controller
{
    /**
     * Display a listing of recurring transactions for the given household.
     */
    public function index(Request $request, Household $household): JsonResponse
    {
        $query = RecurringTransaction::with(['account:id,name,type', 'toAccount:id,name,type'])
            ->where('household_id', $household->id);

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        $items = $query->orderBy('id', 'desc')->get();

        return response()->json(['recurring_transactions' => $items], 200);
    }

    /**
     * Store a new recurring transaction for the given household.
     */
    public function store(Request $request, Household $household): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => ['required', Rule::in(['income', 'expense', 'transfer'])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:255'],
            'frequency' => ['required', Rule::in(['daily', 'weekly', 'monthly', 'yearly'])],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'account_id' => ['required', 'integer'],
            'to_account_id' => ['nullable', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validator->after(function ($validator) use ($request, $household) {
            $type = $request->input('type');
            $accountId = $request->input('account_id');
            $toAccountId = $request->input('to_account_id');

            $account = Account::where('household_id', $household->id)
                ->where('id', $accountId)
                ->where('is_active', true)
                ->first();
            if (! $account) {
                $validator->errors()->add('account_id', 'The selected account is invalid.');
                return;
            }

            if ($type === 'transfer') {
                if (empty($toAccountId)) {
                    $validator->errors()->add('to_account_id', 'The to_account_id field is required for transfer.');
                    return;
                }
                if ($accountId == $toAccountId) {
                    $validator->errors()->add('to_account_id', 'The to_account_id must be different from account_id.');
                }
                $toAccount = Account::where('household_id', $household->id)
                    ->where('id', $toAccountId)
                    ->where('is_active', true)
                    ->first();
                if (! $toAccount) {
                    $validator->errors()->add('to_account_id', 'The selected to_account_id is invalid.');
                }
            } else {
                if (! is_null($toAccountId)) {
                    $validator->errors()->add('to_account_id', 'The to_account_id must be null for income and expense.');
                }
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $recurring = RecurringTransaction::create([
            'household_id' => $household->id,
            'type' => $request->input('type'),
            'amount' => number_format($request->input('amount'), 2, '.', ''),
            'description' => $request->filled('description') ? trim($request->input('description')) : null,
            'frequency' => $request->input('frequency'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'account_id' => $request->input('account_id'),
            'to_account_id' => $request->input('to_account_id'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $recurring->load(['account:id,name,type', 'toAccount:id,name,type']);

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'household_id' => $household->id,
            'action' => 'recurring_transaction_created',
            'entity_type' => 'recurring_transaction',
            'entity_id' => $recurring->id,
            'description' => null,
            'metadata' => null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
        ]);

        return response()->json([
            'message' => 'Recurring transaction created successfully.',
            'recurring_transaction' => $recurring,
        ], 201);
    }

    /**
     * Display the specified recurring transaction.
     */
    public function show(Household $household, RecurringTransaction $recurring): JsonResponse
    {
        if ((int) $recurring->household_id !== (int) $household->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $recurring->load(['account:id,name,type', 'toAccount:id,name,type']);

        return response()->json(['recurring_transaction' => $recurring], 200);
    }

    /**
     * Update an existing recurring transaction.
     */
    public function update(Request $request, Household $household, RecurringTransaction $recurring): JsonResponse
    {
        if ((int) $recurring->household_id !== (int) $household->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $updatable = ['type', 'amount', 'description', 'frequency', 'start_date', 'end_date', 'account_id', 'to_account_id', 'is_active'];
        $provided = array_intersect($updatable, array_keys($request->all()));
        if (empty($provided)) {
            return response()->json([
                'message' => 'At least one updatable field must be provided.',
            ], 422);
        }

        $validator = Validator::make($request->only($updatable), [
            'type' => ['sometimes', Rule::in(['income', 'expense', 'transfer'])],
            'amount' => ['sometimes', 'numeric', 'gt:0'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'frequency' => ['sometimes', Rule::in(['daily', 'weekly', 'monthly', 'yearly'])],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date'],
            'account_id' => ['sometimes', 'integer'],
            'to_account_id' => ['sometimes', 'nullable', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validator->after(function ($validator) use ($request, $household, $recurring) {
            $type = $request->input('type', $recurring->type);
            $accountId = $request->input('account_id', $recurring->account_id);
            $toAccountId = $request->has('to_account_id') ? $request->input('to_account_id') : $recurring->to_account_id;

            $account = Account::where('household_id', $household->id)
                ->where('id', $accountId)
                ->where('is_active', true)
                ->first();
            if (! $account) {
                $validator->errors()->add('account_id', 'The selected account is invalid.');
                return;
            }

            if ($type === 'transfer') {
                if (empty($toAccountId)) {
                    $validator->errors()->add('to_account_id', 'The to_account_id field is required for transfer.');
                    return;
                }
                if ($accountId == $toAccountId) {
                    $validator->errors()->add('to_account_id', 'The to_account_id must be different from account_id.');
                }
                $toAccount = Account::where('household_id', $household->id)
                    ->where('id', $toAccountId)
                    ->where('is_active', true)
                    ->first();
                if (! $toAccount) {
                    $validator->errors()->add('to_account_id', 'The selected to_account_id is invalid.');
                }
            } else {
                if ($request->has('to_account_id') && ! is_null($toAccountId)) {
                    $validator->errors()->add('to_account_id', 'The to_account_id must be null for income and expense.');
                }
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $updateData = [];
        if ($request->filled('type')) $updateData['type'] = $request->input('type');
        if ($request->filled('amount')) $updateData['amount'] = number_format($request->input('amount'), 2, '.', '');
        if ($request->has('description')) $updateData['description'] = $request->filled('description') ? trim($request->input('description')) : null;
        if ($request->filled('frequency')) $updateData['frequency'] = $request->input('frequency');
        if ($request->filled('start_date')) $updateData['start_date'] = $request->input('start_date');
        if ($request->has('end_date')) $updateData['end_date'] = $request->input('end_date');
        if ($request->filled('account_id')) $updateData['account_id'] = $request->input('account_id');
        if ($request->has('to_account_id')) $updateData['to_account_id'] = $request->input('to_account_id');
        if ($request->has('is_active')) $updateData['is_active'] = $request->boolean('is_active');

        $recurring->update($updateData);
        $recurring->load(['account:id,name,type', 'toAccount:id,name,type']);

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'household_id' => $household->id,
            'action' => 'recurring_transaction_updated',
            'entity_type' => 'recurring_transaction',
            'entity_id' => $recurring->id,
            'description' => null,
            'metadata' => null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
        ]);

        return response()->json([
            'message' => 'Recurring transaction updated successfully.',
            'recurring_transaction' => $recurring,
        ], 200);
    }

    /**
     * Delete a recurring transaction.
     */
    public function destroy(Request $request, Household $household, RecurringTransaction $recurring): JsonResponse
    {
        if ((int) $recurring->household_id !== (int) $household->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $recurringId = $recurring->id;
        $recurring->delete();

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'household_id' => $household->id,
            'action' => 'recurring_transaction_deleted',
            'entity_type' => 'recurring_transaction',
            'entity_id' => $recurringId,
            'description' => null,
            'metadata' => null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
        ]);

        return response()->json([
            'message' => 'Recurring transaction deleted successfully.',
        ], 200);
    }

    /**
     * Process / Generate actual transaction from recurring template.
     */
    public function process(Request $request, Household $household, RecurringTransaction $recurring): JsonResponse
    {
        if ((int) $recurring->household_id !== (int) $household->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $transactionDate = $request->input('transaction_date', date('Y-m-d'));

        $transaction = Transaction::create([
            'household_id' => $household->id,
            'type' => $recurring->type,
            'amount' => $recurring->amount,
            'description' => $recurring->description ? "[Rutin] {$recurring->description}" : '[Rutin]',
            'transaction_date' => $transactionDate,
            'account_id' => $recurring->account_id,
            'to_account_id' => $recurring->to_account_id,
        ]);

        $recurring->update(['last_generated_at' => $transactionDate]);

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'household_id' => $household->id,
            'action' => 'recurring_transaction_processed',
            'entity_type' => 'transaction',
            'entity_id' => $transaction->id,
            'description' => null,
            'metadata' => ['recurring_transaction_id' => $recurring->id],
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
        ]);

        return response()->json([
            'message' => 'Transaction generated successfully from recurring template.',
            'transaction' => $transaction,
        ], 201);
    }

    /**
     * Upcoming Bills & Reminders for Household.
     */
    public function upcomingBills(Request $request, Household $household): JsonResponse
    {
        $days = (int) $request->input('days', 7);
        $today = date('Y-m-d');
        $maxDate = date('Y-m-d', strtotime("+{$days} days"));

        $recurringItems = RecurringTransaction::where('household_id', $household->id)
            ->where('is_active', true)
            ->get();

        $upcoming = [];
        $members = \App\Models\HouseholdMember::where('household_id', $household->id)->get();

        foreach ($recurringItems as $item) {
            $dueDate = $item->start_date ? (is_string($item->start_date) ? $item->start_date : $item->start_date->format('Y-m-d')) : $today;
            if ($item->last_generated_at) {
                $last = is_string($item->last_generated_at) ? $item->last_generated_at : $item->last_generated_at->format('Y-m-d');
                if ($item->frequency === 'daily') {
                    $dueDate = date('Y-m-d', strtotime('+1 day', strtotime($last)));
                } elseif ($item->frequency === 'weekly') {
                    $dueDate = date('Y-m-d', strtotime('+1 week', strtotime($last)));
                } elseif ($item->frequency === 'monthly') {
                    $dueDate = date('Y-m-d', strtotime('+1 month', strtotime($last)));
                } elseif ($item->frequency === 'yearly') {
                    $dueDate = date('Y-m-d', strtotime('+1 year', strtotime($last)));
                }
            }

            if ($dueDate <= $maxDate) {
                $upcoming[] = [
                    'id' => $item->id,
                    'type' => $item->type,
                    'amount' => number_format((float) $item->amount, 2, '.', ''),
                    'description' => $item->description ?: ucfirst($item->type) . ' Rutin',
                    'frequency' => $item->frequency,
                    'due_date' => $dueDate,
                ];

                foreach ($members as $member) {
                    $alreadyNotified = \App\Models\Notification::where('user_id', $member->user_id)
                        ->where('type', 'upcoming_bill')
                        ->where('data->recurring_transaction_id', $item->id)
                        ->where('data->due_date', $dueDate)
                        ->exists();

                    if (! $alreadyNotified) {
                        \App\Services\NotificationService::send(
                            $member->user_id,
                            $household->id,
                            'upcoming_bill',
                            'Pengingat Tagihan Rutin',
                            'Tagihan/transaksi rutin "' . ($item->description ?: $item->type) . '" sebesar Rp ' . number_format((float)$item->amount, 0, ',', '.') . ' jatuh tempo pada ' . $dueDate . '.',
                            [
                                'recurring_transaction_id' => $item->id,
                                'due_date' => $dueDate,
                                'amount' => (float) $item->amount,
                            ]
                        );
                    }
                }
            }
        }

        return response()->json([
            'upcoming_bills' => $upcoming,
        ], 200);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Household;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Http\Controllers\Controller;

class TransactionController extends Controller
{
    /**
     * Display a listing of the transactions for the given household.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Household  $household  (route model binding)
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request, Household $household): JsonResponse
    {
        // Middleware ensures the authenticated user is a member of the household.
        $query = Transaction::where('household_id', $household->id);

        if ($request->filled('type') && in_array($request->input('type'), ['income', 'expense', 'transfer'])) {
            $query->where('type', $request->input('type'));
        }

        $transactions = $query
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->get([
                'id',
                'type',
                'amount',
                'description',
                'transaction_date',
                'account_id',
                'to_account_id',
            ]);

        return response()->json(['transactions' => $transactions]);
    }
    /**
     * Store a new transaction for the given household.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Household  $household
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, Household $household): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => ['required', Rule::in(['income', 'expense', 'transfer'])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:255'],
            'transaction_date' => ['required', 'date'],
            'account_id' => ['required', 'integer'],
            'to_account_id' => ['nullable', 'integer'],
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
                    $validator->errors()->add('to_account_id', 'The to_account_id field is required for transfer transactions.');
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
                    $validator->errors()->add('to_account_id', 'The to_account_id must be null for income and expense transactions.');
                }
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $transaction = Transaction::create([
            'household_id' => $household->id,
            'type' => $request->input('type'),
            'amount' => number_format($request->input('amount'), 2, '.', ''),
            'description' => $request->filled('description') ? trim($request->input('description')) : null,
            'transaction_date' => $request->input('transaction_date'),
            'account_id' => $request->input('account_id'),
            'to_account_id' => $request->input('to_account_id'),
        ]);

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'household_id' => $household->id,
            'action' => 'transaction_created',
            'entity_type' => 'transaction',
            'entity_id' => $transaction->id,
            'description' => null,
            'metadata' => null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
        ]);

        return response()->json([
            'message' => 'Transaction created successfully.',
            'transaction' => $transaction->only([
                'id', 'type', 'amount', 'description', 'transaction_date', 'account_id', 'to_account_id',
            ]),
        ], 201);
    }

    /**
     * Display the specified transaction for the given household.
     *
     * @param  \App\Models\Household  $household
     * @param  \App\Models\Transaction  $transaction
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Household $household, Transaction $transaction): JsonResponse
    {
        if ((int) $transaction->household_id !== (int) $household->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json([
            'transaction' => $transaction->only([
                'id',
                'type',
                'amount',
                'description',
                'transaction_date',
                'account_id',
                'to_account_id',
            ]),
        ], 200);
    }

    /**
    * Update an existing transaction for the given household.
    *
    * @param  \Illuminate\Http\Request  $request
    * @param  \App\Models\Household  $household
    * @param  \App\Models\Transaction $transactionModel
    * @return \Illuminate\Http\JsonResponse
    */
    public function update(Request $request, Household $household, Transaction $transaction): JsonResponse
    {
        // Debug IDs
        logger()->debug('Ownership check IDs', ['transaction_household_id' => $transaction->household_id, 'household_id' => $household->id]);
        if ((int) $transaction->household_id !== (int) $household->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        // Determine which fields are being updated
        $updatable = ['type', 'amount', 'description', 'transaction_date', 'account_id', 'to_account_id'];
        $provided = array_intersect($updatable, array_keys($request->all()));
        if (empty($provided)) {
            return response()->json([
                'message' => 'At least one updatable field must be provided.'
            ], 422);
        }

        // Validation rules (only for provided fields)
        $rules = [
            'type' => ['sometimes', Rule::in(['income', 'expense', 'transfer'])],
            'amount' => ['sometimes', 'numeric', 'gt:0'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'transaction_date' => ['sometimes', 'date'],
            'account_id' => ['sometimes', 'integer'],
            'to_account_id' => ['sometimes', 'nullable', 'integer'],
        ];
        $validator = Validator::make($request->only($updatable), $rules);

        // After‑validation checks using merged data (existing + new)
        $validator->after(function ($validator) use ($request, $household, $transaction) {
            // Merge existing transaction data with incoming data
            $data = $transaction->only(['type', 'account_id', 'to_account_id']);
            foreach ($request->only(['type', 'account_id', 'to_account_id']) as $key => $value) {
                if (!is_null($value)) {
                    $data[$key] = $value;
                }
            }
            $type = $data['type'];
            $accountId = $data['account_id'];
            $toAccountId = $data['to_account_id'] ?? null;

            // Validate source account belongs to household and is active
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
                    $validator->errors()->add('to_account_id', 'The to_account_id field is required for transfer transactions.');
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
                // Only enforce null when the request explicitly provides a non‑null value
                if ($request->has('to_account_id') && ! is_null($toAccountId)) {
                    $validator->errors()->add('to_account_id', 'The to_account_id must be null for income and expense transactions.');
                }
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Prepare data for update
        $updateData = [];
        if ($request->filled('type')) {
            $updateData['type'] = $request->input('type');
        }
        if ($request->filled('amount')) {
            $updateData['amount'] = number_format($request->input('amount'), 2, '.', '');
        }
        if ($request->has('description')) {
            $updateData['description'] = $request->filled('description') ? trim($request->input('description')) : null;
        }
        if ($request->filled('transaction_date')) {
            $updateData['transaction_date'] = $request->input('transaction_date');
        }
        if ($request->filled('account_id')) {
            $updateData['account_id'] = $request->input('account_id');
        }
        if ($request->has('to_account_id')) {
            $updateData['to_account_id'] = $request->input('to_account_id');
        }

        $transaction->update($updateData);

        // Activity log
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'household_id' => $household->id,
            'action' => 'transaction_updated',
            'entity_type' => 'transaction',
            'entity_id' => $transaction->id,
            'description' => null,
            'metadata' => null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
        ]);

        return response()->json([
            'message' => 'Transaction updated successfully.',
            'transaction' => $transaction->only([
                'id', 'type', 'amount', 'description', 'transaction_date', 'account_id', 'to_account_id',
            ]),
        ], 200);
    }
    /**
     * Delete a transaction for the given household.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Household  $household
     * @param  \App\Models\Transaction $transactionModel
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, Household $household, Transaction $transaction): JsonResponse
    {
        // Ensure the transaction belongs to the household
        if ((int) $transaction->household_id !== (int) $household->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        // Preserve ID for activity log before deletion
        $transactionId = $transaction->id;

        // Perform hard delete
        $transaction->delete();

        // Log activity
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'household_id' => $household->id,
            'action' => 'transaction_deleted',
            'entity_type' => 'transaction',
            'entity_id' => $transactionId,
            'description' => null,
            'metadata' => null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
        ]);

        return response()->json([
            'message' => 'Transaction deleted successfully.'
        ], 200);
    }
}




?>

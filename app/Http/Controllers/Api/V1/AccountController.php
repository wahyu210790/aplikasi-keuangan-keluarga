<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Account;
use App\Models\Household;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use App\Models\ActivityLog;

class AccountController extends Controller
{
    /**
     * List accounts belonging to a household.
     *
     * @param  \App\Models\Household  $household  (route model binding)
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Household $household): JsonResponse
    {
        // The middleware ensures the authenticated user is a member of the household.
        // We only return the fields required by the spec.
        $accounts = Account::where('household_id', $household->id)
            ->orderBy('id', 'asc')
            ->get([
                'id',
                'name',
                'type',
                'initial_balance',
                'is_active',
            ]);

        return response()->json([
            'accounts' => $accounts,
        ]);
    }
    /**
     * Create a new account for the given household.
     *
     * @param  \App\Models\Household  $household (route model binding)
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Household $household, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['bank', 'cash', 'e_wallet'])],
            'initial_balance' => ['required', 'numeric', 'min:0'],
            // prohibited fields
            'household_id' => ['prohibited'],
            'is_active' => ['prohibited'],
            'balance' => ['prohibited'],
            'current_balance' => ['prohibited'],
            'description' => ['prohibited'],
            'currency' => ['prohibited'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $name = trim($request->input('name'));
        if ($name === '') {
            return response()->json([
                'message' => 'Validation errors',
                'errors' => ['name' => ['The name field must not be empty.']],
            ], 422);
        }

        $type = $request->input('type');
        $initialBalance = number_format($request->input('initial_balance'), 2, '.', '');

        return DB::transaction(function () use ($household, $name, $type, $initialBalance, $request) {
            $account = Account::create([
                'household_id' => $household->id,
                'name' => $name,
                'type' => $type,
                'initial_balance' => $initialBalance,
                'is_active' => true,
            ]);

            ActivityLog::create([
                'user_id' => $request->user()->id,
                'household_id' => $household->id,
                'action' => 'account_created',
                'entity_type' => 'account',
                'entity_id' => $account->id,
                'description' => "Account {$account->name} created",
                'created_at' => now(),
            ]);

            return response()->json([
                'message' => 'Account created successfully.',
                'account' => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'type' => $account->type,
                    'initial_balance' => $account->initial_balance,
                    'is_active' => $account->is_active,
                ],
            ], 201);
        });
    }
    /**
     * Update an existing account.
     *
     * @param  \App\Models\Household  $household
     * @param  \App\Models\Account   $account (route model binding)
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Household $household, Account $account, Request $request): JsonResponse
    {
        // Ensure the account belongs to the household (tenant isolation)
        if ($account->household_id !== $household->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', Rule::in(['bank', 'cash', 'e_wallet'])],
            'initial_balance' => ['sometimes', 'numeric', 'min:0'],
            // prohibited fields
            'household_id' => ['prohibited'],
            'is_active' => ['prohibited'],
            'balance' => ['prohibited'],
            'current_balance' => ['prohibited'],
            'description' => ['prohibited'],
            'currency' => ['prohibited'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        if (empty($validated)) {
            return response()->json([
                'message' => 'Validation errors',
                'errors' => ['request' => ['At least one updatable field must be provided.']],
            ], 422);
        }

        // Process name trimming if provided
        if (array_key_exists('name', $validated)) {
            $name = trim($validated['name']);
            if ($name === '') {
                return response()->json([
                    'message' => 'Validation errors',
                    'errors' => ['name' => ['The name field must not be empty.']],
                ], 422);
            }
            $validated['name'] = $name;
        }

        // Process initial_balance formatting if provided
        if (array_key_exists('initial_balance', $validated)) {
            $validated['initial_balance'] = number_format($validated['initial_balance'], 2, '.', '');
        }

        // Perform the update
        $account->update($validated);

        // Activity log
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'household_id' => $household->id,
            'action' => 'account_updated',
            'entity_type' => 'account',
            'entity_id' => $account->id,
            'description' => "Account {$account->name} updated",
            'created_at' => now(),
        ]);

        return response()->json([
            'message' => 'Account updated successfully.',
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type,
                'initial_balance' => $account->initial_balance,
                'is_active' => $account->is_active,
            ],
        ], 200);
    }
    /**
    * Archive (deactivate) an account.
    *
    * @param  \App\Models\Household  $household
    * @param  \App\Models\Account   $account (route model binding)
    * @param  \Illuminate\Http\Request $request
    * @return \Illuminate\Http\JsonResponse
    */
    public function archive(Household $household, Account $account, Request $request): JsonResponse
    {
        // Tenant isolation: ensure the account belongs to the household
        if ($account->household_id !== $household->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Reject any request body fields – all fields are prohibited
        $validator = Validator::make($request->all(), [
            'household_id' => ['prohibited'],
            'is_active' => ['prohibited'],
            'name' => ['prohibited'],
            'type' => ['prohibited'],
            'initial_balance' => ['prohibited'],
            'balance' => ['prohibited'],
            'current_balance' => ['prohibited'],
            'description' => ['prohibited'],
            'currency' => ['prohibited'],
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $wasActive = $account->is_active;
        if ($wasActive) {
            $account->is_active = false;
            $account->save();

            ActivityLog::create([
                'user_id' => $request->user()->id,
                'household_id' => $household->id,
                'action' => 'account_archived',
                'entity_type' => 'account',
                'entity_id' => $account->id,
                'description' => "Account {$account->name} archived",
                'created_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Account archived successfully.',
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type,
                'initial_balance' => $account->initial_balance,
                'is_active' => $account->is_active,
            ],
        ], 200);
    }
}




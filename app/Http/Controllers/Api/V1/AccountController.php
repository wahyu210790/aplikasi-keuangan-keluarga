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
     * Helper to check if the user is a household owner.
     */
    private function isHouseholdOwner(Household $household, int $userId): bool
    {
        $member = \App\Models\HouseholdMember::where('household_id', $household->id)
            ->where('user_id', $userId)
            ->first();

        return $member && $member->role === 'household_owner';
    }

    /**
     * List accounts belonging to a household.
     */
    public function index(Household $household): JsonResponse
    {
        $accounts = Account::with('user:id,name')
            ->where('household_id', $household->id)
            ->orderBy('id', 'asc')
            ->get([
                'id',
                'household_id',
                'user_id',
                'name',
                'type',
                'initial_balance',
                'is_active',
            ])
            ->map(function ($acc) {
                return [
                    'id' => $acc->id,
                    'household_id' => $acc->household_id,
                    'user_id' => $acc->user_id,
                    'user_name' => $acc->user ? $acc->user->name : null,
                    'name' => $acc->name,
                    'type' => $acc->type,
                    'initial_balance' => $acc->initial_balance,
                    'is_active' => $acc->is_active,
                ];
            });

        return response()->json([
            'accounts' => $accounts,
        ]);
    }

    /**
     * Create a new account for the given household.
     */
    public function store(Household $household, Request $request): JsonResponse
    {
        $user = $request->user();
        $isOwner = $this->isHouseholdOwner($household, $user->id);

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['bank', 'cash', 'e_wallet'])],
            'initial_balance' => ['required', 'numeric', 'min:0'],
            'user_id' => ['nullable', 'integer'],
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

        $targetUserId = $request->input('user_id');
        if ($targetUserId) {
            // Check if target user belongs to the household
            $isMember = \App\Models\HouseholdMember::where('household_id', $household->id)
                ->where('user_id', $targetUserId)
                ->exists();

            if (!$isMember) {
                return response()->json([
                    'message' => 'Validation errors',
                    'errors' => ['user_id' => ['Specified user is not a member of this household.']],
                ], 422);
            }

            // Only household owner can create account for another member
            if (!$isOwner && (int) $targetUserId !== (int) $user->id) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        } else {
            $targetUserId = $user->id;
        }

        // Quota check: Max 3 active accounts per member
        $activeCount = Account::where('household_id', $household->id)
            ->where('user_id', $targetUserId)
            ->where('is_active', true)
            ->count();

        if ($activeCount >= 3) {
            return response()->json([
                'message' => 'Validation errors',
                'errors' => ['user_id' => ['Maksimal 3 akun aktif per anggota keluarga.']],
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

        return DB::transaction(function () use ($household, $targetUserId, $name, $type, $initialBalance, $user) {
            $account = Account::create([
                'household_id' => $household->id,
                'user_id' => $targetUserId,
                'name' => $name,
                'type' => $type,
                'initial_balance' => $initialBalance,
                'is_active' => true,
            ]);

            ActivityLog::create([
                'user_id' => $user->id,
                'household_id' => $household->id,
                'action' => 'account_created',
                'entity_type' => 'account',
                'entity_id' => $account->id,
                'description' => "Account {$account->name} created",
                'created_at' => now(),
            ]);

            $account->load('user:id,name');

            return response()->json([
                'message' => 'Account created successfully.',
                'account' => [
                    'id' => $account->id,
                    'user_id' => $account->user_id,
                    'user_name' => $account->user ? $account->user->name : null,
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
     */
    public function update(Household $household, Account $account, Request $request): JsonResponse
    {
        $user = $request->user();
        // Ensure the account belongs to the household (tenant isolation)
        if ($account->household_id !== $household->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $isOwner = $this->isHouseholdOwner($household, $user->id);
        // Regular members can only update their own account
        if (!$isOwner && $account->user_id !== null && (int) $account->user_id !== (int) $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', Rule::in(['bank', 'cash', 'e_wallet'])],
            'initial_balance' => ['sometimes', 'numeric', 'min:0'],
            'user_id' => ['sometimes', 'integer'],
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
        $account->load('user:id,name');

        // Activity log
        ActivityLog::create([
            'user_id' => $user->id,
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
                'user_id' => $account->user_id,
                'user_name' => $account->user ? $account->user->name : null,
                'name' => $account->name,
                'type' => $account->type,
                'initial_balance' => $account->initial_balance,
                'is_active' => $account->is_active,
            ],
        ], 200);
    }

    /**
     * Archive (deactivate) an account.
     */
    public function archive(Household $household, Account $account, Request $request): JsonResponse
    {
        $user = $request->user();
        // Tenant isolation: ensure the account belongs to the household
        if ($account->household_id !== $household->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $isOwner = $this->isHouseholdOwner($household, $user->id);
        // Regular members can only archive their own account
        if (!$isOwner && $account->user_id !== null && (int) $account->user_id !== (int) $user->id) {
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
            'user_id' => ['prohibited'],
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
                'user_id' => $user->id,
                'household_id' => $household->id,
                'action' => 'account_archived',
                'entity_type' => 'account',
                'entity_id' => $account->id,
                'description' => "Account {$account->name} archived",
                'created_at' => now(),
            ]);
        }

        $account->load('user:id,name');

        return response()->json([
            'message' => 'Account archived successfully.',
            'account' => [
                'id' => $account->id,
                'user_id' => $account->user_id,
                'user_name' => $account->user ? $account->user->name : null,
                'name' => $account->name,
                'type' => $account->type,
                'initial_balance' => $account->initial_balance,
                'is_active' => $account->is_active,
            ],
        ], 200);
    }
}




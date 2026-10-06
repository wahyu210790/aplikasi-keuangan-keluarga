<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Household;
use App\Models\Saving;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SavingController extends Controller
{
    /**
     * Display a listing of savings for the given household.
     */
    public function index(Request $request, Household $household): JsonResponse
    {
        $query = Saving::with('account:id,name,type')
            ->where('household_id', $household->id);

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

        $savings = $query->orderBy('id', 'desc')->get();

        return response()->json(['savings' => $savings], 200);
    }

    /**
     * Store a new saving goal for the given household.
     */
    public function store(Request $request, Household $household): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'target_amount' => ['required', 'numeric', 'gt:0'],
            'current_amount' => ['sometimes', 'numeric', 'gte:0'],
            'target_date' => ['nullable', 'date'],
            'account_id' => ['nullable', 'integer'],
            'is_completed' => ['sometimes', 'boolean'],
        ]);

        $validator->after(function ($validator) use ($request, $household) {
            $accountId = $request->input('account_id');
            if (! is_null($accountId)) {
                $account = Account::where('household_id', $household->id)
                    ->where('id', $accountId)
                    ->where('is_active', true)
                    ->first();
                if (! $account) {
                    $validator->errors()->add('account_id', 'The selected account is invalid.');
                }
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $targetAmount = number_format($request->input('target_amount'), 2, '.', '');
        $currentAmount = number_format($request->input('current_amount', 0), 2, '.', '');
        $isCompleted = $request->boolean('is_completed', false) || ((float) $currentAmount >= (float) $targetAmount);

        $saving = Saving::create([
            'household_id' => $household->id,
            'account_id' => $request->input('account_id'),
            'name' => trim($request->input('name')),
            'target_amount' => $targetAmount,
            'current_amount' => $currentAmount,
            'target_date' => $request->input('target_date'),
            'is_completed' => $isCompleted,
        ]);

        $saving->load('account:id,name,type');

        $this->checkAndNotifyMilestones($household, $saving, 0.0, (float) $saving->current_amount, (float) $saving->target_amount);

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'household_id' => $household->id,
            'action' => 'saving_created',
            'entity_type' => 'saving',
            'entity_id' => $saving->id,
            'description' => null,
            'metadata' => null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
        ]);

        return response()->json([
            'message' => 'Saving goal created successfully.',
            'saving' => $saving,
        ], 201);
    }

    /**
     * Display the specified saving goal for the given household.
     */
    public function show(Household $household, Saving $saving): JsonResponse
    {
        if ((int) $saving->household_id !== (int) $household->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $saving->load('account:id,name,type');

        return response()->json(['saving' => $saving], 200);
    }

    /**
     * Update an existing saving goal for the given household.
     */
    public function update(Request $request, Household $household, Saving $saving): JsonResponse
    {
        if ((int) $saving->household_id !== (int) $household->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $updatable = ['name', 'target_amount', 'current_amount', 'target_date', 'account_id', 'is_completed'];
        $provided = array_intersect($updatable, array_keys($request->all()));
        if (empty($provided)) {
            return response()->json([
                'message' => 'At least one updatable field must be provided.',
            ], 422);
        }

        $validator = Validator::make($request->only($updatable), [
            'name' => ['sometimes', 'string', 'max:255'],
            'target_amount' => ['sometimes', 'numeric', 'gt:0'],
            'current_amount' => ['sometimes', 'numeric', 'gte:0'],
            'target_date' => ['sometimes', 'nullable', 'date'],
            'account_id' => ['sometimes', 'nullable', 'integer'],
            'is_completed' => ['sometimes', 'boolean'],
        ]);

        $validator->after(function ($validator) use ($request, $household) {
            if ($request->has('account_id') && ! is_null($request->input('account_id'))) {
                $accountId = $request->input('account_id');
                $account = Account::where('household_id', $household->id)
                    ->where('id', $accountId)
                    ->where('is_active', true)
                    ->first();
                if (! $account) {
                    $validator->errors()->add('account_id', 'The selected account is invalid.');
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
        if ($request->filled('name')) $updateData['name'] = trim($request->input('name'));
        if ($request->filled('target_amount')) $updateData['target_amount'] = number_format($request->input('target_amount'), 2, '.', '');
        if ($request->has('current_amount')) $updateData['current_amount'] = number_format($request->input('current_amount'), 2, '.', '');
        if ($request->has('target_date')) $updateData['target_date'] = $request->input('target_date');
        if ($request->has('account_id')) $updateData['account_id'] = $request->input('account_id');
        if ($request->has('is_completed')) $updateData['is_completed'] = $request->boolean('is_completed');

        // Check if current_amount >= target_amount
        $oldCurrent = (float) $saving->current_amount;
        $target = (float) ($updateData['target_amount'] ?? $saving->target_amount);
        $current = (float) ($updateData['current_amount'] ?? $saving->current_amount);
        if ($current >= $target) {
            $updateData['is_completed'] = true;
        }

        $saving->update($updateData);
        $saving->load('account:id,name,type');

        $this->checkAndNotifyMilestones($household, $saving, $oldCurrent, $current, $target);

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'household_id' => $household->id,
            'action' => 'saving_updated',
            'entity_type' => 'saving',
            'entity_id' => $saving->id,
            'description' => null,
            'metadata' => null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
        ]);

        return response()->json([
            'message' => 'Saving goal updated successfully.',
            'saving' => $saving,
        ], 200);
    }

    /**
     * Delete a saving goal for the given household.
     */
    public function destroy(Request $request, Household $household, Saving $saving): JsonResponse
    {
        if ((int) $saving->household_id !== (int) $household->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $savingId = $saving->id;
        $saving->delete();

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'household_id' => $household->id,
            'action' => 'saving_deleted',
            'entity_type' => 'saving',
            'entity_id' => $savingId,
            'description' => null,
            'metadata' => null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
        ]);

        return response()->json([
            'message' => 'Saving goal deleted successfully.',
        ], 200);
    }

    /**
     * Helper to check and notify goal milestones (25%, 50%, 75%, 100%).
     */
    private function checkAndNotifyMilestones(Household $household, Saving $saving, float $oldCurrent, float $newCurrent, float $target): void
    {
        if ($target <= 0) {
            return;
        }

        $oldPct = round(($oldCurrent / $target) * 100, 2);
        $newPct = round(($newCurrent / $target) * 100, 2);

        $milestones = [25, 50, 75, 100];
        $members = \App\Models\HouseholdMember::where('household_id', $household->id)->get();

        foreach ($milestones as $milestone) {
            if ($oldPct < $milestone && $newPct >= $milestone) {
                foreach ($members as $member) {
                    $alreadyNotified = \App\Models\Notification::where('user_id', $member->user_id)
                        ->where('type', 'goal_milestone')
                        ->where('data->saving_id', $saving->id)
                        ->where('data->milestone', $milestone)
                        ->exists();

                    if (! $alreadyNotified) {
                        \App\Services\NotificationService::send(
                            $member->user_id,
                            $household->id,
                            'goal_milestone',
                            "Pencapaian Target Tabungan: {$milestone}%",
                            "Target tabungan \"{$saving->name}\" telah mencapai {$milestone}% (Rp " . number_format($newCurrent, 0, ',', '.') . " dari Rp " . number_format($target, 0, ',', '.') . ").",
                            [
                                'saving_id' => $saving->id,
                                'milestone' => $milestone,
                                'current_amount' => $newCurrent,
                                'target_amount' => $target,
                            ]
                        );
                    }
                }
            }
        }
    }
}

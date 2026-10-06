<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Household;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\QuotaEnforcementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SubscriptionController extends Controller
{
    /**
     * Show active subscription & quota usage stats.
     */
    public function show(Request $request, Household $household): JsonResponse
    {
        $stats = QuotaEnforcementService::getUsageStats($household);
        return response()->json($stats);
    }

    /**
     * List all available plans.
     */
    public function plans(): JsonResponse
    {
        $plans = Plan::where('is_active', true)->get();
        return response()->json(['plans' => $plans]);
    }

    /**
     * Subscribe or upgrade household to a plan.
     */
    public function store(Request $request, Household $household): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'plan_id' => ['required', 'exists:plans,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $plan = Plan::findOrFail($request->input('plan_id'));

        // Expire any existing active subscriptions for household
        Subscription::where('household_id', $household->id)
            ->where('status', 'active')
            ->update(['status' => 'expired']);

        $durationDays = $plan->duration_days ?: 30;

        $subscription = Subscription::create([
            'household_id' => $household->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addDays($durationDays),
            'notes' => "Subscribed to {$plan->name}",
        ]);

        return response()->json([
            'message' => 'Subscription updated successfully',
            'subscription' => $subscription->load('plan'),
        ], 201);
    }
}

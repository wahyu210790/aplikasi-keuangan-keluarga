<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    /**
     * Get global platform KPI statistics for Super Admin.
     */
    public function stats()
    {
        $totalUsers = User::count();
        $totalHouseholds = Household::count();
        $activeHouseholds = Household::where('status', 'active')->count();
        $suspendedHouseholds = Household::where('status', 'suspended')->count();
        $totalTransactions = Transaction::count();
        $totalVolume = (float) Transaction::sum('amount');
        $activeSubscriptions = Subscription::where('status', 'active')->count();

        $subscriptionOverview = Plan::withCount(['subscriptions' => function ($query) {
            $query->where('status', 'active');
        }])->get()->map(function ($plan) {
            return [
                'plan_id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'price' => (float) $plan->price,
                'active_subscriptions' => $plan->subscriptions_count,
            ];
        });

        return response()->json([
            'data' => [
                'total_users' => $totalUsers,
                'total_households' => $totalHouseholds,
                'active_households' => $activeHouseholds,
                'suspended_households' => $suspendedHouseholds,
                'total_transactions' => $totalTransactions,
                'total_volume' => $totalVolume,
                'active_subscriptions' => $activeSubscriptions,
                'subscription_overview' => $subscriptionOverview,
            ],
        ]);
    }

    /**
     * Get paginated list of tenants/households for Super Admin.
     */
    public function households(Request $request)
    {
        $query = Household::withCount(['members', 'subscriptions']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = (int) $request->input('per_page', 15);
        $households = $query->latest()->paginate($perPage);

        $households->getCollection()->transform(function ($household) {
            $ownerMember = HouseholdMember::where('household_id', $household->id)
                ->where('role', 'household_owner')
                ->with('user')
                ->first();

            $ownerUser = $ownerMember && $ownerMember->user ? [
                'id' => $ownerMember->user->id,
                'name' => $ownerMember->user->name,
                'email' => $ownerMember->user->email,
            ] : null;

            return [
                'id' => $household->id,
                'name' => $household->name,
                'description' => $household->description,
                'status' => $household->status ?? 'active',
                'created_at' => $household->created_at ? $household->created_at->toIso8601String() : null,
                'members_count' => $household->members_count,
                'subscriptions_count' => $household->subscriptions_count,
                'owner' => $ownerUser,
            ];
        });

        return response()->json($households);
    }

    /**
     * Get detailed tenant/household information for Super Admin.
     */
    public function showHousehold(Household $household)
    {
        $household->load(['members.user']);

        $members = $household->members->map(function ($member) {
            return [
                'id' => $member->id,
                'role' => $member->role,
                'joined_at' => $member->created_at ? $member->created_at->toIso8601String() : null,
                'user' => $member->user ? [
                    'id' => $member->user->id,
                    'name' => $member->user->name,
                    'email' => $member->user->email,
                    'global_role' => $member->user->global_role,
                ] : null,
            ];
        });

        $activeSubscription = Subscription::where('household_id', $household->id)
            ->where('status', 'active')
            ->with('plan')
            ->first();

        $accountsCount = \App\Models\Account::where('household_id', $household->id)->count();
        $transactionsCount = Transaction::where('household_id', $household->id)->count();
        $totalVolume = (float) Transaction::where('household_id', $household->id)->sum('amount');

        return response()->json([
            'data' => [
                'id' => $household->id,
                'name' => $household->name,
                'description' => $household->description,
                'status' => $household->status ?? 'active',
                'created_at' => $household->created_at ? $household->created_at->toIso8601String() : null,
                'members' => $members,
                'active_subscription' => $activeSubscription ? [
                    'id' => $activeSubscription->id,
                    'status' => $activeSubscription->status,
                    'starts_at' => $activeSubscription->starts_at ? $activeSubscription->starts_at->toIso8601String() : null,
                    'expires_at' => $activeSubscription->expires_at ? $activeSubscription->expires_at->toIso8601String() : null,
                    'plan' => $activeSubscription->plan ? [
                        'id' => $activeSubscription->plan->id,
                        'name' => $activeSubscription->plan->name,
                        'slug' => $activeSubscription->plan->slug,
                    ] : null,
                ] : null,
                'accounts_count' => $accountsCount,
                'transactions_count' => $transactionsCount,
                'total_volume' => $totalVolume,
            ],
        ]);
    }

    /**
     * Update tenant status or basic metadata for Super Admin.
     */
    public function updateHousehold(Request $request, Household $household)
    {
        $validated = $request->validate([
            'status' => 'sometimes|string|in:active,suspended,inactive',
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string|max:1000',
        ]);

        $household->update($validated);

        return response()->json([
            'message' => 'Kontrol tenant berhasil diperbarui.',
            'data' => [
                'id' => $household->id,
                'name' => $household->name,
                'description' => $household->description,
                'status' => $household->status,
                'updated_at' => $household->updated_at ? $household->updated_at->toIso8601String() : null,
            ],
        ]);
    }

    // ==========================================
    // TASK 16.5 — USER MANAGEMENT
    // ==========================================

    /**
     * Get paginated list of users for Super Admin.
     */
    public function users(Request $request)
    {
        $query = User::withCount('householdMembers');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('global_role')) {
            $query->where('global_role', $request->input('global_role'));
        }

        $perPage = (int) $request->input('per_page', 15);
        $users = $query->latest()->paginate($perPage);

        $users->getCollection()->transform(function ($u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'global_role' => $u->global_role ?? 'user',
                'email_verified_at' => $u->email_verified_at ? $u->email_verified_at->toIso8601String() : null,
                'created_at' => $u->created_at ? $u->created_at->toIso8601String() : null,
                'households_count' => $u->household_members_count,
            ];
        });

        return response()->json($users);
    }

    /**
     * Get detailed user profile and household memberships for Super Admin.
     */
    public function showUser(User $user)
    {
        $memberships = HouseholdMember::where('user_id', $user->id)
            ->with('household')
            ->get()
            ->map(function ($m) {
                return [
                    'household_id' => $m->household_id,
                    'household_name' => $m->household ? $m->household->name : null,
                    'role' => $m->role,
                    'joined_at' => $m->created_at ? $m->created_at->toIso8601String() : null,
                ];
            });

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'global_role' => $user->global_role ?? 'user',
                'email_verified_at' => $user->email_verified_at ? $user->email_verified_at->toIso8601String() : null,
                'created_at' => $user->created_at ? $user->created_at->toIso8601String() : null,
                'households' => $memberships,
            ]
        ]);
    }

    // ==========================================
    // TASK 16.6 — ADMIN RESET PASSWORD
    // ==========================================

    /**
     * Force reset password of a user by Super Admin.
     */
    public function resetUserPassword(Request $request, User $user)
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Revoke all existing Sanctum tokens
        $user->tokens()->delete();

        // Audit Log
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'household_id' => null,
            'action' => 'admin_user_password_reset',
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'description' => "Super Admin reset password user #{$user->id} ({$user->email})",
            'metadata' => ['target_user_id' => $user->id],
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
        ]);

        return response()->json([
            'message' => 'Password user berhasil di-reset dan token aktif di-revoke.',
            'user_id' => $user->id,
        ], 200);
    }

    // ==========================================
    // TASK 16.7 — PLAN MANAGEMENT
    // ==========================================

    /**
     * List all subscription plans for Super Admin.
     */
    public function plans(Request $request)
    {
        $plans = Plan::withCount('subscriptions')->get()->map(function ($plan) {
            return [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'description' => $plan->description,
                'price' => (float) $plan->price,
                'duration_days' => $plan->duration_days,
                'max_members' => $plan->max_members,
                'max_accounts' => $plan->max_accounts,
                'max_transactions_per_month' => $plan->max_transactions_per_month,
                'is_active' => (bool) $plan->is_active,
                'subscriptions_count' => $plan->subscriptions_count,
            ];
        });

        return response()->json(['plans' => $plans]);
    }

    /**
     * Create a new subscription plan.
     */
    public function storePlan(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:plans,slug',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration_days' => 'required|integer|min:0',
            'max_members' => 'nullable|integer|min:1',
            'max_accounts' => 'nullable|integer|min:1',
            'max_transactions_per_month' => 'nullable|integer|min:1',
            'is_active' => 'sometimes|boolean',
        ]);

        $plan = Plan::create($validated);

        return response()->json([
            'message' => 'Paket langganan berhasil dibuat.',
            'plan' => $plan,
        ], 201);
    }

    /**
     * Show plan details.
     */
    public function showPlan(Plan $plan)
    {
        $plan->loadCount('subscriptions');
        return response()->json(['plan' => $plan]);
    }

    /**
     * Update subscription plan details.
     */
    public function updatePlan(Request $request, Plan $plan)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('plans', 'slug')->ignore($plan->id)],
            'description' => 'sometimes|nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'duration_days' => 'sometimes|integer|min:0',
            'max_members' => 'sometimes|nullable|integer|min:1',
            'max_accounts' => 'sometimes|nullable|integer|min:1',
            'max_transactions_per_month' => 'sometimes|nullable|integer|min:1',
            'is_active' => 'sometimes|boolean',
        ]);

        $plan->update($validated);

        return response()->json([
            'message' => 'Paket langganan berhasil diperbarui.',
            'plan' => $plan,
        ]);
    }

    /**
     * Delete subscription plan (only if not assigned to subscriptions).
     */
    public function destroyPlan(Plan $plan)
    {
        if ($plan->subscriptions()->exists()) {
            return response()->json([
                'message' => 'Paket langganan tidak dapat dihapus karena sedang digunakan oleh subscription.',
            ], 422);
        }

        $plan->delete();

        return response()->json([
            'message' => 'Paket langganan berhasil dihapus.',
        ]);
    }

    // ==========================================
    // TASK 16.8 — SUBSCRIPTION MANAGEMENT
    // ==========================================

    /**
     * List all subscriptions for Super Admin.
     */
    public function subscriptions(Request $request)
    {
        $query = Subscription::with(['household:id,name', 'plan:id,name,slug,price']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = (int) $request->input('per_page', 15);
        $subs = $query->latest()->paginate($perPage);

        return response()->json($subs);
    }

    /**
     * Assign or change subscription plan for a household.
     */
    public function assignHouseholdSubscription(Request $request, Household $household)
    {
        $validated = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'duration_days' => 'nullable|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        $plan = Plan::findOrFail($validated['plan_id']);
        $days = $validated['duration_days'] ?? ($plan->duration_days ?: 30);
        $startsAt = now();
        $expiresAt = $days > 0 ? now()->addDays($days) : null;

        // Deactivate existing active subscriptions for household
        Subscription::where('household_id', $household->id)->update(['status' => 'inactive']);

        $subscription = Subscription::create([
            'household_id' => $household->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
            'notes' => $validated['notes'] ?? 'Assigned by Super Admin',
        ]);

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'household_id' => $household->id,
            'action' => 'admin_subscription_changed',
            'entity_type' => 'subscription',
            'entity_id' => $subscription->id,
            'description' => "Super Admin menugaskan paket \"{$plan->name}\" ke household #{$household->id}",
            'metadata' => ['plan_id' => $plan->id],
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
        ]);

        return response()->json([
            'message' => 'Paket langganan household berhasil diperbarui.',
            'subscription' => $subscription->load('plan'),
        ], 200);
    }

    /**
     * Update subscription status or extend expiration date.
     */
    public function updateSubscription(Request $request, Subscription $subscription)
    {
        $validated = $request->validate([
            'status' => 'sometimes|string|in:active,suspended,inactive,canceled',
            'extend_days' => 'sometimes|nullable|integer|min:1',
            'expires_at' => 'sometimes|nullable|date',
        ]);

        if (isset($validated['extend_days']) && $validated['extend_days'] > 0) {
            $base = $subscription->expires_at && $subscription->expires_at->isFuture()
                ? $subscription->expires_at
                : now();
            $validated['expires_at'] = $base->addDays($validated['extend_days']);
            unset($validated['extend_days']);
        }

        $subscription->update($validated);

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'household_id' => $subscription->household_id,
            'action' => 'admin_subscription_updated',
            'entity_type' => 'subscription',
            'entity_id' => $subscription->id,
            'description' => "Super Admin memperbarui status/durasi subscription #{$subscription->id}",
            'metadata' => $validated,
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
        ]);

        return response()->json([
            'message' => 'Subscription berhasil diperbarui.',
            'subscription' => $subscription->load('plan'),
        ]);
    }

    // ==========================================
    // TASK 16.9 — GLOBAL ACTIVITY LOG
    // ==========================================

    /**
     * System-wide activity log viewer for Super Admin.
     */
    public function activityLogs(Request $request)
    {
        $query = ActivityLog::with([
            'user:id,name,email',
            'household:id,name',
        ]);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('household_id')) {
            $query->where('household_id', $request->input('household_id'));
        }

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        $perPage = (int) $request->input('per_page', 20);
        $logs = $query->latest()->paginate($perPage);

        return response()->json($logs);
    }

    // ==========================================
    // TASK 16.10 — ADMIN SETTINGS
    // ==========================================

    /**
     * Get platform settings for Super Admin.
     */
    public function getSettings()
    {
        $settings = SystemSetting::all()->pluck('value', 'key')->toArray();

        // Standard defaults
        $defaults = [
            'app_name' => 'SaaS Keuangan Keluarga',
            'maintenance_mode' => 'false',
            'allow_registration' => 'true',
            'support_email' => 'support@keuangankeluarga.com',
            'default_currency' => 'IDR',
        ];

        $merged = array_merge($defaults, $settings);

        return response()->json(['settings' => $merged]);
    }

    /**
     * Update platform settings.
     */
    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.*' => 'nullable|string',
        ]);

        foreach ($validated['settings'] as $key => $val) {
            SystemSetting::set($key, (string) $val);
        }

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'household_id' => null,
            'action' => 'admin_settings_updated',
            'entity_type' => 'system_setting',
            'entity_id' => null,
            'description' => 'Super Admin memperbarui pengaturan global platform',
            'metadata' => array_keys($validated['settings']),
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
        ]);

        return response()->json([
            'message' => 'Pengaturan platform berhasil disimpan.',
            'settings' => SystemSetting::all()->pluck('value', 'key')->toArray(),
        ]);
    }
}

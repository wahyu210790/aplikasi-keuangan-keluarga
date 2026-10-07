<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class HouseholdMemberController extends Controller
{
    /**
     * List members of a household.
     */
    public function index(Household $household, Request $request)
    {
        $user = $request->user();
        // Authorization: must be a member of the household
        $membership = HouseholdMember::where('household_id', $household->id)
            ->where('user_id', $user->id)
            ->first();
        if (! $membership) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $members = HouseholdMember::with('user:id,name,email')->where('household_id', $household->id)->get();

        $memberData = $members->map(function (HouseholdMember $m) {
            return [
                'id' => $m->id,
                'user_id' => $m->user_id,
                'name' => $m->user->name,
                'email' => $m->user->email,
                'role' => $m->role === HouseholdMember::ROLE_MEMBER_LEGACY ? HouseholdMember::ROLE_ADULT : $m->role,
            ];
        });

        return response()->json([
            'household' => [
                'id' => $household->id,
                'name' => $household->name,
            ],
            'members' => $memberData,
        ]);
    }

    /**
     * Add a new member to a household.
     */
    public function store(Household $household, Request $request)
    {
        $user = $request->user();
        // Must be owner
        $ownerMembership = HouseholdMember::where('household_id', $household->id)
            ->where('user_id', $user->id)
            ->where('role', 'household_owner')
            ->first();
        if (! $ownerMembership) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'exists:users,email'],
            'role' => ['sometimes', 'in:household_member,adult_member,child_member'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $validator->errors()], 422);
        }
        $validated = $validator->validated();

        $role = $validated['role'] ?? 'adult_member';

        // Find target user
        $targetUser = User::where('email', $validated['email'])->first();

        // Prevent self-add
        if ($targetUser->id === $user->id) {
            return response()->json(['message' => 'Cannot add yourself'], 422);
        }

        // Check duplicate membership
        $exists = HouseholdMember::where('household_id', $household->id)
            ->where('user_id', $targetUser->id)
            ->exists();
        if ($exists) {
            return response()->json(['message' => 'User already a member'], 422);
        }

        return DB::transaction(function () use ($household, $targetUser, $user, $role) {
            $member = HouseholdMember::create([
                'user_id' => $targetUser->id,
                'household_id' => $household->id,
                'role' => $role,
            ]);

            ActivityLog::create([
                'user_id' => $user->id,
                'household_id' => $household->id,
                'action' => 'household_member_added',
                'entity_type' => 'household_member',
                'entity_id' => $member->id,
                'description' => "Added member {$targetUser->email} to household {$household->id}",
                'created_at' => now(),
            ]);

            return response()->json([
                'message' => 'Member added',
                'member' => [
                    'id' => $member->id,
                    'user_id' => $member->user_id,
                    'role' => $member->role,
                ],
            ], 201);
        });
    }

    /**
     * Update a member's role.
     */
    public function update(Household $household, HouseholdMember $member, Request $request)
    {
        $user = $request->user();
        // Must be owner of this household
        $ownerMembership = HouseholdMember::where('household_id', $household->id)
            ->where('user_id', $user->id)
            ->where('role', 'household_owner')
            ->first();
        if (! $ownerMembership) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Ensure the member belongs to the household
        if ($member->household_id !== $household->id) {
            return response()->json(['message' => 'Member not in household'], 404);
        }

        // Prevent changing the owner role
        if ($member->role === 'household_owner') {
            return response()->json(['message' => 'Cannot modify owner role'], 422);
        }

        $validator = Validator::make($request->all(), [
            'role' => ['required', 'in:household_member,adult_member,child_member'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation errors', 'errors' => $validator->errors()], 422);
        }

        $newRole = $request->input('role');

        return DB::transaction(function () use ($member, $newRole, $user, $household) {
            $member->role = $newRole;
            $member->save();

            ActivityLog::create([
                'user_id' => $user->id,
                'household_id' => $household->id,
                'action' => 'household_member_updated',
                'entity_type' => 'household_member',
                'entity_id' => $member->id,
                'description' => "Updated member {$member->id} role to {$member->role}",
                'created_at' => now(),
            ]);

            return response()->json([
                'message' => 'Member role updated',
                'member' => [
                    'id' => $member->id,
                    'role' => $member->role,
                ],
            ]);
        });
    }

    /**
     * Remove a member from a household.
     */
    public function destroy(Household $household, HouseholdMember $member, Request $request)
    {
        $user = $request->user();
        // Must be owner
        $ownerMembership = HouseholdMember::where('household_id', $household->id)
            ->where('user_id', $user->id)
            ->where('role', 'household_owner')
            ->first();
        if (! $ownerMembership) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($member->household_id !== $household->id) {
            return response()->json(['message' => 'Member not in household'], 404);
        }

        if ($member->role === 'household_owner') {
            return response()->json(['message' => 'Cannot remove owner'], 422);
        }

        return DB::transaction(function () use ($member, $user, $household) {
            $memberId = $member->id;
            $member->delete();

            ActivityLog::create([
                'user_id' => $user->id,
                'household_id' => $household->id,
                'action' => 'household_member_removed',
                'entity_type' => 'household_member',
                'entity_id' => $memberId,
                'description' => "Removed member {$memberId} from household {$household->id}",
                'created_at' => now(),
            ]);

            return response()->json(['message' => 'Member removed']);
        });
    }
}

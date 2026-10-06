<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Models\HouseholdMember;
use App\Models\User;
use App\Services\QuotaEnforcementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class HouseholdInvitationController extends Controller
{
    /**
     * List invitations for a household.
     */
    public function index(Request $request, Household $household): JsonResponse
    {
        $invitations = HouseholdInvitation::where('household_id', $household->id)
            ->orderBy('id', 'desc')
            ->get();

        return response()->json(['invitations' => $invitations]);
    }

    /**
     * Create an invitation to join household.
     */
    public function store(Request $request, Household $household): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'role' => ['sometimes', 'in:household_owner,household_member'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Quota check
        if (!QuotaEnforcementService::canAddMember($household)) {
            return response()->json([
                'message' => 'Kuota anggota household telah mencapai batas maksimal paket langganan Anda.'
            ], 422);
        }

        $email = strtolower(trim($request->input('email')));
        $role = $request->input('role', 'household_member');

        $code = Str::upper(Str::random(8));

        $invitation = HouseholdInvitation::create([
            'household_id' => $household->id,
            'email' => $email,
            'code' => $code,
            'role' => $role,
            'expires_at' => now()->addDays(7),
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Undangan berhasil dibuat',
            'invitation' => $invitation,
        ], 201);
    }

    /**
     * Accept invitation code by authenticated user.
     */
    public function accept(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $code = strtoupper(trim($request->input('code')));

        $invitation = HouseholdInvitation::where('code', $code)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->first();

        if (!$invitation) {
            return response()->json([
                'message' => 'Kode undangan tidak valid atau telah kadaluarsa.'
            ], 404);
        }

        $user = $request->user();

        // Check if already a member
        $existing = HouseholdMember::where('household_id', $invitation->household_id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Anda sudah menjadi anggota dari household ini.'
            ], 422);
        }

        HouseholdMember::create([
            'user_id' => $user->id,
            'household_id' => $invitation->household_id,
            'role' => $invitation->role,
        ]);

        $invitation->status = 'accepted';
        $invitation->save();

        return response()->json([
            'message' => 'Berhasil bergabung dengan household!',
            'household_id' => $invitation->household_id,
        ]);
    }
}

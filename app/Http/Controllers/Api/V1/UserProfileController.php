<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserProfileController extends Controller
{
    /**
     * Get authenticated user profile.
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user()->only(['id', 'name', 'email', 'global_role', 'created_at']),
        ]);
    }

    /**
     * Update user profile (name, email).
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($request->filled('name')) {
            $user->name = trim($request->input('name'));
        }

        if ($request->filled('email')) {
            $user->email = strtolower(trim($request->input('email')));
        }

        $user->save();

        return response()->json([
            'message' => 'Profil berhasil diperbarui',
            'user' => $user->only(['id', 'name', 'email', 'global_role']),
        ]);
    }

    /**
     * Change user password.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (!Hash::check($request->input('current_password'), $user->password)) {
            return response()->json([
                'message' => 'Password lama tidak cocok.',
                'errors' => ['current_password' => ['Password lama tidak cocok.']],
            ], 422);
        }

        $user->password = Hash::make($request->input('new_password'));
        $user->save();

        return response()->json([
            'message' => 'Password berhasil diperbarui.',
        ]);
    }

    /**
     * Revoke all user Sanctum tokens (logout all devices).
     */
    public function revokeTokens(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Seluruh sesi perangkat berhasil dicabut.',
        ]);
    }
}

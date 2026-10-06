<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Handle user login and return Sanctum token.
     */
    public function login(Request $request)
    {
        // Validation
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Find user by email
        $user = User::where('email', $request->input('email'))->first();

        // Verify password
        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            return response()->json([
                'message' => 'Email atau password salah.',
            ], 422);
        }

        // Create Sanctum personal access token
        $token = $user->createToken('auth-token')->plainTextToken;

        // Simple activity log entry (household_id null per clarification)
        if (class_exists(ActivityLog::class)) {
            ActivityLog::create([
                'user_id' => $user->id,
                'household_id' => null,
                'action' => 'login',
            ]);
        }

        // Return response with limited user data
        return response()->json([
            'message' => 'Login berhasil.',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'global_role' => $user->global_role,
            ],
        ], 200);
    }

/**
     * Handle user logout by revoking the current access token.
     */
    public function logout(Request $request)
    {
        // Ensure the request is authenticated via auth:sanctum middleware.
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Retrieve the token used for this request.
        $currentToken = $request->user()->currentAccessToken();
        if ($currentToken) {
            // Delete only this token.
            $currentToken->delete();
        }

        // Log the logout activity.
        if (class_exists(ActivityLog::class)) {
            ActivityLog::create([
                'user_id' => $user->id,
                'household_id' => null,
                'action' => 'logout',
            ]);
        }

        return response()->json([
            'message' => 'Logout berhasil.',
        ], 200);
    }
    /**
     * Return the currently authenticated user.
     */
    public function me(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Load households with pivot role
        $households = $user->households->map(function ($h) {
            return [
                'id' => $h->id,
                'name' => $h->name,
                'role' => $h->pivot->role,
            ];
        })->values();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'global_role' => $user->global_role,
            ],
            'households' => $households,
        ], 200);
    }


}

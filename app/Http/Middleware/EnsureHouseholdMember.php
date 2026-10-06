<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\HouseholdMember;
use Symfony\Component\HttpFoundation\Response;

class EnsureHouseholdMember
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Ensure the request is authenticated
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Resolve the household model from the route parameter (may be id or model)
        $household = $request->route('household');
        // The implicit routeâ€‘model binding supplies a Household model instance.
        if (! $household) {
            return response()->json(['message' => 'Household not found.'], 404);
        }

        // Verify the user is a member of the household (owner or member)
        $membership = HouseholdMember::where('user_id', $user->id)
            ->where('household_id', $household->id)
            ->first();

        if (! $membership) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke household ini.'
            ], 403);
        }

        // Store the verified household on the request for downstream usage
        // No need to store household on request; controller receives it via binding

        return $next($request);
    }
}

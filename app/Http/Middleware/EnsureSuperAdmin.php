<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
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
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $user->isSuperAdmin() && $user->global_role !== 'super_admin') {
            return response()->json([
                'message' => 'Akses ditolak. Hanya super admin yang diizinkan.'
            ], 403);
        }

        return $next($request);
    }
}

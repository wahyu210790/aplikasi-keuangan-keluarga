<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Household;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogViewerController extends Controller
{
    /**
     * Display a listing of activity logs for the given household.
     */
    public function index(Request $request, Household $household): JsonResponse
    {
        $query = ActivityLog::with('user:id,name,email')
            ->where('household_id', $household->id);

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->input('entity_type'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('description', 'like', "%{$search}%");
        }

        if ($request->filled('start_date')) {
            $query->where('created_at', '>=', $request->input('start_date') . ' 00:00:00');
        }

        if ($request->filled('end_date')) {
            $query->where('created_at', '<=', $request->input('end_date') . ' 23:59:59');
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $logs = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json($logs);
    }

    /**
     * Get summary statistics of activity logs for the household.
     */
    public function summary(Request $request, Household $household): JsonResponse
    {
        $query = ActivityLog::where('household_id', $household->id);

        if ($request->filled('start_date')) {
            $query->where('created_at', '>=', $request->input('start_date') . ' 00:00:00');
        }

        if ($request->filled('end_date')) {
            $query->where('created_at', '<=', $request->input('end_date') . ' 23:59:59');
        }

        $totalLogs = (clone $query)->count();

        $actionCounts = (clone $query)
            ->selectRaw('action, count(*) as count')
            ->groupBy('action')
            ->pluck('count', 'action')
            ->toArray();

        return response()->json([
            'total_logs' => $totalLogs,
            'action_counts' => $actionCounts,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * List notifications for authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Notification::where('user_id', $user->id);

        if ($request->has('is_read')) {
            $isRead = filter_var($request->input('is_read'), FILTER_VALIDATE_BOOLEAN);
            $query->where('is_read', $isRead);
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $notifications = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json($notifications);
    }

    /**
     * Get unread notification count.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        $count = Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        return response()->json(['unread_count' => $count]);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $notification->is_read = true;
        $notification->read_at = now();
        $notification->save();

        return response()->json([
            'message' => 'Notifikasi ditandai telah dibaca.',
            'notification' => $notification,
        ]);
    }

    /**
     * Mark all notifications for user as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json(['message' => 'Semua notifikasi ditandai telah dibaca.']);
    }
}

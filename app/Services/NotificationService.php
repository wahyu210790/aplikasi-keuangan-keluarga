<?php

namespace App\Services;

use App\Models\Notification;

class NotificationService
{
    /**
     * Send a notification to a specific user.
     */
    public static function send(
        int $userId,
        ?int $householdId,
        string $type,
        string $title,
        string $message,
        ?array $data = null
    ): Notification {
        return Notification::create([
            'user_id' => $userId,
            'household_id' => $householdId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'is_read' => false,
        ]);
    }
}

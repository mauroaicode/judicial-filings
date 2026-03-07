<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Notification\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppUserNotificationController
{
    /**
     * Display a listing of app user notifications.
     */
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->notifications()
            ->paginate(20)
            ->through(fn (\Illuminate\Notifications\DatabaseNotification $notification): \Src\Application\AppUser\Notification\Resources\AppUserNotificationResource => \Src\Application\AppUser\Notification\Resources\AppUserNotificationResource::fromModel($notification));

        return response()->json($notifications);
    }

    /**
     * Get the count of unread app user notifications.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    /**
     * Mark a specific app user notification as read.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['success' => true]);
    }

    /**
     * Mark all app user notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        /** @var \Src\Domain\AppUser\Models\AppUser $user */
        $user = $request->user();
        $user->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    /**
     * Delete an app user notification.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->delete();

        return response()->json(['success' => true]);
    }
}

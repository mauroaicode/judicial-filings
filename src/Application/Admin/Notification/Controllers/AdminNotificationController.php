<?php

declare(strict_types=1);

namespace Src\Application\Admin\Notification\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Application\Admin\Notification\Resources\AdminNotificationResource;

class AdminNotificationController
{
    /**
     * Display a listing of admin notifications.
     */
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->notifications()
            ->paginate(20)
            ->through(fn (\Illuminate\Notifications\DatabaseNotification $notification): \Src\Application\Admin\Notification\Resources\AdminNotificationResource => AdminNotificationResource::fromModel($notification));

        return response()->json($notifications);
    }

    /**
     * Get the count of unread admin notifications.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    /**
     * Mark a specific admin notification as read.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['success' => true]);
    }

    /**
     * Mark all admin notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        /** @var \Src\Domain\AppUser\Models\AppUser $user */
        $user = $request->user();
        $user->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    /**
     * Delete an admin notification.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->delete();

        return response()->json(['success' => true]);
    }
}

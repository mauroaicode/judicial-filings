<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Notification\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Src\Application\AppUser\Notification\Resources\AppUserNotificationResource;
use Src\Domain\AppUser\Models\AppUser;

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
            ->through(fn (DatabaseNotification $notification): AppUserNotificationResource => AppUserNotificationResource::fromModel($notification));

        return response()->json($notifications);
    }

    /**
     * Get the count of unread and new app user notifications.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        /** @var AppUser $user */
        $user = $request->user();

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'new_count' => $user->notifications()->whereNull('opened_at')->count(),
        ]);
    }

    /**
     * Mark a specific app user notification as read.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        if (is_null($notification->opened_at)) {
            $notification->update(['opened_at' => now()]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Mark all app user notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        /** @var AppUser $user */
        $user = $request->user();
        $user->unreadNotifications()->update([
            'read_at' => now(),
            'opened_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Mark all app user notifications as opened.
     */
    public function markAllAsOpened(Request $request): JsonResponse
    {
        /** @var AppUser $user */
        $user = $request->user();
        $user->notifications()->whereNull('opened_at')->update(['opened_at' => now()]);

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

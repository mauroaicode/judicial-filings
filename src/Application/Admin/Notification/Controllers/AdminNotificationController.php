<?php

declare(strict_types=1);

namespace Src\Application\Admin\Notification\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Src\Application\Admin\Notification\Resources\AdminNotificationResource;
use Src\Domain\User\Models\User;

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
            ->through(fn (DatabaseNotification $notification): AdminNotificationResource => AdminNotificationResource::fromModel($notification));

        return response()->json($notifications);
    }

    /**
     * Unread (sin leer) y “nuevas” (campanita: aún no abiertas / sin opened_at).
     */
    public function unreadCount(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'new_count' => $user->notifications()->whereNull('opened_at')->count(),
        ]);
    }

    /**
     * Mark a specific admin notification as read.
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
     * Mark all admin notifications as read (y como abiertas).
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->unreadNotifications()->update([
            'read_at' => now(),
            'opened_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Solo quita el contador de “nuevas” (campanita abierta); no marca como leídas.
     */
    public function markAllAsOpened(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->notifications()->whereNull('opened_at')->update(['opened_at' => now()]);

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

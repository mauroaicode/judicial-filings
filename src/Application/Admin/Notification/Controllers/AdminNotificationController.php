<?php

declare(strict_types=1);

namespace Src\Application\Admin\Notification\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminNotificationController
{
    /**
     * Display a listing of admin notifications.
     */
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()->paginate(20);

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
        $request->user()->unreadNotifications->each(fn($n) => $n->markAsRead());

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

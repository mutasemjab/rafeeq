<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\RafiqNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = RafiqNotification::where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json([
            'data'        => NotificationResource::collection($notifications->items()),
            'unread_count'=> RafiqNotification::where('user_id', $request->user()->id)->whereNull('read_at')->count(),
            'meta'        => ['total' => $notifications->total(), 'last_page' => $notifications->lastPage()],
        ]);
    }

    public function markRead(Request $request, RafiqNotification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $notification->update(['read_at' => now()]);
        return response()->json(['message' => 'Marked as read.']);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        RafiqNotification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
        return response()->json(['message' => 'All notifications marked as read.']);
    }
}

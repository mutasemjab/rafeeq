<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserDeviceController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'push_token' => 'required|string|max:2048',
            'platform' => 'nullable|in:android,ios,web',
            'app_version' => 'nullable|string|max:50',
        ]);

        $device = UserDevice::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'push_token' => $data['push_token'],
            ],
            [
                'platform' => $data['platform'] ?? null,
                'app_version' => $data['app_version'] ?? null,
                'last_seen_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Push token registered.',
            'device' => [
                'id' => $device->id,
                'platform' => $device->platform,
                'push_token' => $device->push_token,
                'app_version' => $device->app_version,
                'last_seen_at' => $device->last_seen_at?->toISOString(),
            ],
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'push_token' => 'required|string|max:2048',
        ]);

        $deleted = UserDevice::query()
            ->where('user_id', $request->user()->id)
            ->where('push_token', $data['push_token'])
            ->delete();

        return response()->json([
            'message' => 'Push token removed.',
            'deleted' => $deleted,
        ]);
    }
}

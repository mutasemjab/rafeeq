<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function current(Request $request): JsonResponse
    {
        $sub = $request->user()->activeSubscription();
        if (!$sub) {
            return response()->json(['message' => 'No active subscription.'], 404);
        }
        return response()->json(new SubscriptionResource($sub));
    }

    public function history(Request $request): JsonResponse
    {
        $subs = $request->user()->subscriptions()->with('plan')->latest()->get();
        return response()->json(SubscriptionResource::collection($subs));
    }
}

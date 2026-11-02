<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function storeCatalog(Request $request, \App\Services\Payments\StoreSubscriptionService $service): JsonResponse
    {
        $data = $request->validate(['store' => 'required|in:apple,google']);
        return response()->json($service->catalog($request->user(), $data['store']));
    }

    public function verifyPurchase(Request $request, \App\Services\Payments\StoreSubscriptionService $service): JsonResponse
    {
        $data = $request->validate(['store' => 'required|in:apple,google', 'reference' => 'required|string|max:4096']);
        $sub = $service->synchronize($request->user(), $data['store'], $data['reference']);
        return response()->json(array_merge((new SubscriptionResource($sub))->resolve($request), [
            'has_pro' => app(\App\Services\AI\ChatModePolicy::class)->canUseExtraThinking($request->user()),
        ]));
    }

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

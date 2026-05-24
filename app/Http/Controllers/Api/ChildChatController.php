<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ChatRequest;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Services\AI\ChildChatService;
use Illuminate\Http\JsonResponse;

class ChildChatController extends Controller
{
    public function __construct(private ChildChatService $chatService) {}

    public function chat(ChatRequest $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        if ($conversation->status !== 'active') {
            return response()->json(['message' => 'Conversation is closed.'], 422);
        }

        // Daily message limit check (free plan)
        $user = $request->user();
        $sub  = $user->activeSubscription();
        $plan = $sub?->plan;

        if ($plan && $plan->ai_messages_per_day !== null) {
            $todayCount = $conversation->messages()
                ->where('role', 'user')
                ->whereDate('created_at', today())
                ->count();

            // Count across all conversations
            $todayCount = \App\Models\Message::whereHas('conversation', fn($q) => $q->where('user_id', $user->id))
                ->where('role', 'user')
                ->whereDate('created_at', today())
                ->count();

            if ($todayCount >= $plan->ai_messages_per_day) {
                return response()->json([
                    'message' => 'Daily message limit reached. Upgrade your plan for unlimited messages.',
                    'limit_reached' => true,
                ], 429);
            }
        }

        $message = $this->chatService->ask(
            $conversation,
            $request->input('message'),
            (int) $user->id,
            $conversation->child_id !== null ? (int) $conversation->child_id : null,
            $request->input('language', $user->preferred_language ?? 'en'),
        );

        return response()->json(new MessageResource($message));
    }
}

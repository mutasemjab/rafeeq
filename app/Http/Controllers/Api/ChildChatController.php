<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ChatRequest;
use App\Http\Resources\MessageResource;
use App\Jobs\ProcessChatTurnJob;
use App\Models\ChatTurn;
use App\Models\Conversation;
use App\Services\AI\ChatTurnService;
use App\Services\AI\ChatUsageService;
use App\Services\AI\ChildChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class ChildChatController extends Controller
{
    public function __construct(private ChildChatService $chatService, private ChatTurnService $turns)
    {
    }

    public function usage(Request $request, ChatUsageService $usage): JsonResponse
    {
        return response()->json($usage->forUser($request->user()));
    }

    public function chat(ChatRequest $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);
        $user = $request->user();
        $language = $request->input('language', $user->preferred_language ?? 'en') ?: 'en';
        [$turn, $dispatch] = $this->turns->accept($conversation, $user,
            $request->input('client_message_id') ?: (string) Str::uuid(), $request->input('message'), $language);
        if ($request->boolean('async')) {
            if ($dispatch) {
                try {
                    if (config('queue.default') === 'sync') {
                        ProcessChatTurnJob::dispatchAfterResponse((int) $turn->id, (int) $turn->attempt);
                    } else {
                        ProcessChatTurnJob::dispatch((int) $turn->id, (int) $turn->attempt);
                    }
                } catch (Throwable $exception) {
                    $this->turns->fail($turn, 'QUEUE_UNAVAILABLE', $language === 'ar'
                        ? 'تعذر بدء تجهيز الرد. رسالتك محفوظة؛ أعد المحاولة.' : 'Could not start preparing the reply. Your message is saved; please retry.');
                }
            }

            return $this->turnResponse($turn->fresh());
        }
        if ($dispatch) {
            $seconds = max(181, (int) config('ai.chat_request_timeout', 420));
            if (function_exists('set_time_limit')) {
                set_time_limit($seconds);
            }
            $this->turns->process($turn, $this->chatService);
            $turn->refresh();
        }
        if ($turn->status === 'completed') {
            return response()->json(new MessageResource($turn->assistantMessage));
        }
        if ($turn->status === 'failed') {
            return response()->json([
                'success' => false, 'stage' => $turn->error['stage'] ?? null,
                'message' => $turn->error['message'], 'error_code' => $turn->error['code'],
                'retryable' => true, 'client_message_id' => $turn->client_message_id,
            ], 503);
        }

        return $this->turnResponse($turn);
    }

    public function status(Request $request, Conversation $conversation, string $clientMessageId): JsonResponse
    {
        $this->authorize('view', $conversation);
        $turn = ChatTurn::where('conversation_id', $conversation->id)->where('client_message_id', $clientMessageId)->firstOrFail();
        $this->turns->expireStalled($turn);

        return $this->turnResponse($turn);
    }

    public function cancel(Request $request, Conversation $conversation, string $clientMessageId): JsonResponse
    {
        $this->authorize('view', $conversation);
        $turn = ChatTurn::where('conversation_id', $conversation->id)->where('client_message_id', $clientMessageId)->firstOrFail();
        $cancelled = ChatTurn::whereKey($turn->id)->where('status', 'queued')->update([
            'status' => 'cancelled', 'stage' => 'cancelled', 'completed_at' => now(), 'updated_at' => now(),
        ]);
        abort_unless($cancelled || $turn->status === 'cancelled', 409,
            $turn->language === 'ar' ? 'بدأ تجهيز الرد بالفعل؛ يمكنك الرجوع إليه لاحقًا.' : 'Reply preparation has already started; you can return to it later.');
        if ($message = $turn->userMessage) {
            $message->update(['metadata' => array_merge($message->metadata ?? [], ['delivery_status' => 'cancelled'])]);
        }

        return $this->turnResponse($turn->fresh());
    }

    private function turnResponse(ChatTurn $turn): JsonResponse
    {
        return response()->json(['turn' => [
            'client_message_id' => $turn->client_message_id, 'user_message_id' => $turn->user_message_id,
            'status' => $turn->status, 'stage' => $turn->stage,
            'message' => $turn->status === 'completed' && $turn->assistantMessage ? new MessageResource($turn->assistantMessage) : null,
            'error' => $turn->error, 'updated_at' => $turn->updated_at?->toISOString(),
        ]], in_array($turn->status, ['queued', 'processing'], true) ? 202 : 200);
    }
}

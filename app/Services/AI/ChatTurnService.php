<?php

namespace App\Services\AI;

use App\Exceptions\ChatServiceUnavailableException;
use App\Models\ChatTurn;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ChatTurnService
{
    public function __construct(private ChatUsageService $usage)
    {
    }

    /** Reserve the user's quota and persist exactly one user message before accepting work. */
    public function accept(Conversation $conversation, User $user, string $clientId, string $message, string $language): array
    {
        return DB::transaction(function () use ($conversation, $user, $clientId, $message, $language) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $conversation = Conversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $conversation->user_id === (int) $user->id, 403);
            app(PersonContextService::class)->assertCanProcess($conversation, (int) $user->id);
            $hash = hash('sha256', json_encode([$message, $language], JSON_UNESCAPED_UNICODE));
            $existing = ChatTurn::where('conversation_id', $conversation->id)->where('client_message_id', $clientId)->first();
            if ($existing) {
                abort_unless(hash_equals($existing->request_hash, $hash), 409,
                    $language === 'ar' ? 'هذا المعرّف مستخدم لرسالة أخرى.' : 'This message ID belongs to a different request.');
                $this->expireStalled($existing);
                if (! in_array($existing->status, ['failed', 'cancelled'], true)) {
                    return [$existing, false];
                }
                abort_unless($conversation->status === 'active', 422, $language === 'ar' ? 'المحادثة مغلقة.' : 'Conversation is closed.');
                $this->assertNoActiveTurn($conversation, $language);
                if (Message::where('conversation_id', $conversation->id)->where('role', 'user')
                    ->where('id', '>', $existing->user_message_id)->exists()) {
                    throw new HttpResponseException(response()->json([
                        'message' => $language === 'ar'
                            ? 'توجد رسائل أحدث في المحادثة. أرسل طلبك مجددًا كرسالة جديدة للحفاظ على ترتيب الحوار.'
                            : 'This conversation has newer messages. Send your request as a new message to preserve the conversation order.',
                        'error_code' => 'CHAT_TURN_SUPERSEDED', 'retryable' => false,
                    ], 409));
                }
                $existing->update(['status' => 'queued', 'stage' => 'queued', 'attempt' => $existing->attempt + 1,
                    'error' => null, 'started_at' => null, 'completed_at' => null]);
                $this->markUserMessage($existing, 'queued');

                return [$existing->fresh(), true];
            }
            abort_unless($conversation->status === 'active', 422, $language === 'ar' ? 'المحادثة مغلقة.' : 'Conversation is closed.');
            $this->assertNoActiveTurn($conversation, $language);
            $usage = $this->usage->forUser($user);
            if ($usage['remaining'] === 0) {
                abort(429, $language === 'ar' ? 'وصلت إلى حد الرسائل اليومي. يمكنك متابعة المحادثة بعد تجدد الرصيد.' : 'Daily message limit reached. Continue after your allowance resets.');
            }
            $userMessage = Message::create([
                'conversation_id' => $conversation->id, 'user_id' => $user->id,
                'child_id' => $conversation->child_id, 'role' => 'user', 'content' => $message,
                'metadata' => ['client_message_id' => $clientId, 'delivery_status' => 'queued', 'language' => $language],
            ]);
            $turn = ChatTurn::create([
                'conversation_id' => $conversation->id, 'user_id' => $user->id,
                'client_message_id' => $clientId, 'request_hash' => $hash, 'message' => $message,
                'language' => $language, 'user_message_id' => $userMessage->id,
                'status' => 'queued', 'stage' => 'queued',
            ]);
            $conversation->forceFill(['last_message_at' => now(),
                'title' => $conversation->title ?: mb_substr(trim($message), 0, 70),
            ])->save();

            return [$turn, true];
        });
    }

    private function assertNoActiveTurn(Conversation $conversation, string $language): void
    {
        $active = ChatTurn::where('conversation_id', $conversation->id)->whereIn('status', ['queued', 'processing'])->get();
        foreach ($active as $turn) {
            $this->expireStalled($turn);
        }
        abort_if($active->contains(fn ($turn) => in_array($turn->status, ['queued', 'processing'], true)), 409,
            $language === 'ar' ? 'جارٍ تجهيز الرد السابق. انتظر ظهوره قبل إرسال رسالة جديدة.' : 'The previous reply is still being prepared. Wait for it before sending another message.');
    }

    public function expireStalled(ChatTurn $turn): void
    {
        DB::transaction(function () use ($turn): void {
            $current = ChatTurn::whereKey($turn->id)->lockForUpdate()->first();
            if ($current === null) {
                return;
            }
            if (! $this->recoverPersistedReply($current)) {
                // Every stage renews the lease. Re-read under lock so an old
                // poll snapshot cannot expire a worker that just progressed.
                $queued = $current->status === 'queued';
                $leaseSeconds = $queued
                    ? max(30, (int) config('ai.chat_queue_timeout', 60))
                    : max(211, (int) config('ai.chat_request_timeout', 420) + 30);
                if (in_array($current->status, ['queued', 'processing'], true)
                    && $current->updated_at->lt(now()->subSeconds($leaseSeconds))) {
                    $this->fail($current, $queued ? 'QUEUE_UNAVAILABLE' : 'interrupted', $current->language === 'ar'
                        ? ($queued ? 'تعذر بدء تجهيز الرد الآن. رسالتك محفوظة؛ حاول مجددًا بعد قليل.' : 'توقف تجهيز الرد. أعد المحاولة لاستكمال نفس الرسالة.')
                        : ($queued ? 'Reply preparation could not start. Your message is saved; try again shortly.' : 'Reply preparation was interrupted. Retry to resume the same message.'));
                }
            }
            $turn->refresh();
        });
    }

    public function process(ChatTurn $turn, ChildChatService $chat, ?int $expectedAttempt = null, bool $alreadyResponded = false): void
    {
        $attempt = $expectedAttempt ?? (int) $turn->attempt;
        if ($attempt !== (int) $turn->attempt || $this->recoverPersistedReply($turn)) {
            return;
        }
        if (ChatTurn::whereKey($turn->id)->where('attempt', $attempt)->where('status', 'queued')->update([
            'status' => 'processing', 'stage' => 'checking_context', 'started_at' => now(), 'updated_at' => now(),
        ]) !== 1) {
            return;
        }
        $turn->refresh();
        $this->markUserMessage($turn, 'processing');
        try {
            $conversation = $turn->conversation;
            $user = User::find($turn->user_id);
            if (! $conversation || ! $user || ! $user->hasAiConsent() || $conversation->status !== 'active'
                || (int) $conversation->user_id !== (int) $turn->user_id || ! $turn->userMessage
                || ! app(PersonContextService::class)->canProcess($conversation, (int) $turn->user_id)) {
                $this->fail($turn, 'unavailable', $turn->language === 'ar' ? 'تعذر استكمال المحادثة. راجع موافقة الذكاء الاصطناعي وحالة المحادثة.' : 'Unable to continue. Check AI consent and conversation status.');

                return;
            }
            $reply = $chat->ask($conversation, $turn->message, (int) $turn->user_id,
                $conversation->child_id, $turn->language, $turn->userMessage,
                function (string $stage) use ($turn, $attempt): void {
                    $currentConversation = Conversation::find($turn->conversation_id);
                    if (! User::find($turn->user_id)?->hasAiConsent()
                        || ! $currentConversation || $currentConversation->status !== 'active'
                        || ! app(PersonContextService::class)->canProcess($currentConversation, (int) $turn->user_id)) {
                        throw new RuntimeException('Chat consent or conversation availability changed.');
                    }
                    if (ChatTurn::whereKey($turn->id)->where('attempt', $attempt)->where('status', 'processing')
                        ->update(['stage' => $stage, 'updated_at' => now()]) !== 1) {
                        throw new RuntimeException('This chat attempt is no longer active.');
                    }
                }, true);
            if ($this->complete($turn, $reply, $attempt)) {
                app(ConversationMaintenanceService::class)->dispatch($conversation, $alreadyResponded);
            }
        } catch (Throwable $error) {
            if ($this->recoverPersistedReply($turn)) {
                return;
            }
            Log::warning('ai.chat_turn.failed', ['turn_id' => $turn->id, 'exception' => $error::class]);
            $this->fail($turn, $error instanceof ChatServiceUnavailableException ? $error->errorCode() : 'AI_SERVICE_UNAVAILABLE',
                $error instanceof ChatServiceUnavailableException ? $error->getMessage() : ($turn->language === 'ar'
                    ? 'تعذر تجهيز الرد الآن. رسالتك محفوظة؛ أعد المحاولة.' : 'Could not prepare the reply. Your message is saved; please retry.'),
                $error instanceof ChatServiceUnavailableException ? $error->stage() : null, $attempt);
        }
    }

    private function complete(ChatTurn $turn, Message $reply, ?int $attempt = null): bool
    {
        if ($reply->role !== 'assistant' || (int) $reply->conversation_id !== (int) $turn->conversation_id
            || (int) $reply->user_id !== (int) $turn->user_id || (int) $reply->reply_to_message_id !== (int) $turn->user_message_id) {
            throw new RuntimeException('Assistant reply does not belong to this turn.');
        }

        return DB::transaction(function () use ($turn, $reply, $attempt): bool {
            $current = ChatTurn::whereKey($turn->id)->lockForUpdate()->first();
            if ($current === null || ($attempt !== null && (int) $current->attempt !== $attempt)) {
                return false;
            }
            if ($current->status === 'completed' && (int) $current->assistant_message_id === (int) $reply->id) {
                return false;
            }
            $reply->forceFill(['metadata' => array_merge($reply->metadata ?? [], [
                'client_message_id' => $turn->client_message_id, 'user_message_id' => $turn->user_message_id,
            ])])->save();
            $current->update(['status' => 'completed', 'stage' => 'completed', 'assistant_message_id' => $reply->id,
                'completed_at' => now(), 'error' => null]);
            $this->markUserMessage($current, 'completed');
            $turn->refresh();

            return true;
        });
    }

    public function fail(ChatTurn $turn, string $code, string $message, ?string $stage = null, ?int $attempt = null): void
    {
        if ($this->recoverPersistedReply($turn)) {
            return;
        }
        ChatTurn::whereKey($turn->id)->where('attempt', $attempt ?? $turn->attempt)->whereIn('status', ['queued', 'processing'])->update([
            'status' => 'failed', 'stage' => 'failed', 'error' => json_encode(['code' => $code, 'message' => $message, 'retryable' => true, 'stage' => $stage]),
            'completed_at' => now(), 'updated_at' => now(),
        ]);
        $turn->refresh();
        if ($turn->status === 'failed') {
            $this->markUserMessage($turn, 'failed');
        }
    }

    private function recoverPersistedReply(ChatTurn $turn): bool
    {
        if ($turn->user_message_id === null) {
            return false;
        }
        $reply = Message::where('reply_to_message_id', $turn->user_message_id)
            ->where('conversation_id', $turn->conversation_id)->where('user_id', $turn->user_id)
            ->where('role', 'assistant')->first();
        if (! $reply) {
            return false;
        }
        if ($turn->status !== 'completed' || (int) $turn->assistant_message_id !== (int) $reply->id) {
            $this->complete($turn, $reply);
        }

        return true;
    }

    private function markUserMessage(ChatTurn $turn, string $status): void
    {
        if ($message = $turn->userMessage) {
            $message->update(['metadata' => array_merge($message->metadata ?? [], ['delivery_status' => $status])]);
        }
    }
}

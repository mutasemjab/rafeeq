<?php

namespace App\Jobs;

use App\Models\ChatTurn;
use App\Services\AI\ChatTurnService;
use App\Services\AI\ChildChatService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessChatTurnJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 420;

    public bool $failOnTimeout = true;

    public function __construct(public int $turnId, public ?int $attempt = null)
    {
        $this->onQueue((string) config('ai.chat_queue', 'default'));
        $this->attempt ??= ChatTurn::find($turnId)?->attempt;
        $this->timeout = max(181, (int) config('ai.chat_request_timeout', 420));
    }

    public function handle(ChatTurnService $turns, ChildChatService $chat): void
    {
        if (function_exists('set_time_limit')) {
            set_time_limit($this->timeout);
        }
        if ($turn = ChatTurn::find($this->turnId)) {
            $turns->process($turn, $chat, $this->attempt, true);
        }
    }

    public function failed(?Throwable $exception): void
    {
        if ($turn = ChatTurn::find($this->turnId)) {
            app(ChatTurnService::class)->fail($turn, 'AI_SERVICE_UNAVAILABLE', $turn->language === 'ar'
                ? 'توقف تجهيز الرد. رسالتك محفوظة؛ أعد المحاولة.' : 'Reply preparation stopped. Your message is saved; please retry.', null, $this->attempt);
        }
    }
}

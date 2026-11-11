<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\AI\AiUsageMeter;
use App\Services\AI\OpenAiConfigResolver;
use App\Services\AI\PromptCachePolicy;
use App\Services\AI\Providers\OpenAiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiCostAccountingTest extends TestCase
{
    use RefreshDatabase;

    private function message(): Message
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_id' => $user->id]);
        return Message::create(['user_id' => $user->id, 'conversation_id' => $conversation->id, 'role' => 'user', 'content' => 'PRIVATE QUESTION']);
    }

    public function test_each_provider_stage_is_recorded_and_linked_without_private_text(): void
    {
        config(['ai.openai_api_key' => 'test-key', 'openai.api_key' => 'test-key', 'ai.embedding_dimensions' => 2]);
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => '{"ok":true}']]],
                'usage' => ['prompt_tokens' => 3000, 'completion_tokens' => 100, 'prompt_tokens_details' => ['cached_tokens' => 2000, 'cache_write_tokens' => 500]]]),
            'api.openai.com/v1/responses' => Http::response(['output_text' => 'PRIVATE ANSWER', 'usage' => ['input_tokens' => 2000, 'output_tokens' => 100]]),
            'api.openai.com/v1/embeddings' => Http::response(['data' => [['index' => 0, 'embedding' => [0.1, 0.2]]], 'usage' => ['prompt_tokens' => 10]]),
        ]);
        $message = $this->message();
        $meter = app(AiUsageMeter::class);
        $previous = $meter->begin($message);
        $provider = new OpenAiProvider(new OpenAiConfigResolver());
        $provider->chatJson([['role' => 'system', 'content' => 'Exact instructions'], ['role' => 'user', 'content' => 'PRIVATE QUESTION']], ['ok' => 'boolean'], ['model' => 'gpt-6-astra', 'schema_name' => 'coverage']);
        $provider->answer([['role' => 'user', 'content' => 'PRIVATE QUESTION']], ['model' => 'gpt-6-astra']);
        $provider->embedding('PRIVATE QUERY');
        $meter->restore($previous);
        $rows = DB::table('ai_request_usages')->get();
        $this->assertCount(3, $rows);
        $this->assertCount(1, $rows->pluck('run_id')->unique());
        $this->assertSame([$message->id], $rows->pluck('user_message_id')->unique()->all());
        $this->assertSame(['estimated'], $rows->pluck('pricing_status')->unique()->values()->all());
        $this->assertSame(2000, $rows[0]->cached_input_tokens);
        $this->assertStringNotContainsString('PRIVATE', $rows->toJson());
        $this->artisan('ai:cost-report', ['--json' => true])->assertExitCode(0);
        $this->artisan('ai:cost-report', ['--by' => 'day', '--json' => true])->assertExitCode(0);
        $this->artisan('ai:cost-report', ['--by' => 'stage', '--json' => true])->assertExitCode(0);
        // Account cleanup erases links while preserving anonymous financial counters.
        $message->user->delete();
        $this->assertSame(0, DB::table('ai_request_usages')->whereNotNull('user_id')->count());
    }

    public function test_failures_retries_and_nested_background_work_do_not_leak_question_scope(): void
    {
        config(['ai.openai_api_key' => 'test-key', 'openai.api_key' => 'test-key']);
        Http::fake(['api.openai.com/v1/responses' => Http::response(['error' => 'Unavailable'], 503)]);
        $meter = app(AiUsageMeter::class); $message = $this->message();
        $previous = $meter->begin($message, 'chat', 2);
        try {
            (new OpenAiProvider(new OpenAiConfigResolver()))->answer([['role' => 'user', 'content' => 'Q']], ['web_search' => true, 'web_search_required' => true]);
        } catch (\RuntimeException $error) {}
        $nested = $meter->begin($message, 'maintenance');
        $meter->record('chat_json', 'gpt-6-astra', 'summary', 1, true, ['usage' => ['prompt_tokens' => 100, 'completion_tokens' => 10]]);
        $meter->restore($nested);
        $meter->record('responses', 'gpt-6-astra', null, 1, true, ['usage' => ['input_tokens' => 100, 'output_tokens' => 10]]);
        $meter->restore($previous);
        $meter->record('responses', 'gpt-6-astra', null, 1, false);
        $rows = DB::table('ai_request_usages')->get();
        $this->assertNull($rows[0]->estimated_cost_usd);
        $this->assertSame('usage_missing', $rows[0]->pricing_status);
        $this->assertSame(2, $rows[0]->attempt);
        $this->assertSame('maintenance', $rows[1]->purpose);
        $this->assertSame($rows[0]->question_key, $rows[1]->question_key);
        $this->assertSame($rows[0]->run_id, $rows[2]->run_id);
        $this->assertNull($rows[3]->user_message_id);
    }

    public function test_deletion_during_a_provider_call_keeps_only_anonymous_cost(): void
    {
        $message = $this->message();
        $meter = app(AiUsageMeter::class);
        $previous = $meter->begin($message);
        $message->delete();
        $meter->record('responses', 'gpt-6-astra', null, 1, true, ['usage' => ['input_tokens' => 100, 'output_tokens' => 10]]);
        $meter->restore($previous);
        $row = DB::table('ai_request_usages')->first();
        $this->assertNotNull($row);
        $this->assertNull($row->user_message_id);
        $this->assertNull($row->user_id);
        $this->assertNull($row->conversation_id);
        $this->assertSame('estimated', $row->pricing_status);
    }

    public function test_local_replies_record_zero_without_masking_a_missing_provider_cost(): void
    {
        $meter = app(AiUsageMeter::class);
        $previous = $meter->begin($this->message());
        $meter->finishQuestion(true);
        $meter->restore($previous);
        $previous = $meter->begin($this->message());
        $meter->record('responses', 'gpt-6-astra', null, 1, false);
        $meter->finishQuestion(false);
        $meter->restore($previous);
        $rows = DB::table('ai_request_usages')->get();
        $this->assertCount(2, $rows);
        $this->assertSame('local_no_api', $rows[0]->pricing_status);
        $this->assertEquals(0, $rows[0]->estimated_cost_usd);
        $this->assertNull($rows[1]->estimated_cost_usd);
    }

    public function test_cache_policy_preserves_every_source_and_instruction_and_does_not_cache_user_suffix(): void
    {
        $policy = app(PromptCachePolicy::class);
        $messages = [['role' => 'system', 'content' => 'ALL instructions.'], ['role' => 'user', 'content' => 'ALL books, conditions and case facts.']];
        $chat = $policy->apply(['model' => 'gpt-6-astra', 'messages' => $messages, 'reasoning_effort' => 'low'], 'chat/completions');
        $this->assertSame($messages[0]['content'], $chat['messages'][0]['content'][0]['text']);
        $this->assertSame($messages[1], $chat['messages'][1]);
        $this->assertSame('low', $chat['reasoning_effort']);
        $this->assertSame('explicit', $chat['prompt_cache_options']['mode']);
        $response = $policy->apply(['model' => 'gpt-6-astra', 'instructions' => $messages[0]['content'], 'input' => [$messages[1]]], 'responses');
        $this->assertSame($messages[0]['content'], $response['input'][0]['content'][0]['text']);
        $this->assertSame($messages[1], $response['input'][1]);
        config(['ai_costs.optimize_prompt_cache' => false]);
        $payload = ['model' => 'gpt-6-astra', 'messages' => $messages];
        $this->assertSame($payload, $policy->apply($payload, 'chat/completions'));
    }
}

<?php

namespace Tests\Feature;

use App\Jobs\ProcessChatTurnJob;
use App\Models\ChatTurn;
use App\Models\Conversation;
use App\Models\PersonProfile;
use App\Models\User;
use App\Services\AI\ChatTurnService;
use App\Services\AI\ChildChatService;
use App\Services\AI\Contracts\LlmProviderInterface;
use App\Services\AI\Providers\FakeLlmProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AllAgesChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();
        config(['ai.provider' => 'fake', 'ai.embedding_provider' => 'fake',
            'ai.web_search_enabled' => false, 'ai.openai_web_search_enabled' => false,
            'ai.default_medical_sources' => [], 'queue.default' => 'sync']);
        Http::preventStrayRequests();
    }

    public function test_real_chat_graph_preserves_adult_context_and_unknown_pathway_answer(): void
    {
        $user = User::factory()->create(['ai_consent_accepted_at' => now()]);
        $person = $this->person($user);
        $conversation = Conversation::factory()->create(['user_id' => $user->id, 'person_profile_id' => $person->id]);
        $test = $this;
        $provider = new class($test) extends FakeLlmProvider {
            private int $turn = 0;
            public function __construct(private AllAgesChatTest $test) {}

            public function chatJson(array $messages, array $schema = [], array $options = []): array
            {
                $result = parent::chatJson($messages, $schema, $options);
                if (isset($schema['properties']['allowed']) || isset($schema['allowed'])) {
                    $result['category'] = 'hearing';
                }
                if (isset($schema['properties']['pathway_ids'])) {
                    $payload = json_decode($messages[1]['content'], true);
                    $this->test->assertSame('adult', $payload['child_context']['profile']['age_group']);
                    $this->test->assertSame(480, $payload['child_context']['profile']['age_months']);
                    $this->test->assertNotContains('gateway:G02', array_column($payload['pathway_context']['gateway_questions'], 'id'));
                    $result['pathway_ids'] = ['hearing'];
                    $result['case_specific'] = true;
                    $result['evidence_required'] = false; // Second turn only explains app capabilities.
                    $result['node_answers'] = [];
                    if (++$this->turn === 1) {
                        $result['action'] = 'ask_clarification';
                        $result['information_sufficient'] = false;
                        $result['question'] = 'Have you had a hearing assessment before?';
                        $result['question_node_id'] = 'hearing:D03';
                        $result['question_target'] = 'hearing_assessment';
                    } else {
                        $this->test->assertSame('hearing:D03', $payload['pathway_context']['pending_question_id']);
                        $result['node_answers'] = [['node_id' => 'hearing:D03', 'status' => 'unknown',
                            'value' => 'Unknown', 'evidence' => "I don't know"]];
                    }
                }

                return $result;
            }
        };
        $this->app->instance(LlmProviderInterface::class, $provider);
        $this->actingAs($user, 'user-api');
        $url = '/api/v1/conversations/'.$conversation->id.'/chat';
        $this->postJson($url, ['message' => 'I have difficulty hearing.', 'language' => 'en', 'client_message_id' => 'adult-turn-1'])
            ->assertOk()->assertJsonPath('response_type', 'clarification')
            ->assertJsonPath('case_state.pathway_state.pending_question_id', 'hearing:D03');
        $this->postJson($url, ['message' => "I don't know. What can this app help me with?", 'language' => 'en', 'client_message_id' => 'adult-turn-2'])
            ->assertOk()->assertJsonPath('response_type', 'answer')
            ->assertJsonPath('case_state.pathway_state.answers.hearing:D03.status', 'unknown');
        $this->assertDatabaseCount('messages', 4);
        $this->assertSame(0, $conversation->messages()->whereNotNull('child_id')->count());
        $this->assertFalse($conversation->fresh()->case_state['pathway_state']['diagnostic_tool']);
        Http::assertNothingSent();
    }

    public function test_queued_turn_does_not_call_models_after_person_consent_is_revoked(): void
    {
        Bus::fake();
        $user = User::factory()->create(['ai_consent_accepted_at' => now()]);
        $person = $this->person($user);
        $conversation = Conversation::factory()->create(['user_id' => $user->id, 'person_profile_id' => $person->id]);
        $this->actingAs($user, 'user-api')->postJson('/api/v1/conversations/'.$conversation->id.'/chat',
            ['message' => 'Help me use the app', 'language' => 'en', 'async' => true, 'client_message_id' => 'revoked-person-turn'])->assertStatus(202);
        $person->update(['ai_consent_accepted_at' => null]);
        $turn = ChatTurn::sole();
        $chat = \Mockery::mock(ChildChatService::class);
        $chat->shouldReceive('ask')->never();
        app(ChatTurnService::class)->process($turn, $chat);
        $this->assertSame('failed', $turn->fresh()->status);
        $this->assertSame(0, $conversation->messages()->where('role', 'assistant')->count());
        Http::assertNothingSent();
    }

    private function person(User $user): PersonProfile
    {
        return $user->personProfiles()->create(['relationship' => 'self', 'age_months' => 480,
            'permission_attested_at' => now(), 'ai_consent_accepted_at' => now(),
            'persistence_consent_accepted_at' => now(), 'consent_version' => '1.0']);
    }
}

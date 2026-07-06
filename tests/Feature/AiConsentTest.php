<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\AI\ChildChatService;
use App\Services\Search\ChatAttachmentSearchService;
use App\Services\Search\Contracts\WebSearchServiceInterface;
use App\Services\Search\KnowledgeSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class AiConsentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();
        $this->user = User::factory()->create();
    }

    public function test_authenticated_user_can_save_ai_consent(): void
    {
        $this->actingAs($this->user, 'user-api')
            ->postJson('/api/v1/user/ai-consent', [
                'hasAiConsent' => true,
                'version' => '1.0',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'AI data-sharing consent saved successfully')
            ->assertJsonPath('data.hasAiConsent', true)
            ->assertJsonPath('data.version', '1.0');

        $this->assertNotNull($this->user->fresh()->ai_consent_accepted_at);
        $this->assertSame('1.0', $this->user->fresh()->ai_consent_version);
    }

    public function test_authenticated_user_can_get_ai_consent_status(): void
    {
        $this->user->forceFill([
            'ai_consent_accepted_at' => now(),
            'ai_consent_version' => '1.0',
        ])->save();

        $this->actingAs($this->user, 'user-api')
            ->getJson('/api/v1/user/ai-consent')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.hasAiConsent', true)
            ->assertJsonPath('data.version', '1.0');
    }

    public function test_ai_endpoint_returns_403_if_consent_is_missing(): void
    {
        $conversation = Conversation::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $this->actingAs($this->user, 'user-api')
            ->postJson("/api/v1/conversations/{$conversation->id}/chat", [
                'message' => 'Help me understand my child progress.',
            ])
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'AI data-sharing consent is required before using AI features.');
    }

    public function test_ai_endpoint_works_after_consent_is_accepted(): void
    {
        $conversation = Conversation::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $this->user->forceFill([
            'ai_consent_accepted_at' => now(),
            'ai_consent_version' => '1.0',
        ])->save();

        $this->mock(ChildChatService::class, function (MockInterface $mock) use ($conversation): void {
            $mock->shouldReceive('ask')
                ->once()
                ->andReturn(
                    Message::query()->create([
                        'conversation_id' => $conversation->id,
                        'role' => 'assistant',
                        'content' => 'Here is a helpful response.',
                    ])
                );
        });

        $this->actingAs($this->user, 'user-api')
            ->postJson("/api/v1/conversations/{$conversation->id}/chat", [
                'message' => 'Help me understand my child progress.',
            ])
            ->assertOk()
            ->assertJsonPath('role', 'assistant')
            ->assertJsonPath('content', 'Here is a helpful response.');
    }

    public function test_ai_endpoint_appends_visible_medical_resources_to_responses(): void
    {
        config([
            'ai.provider' => 'fake',
            'ai.web_search_enabled' => false,
        ]);

        $conversation = Conversation::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $this->user->forceFill([
            'ai_consent_accepted_at' => now(),
            'ai_consent_version' => '1.0',
        ])->save();

        $this->mock(ChatAttachmentSearchService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('search')->once()->andReturn([]);
        });

        $this->mock(KnowledgeSearchService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('search')->once()->andReturn([]);
        });

        $this->mock(WebSearchServiceInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('search')->never();
        });

        $response = $this->actingAs($this->user, 'user-api')
            ->postJson("/api/v1/conversations/{$conversation->id}/chat", [
                'message' => 'Can you suggest developmental wellness steps?',
            ])
            ->assertOk()
            ->assertJsonPath('role', 'assistant');

        $content = $response->json('content');
        $sources = $response->json('sources');

        $this->assertStringContainsString('Resources:', $content);
        $this->assertStringContainsString('https://www.cdc.gov/child-development/index.html', $content);
        $this->assertStringContainsString('https://medlineplus.gov/childdevelopment.html', $content);
        $this->assertContains('MED_SOURCE_1', array_column($sources, 'source_label'));
        $this->assertContains('MED_SOURCE_2', array_column($sources, 'source_label'));

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
        ]);
    }
}

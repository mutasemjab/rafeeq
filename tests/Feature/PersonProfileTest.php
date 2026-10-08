<?php

namespace Tests\Feature;

use App\Models\ChatAttachment;
use App\Models\Child;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\PersonProfile;
use App\Models\User;
use App\Services\AI\ChildMemoryManager;
use App\Services\AI\PersonContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PersonProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPassport();
        $this->user = User::factory()->create(['ai_consent_accepted_at' => now()]);
        $this->actingAs($this->user, 'user-api');
    }

    /** @dataProvider ages */
    public function test_profiles_cover_every_age_group(int $ageMonths, string $group): void
    {
        $this->postJson('/api/v1/person-profiles', array_merge($this->payload(), ['age_months' => $ageMonths]))
            ->assertCreated()->assertJsonPath('age_group', $group)->assertJsonPath('age_months', $ageMonths)
            ->assertJsonPath('consent.has_ai_consent', true)->assertJsonPath('consent.has_persistence_consent', true);
    }

    public static function ages(): array
    {
        return [[0, 'child'], [155, 'child'], [156, 'teen'], [215, 'teen'], [216, 'adult'], [779, 'adult'], [780, 'older_adult']];
    }

    public function test_unknown_age_is_preserved_and_future_dates_are_rejected(): void
    {
        $this->postJson('/api/v1/person-profiles', $this->payload())->assertCreated()->assertJsonPath('age_group', null);
        $this->postJson('/api/v1/person-profiles', array_merge($this->payload(), ['birth_date' => now()->addDay()->format('Y-m-d')]))
            ->assertUnprocessable()->assertJsonValidationErrors('birth_date');
    }

    public function test_profile_requires_explicit_permission_and_persistence_consent(): void
    {
        $this->postJson('/api/v1/person-profiles', array_merge($this->payload(), ['has_persistence_consent' => false]))
            ->assertUnprocessable()->assertJsonValidationErrors('has_persistence_consent');
        $this->postJson('/api/v1/person-profiles', array_merge($this->payload(), ['has_permission' => false]))
            ->assertUnprocessable()->assertJsonValidationErrors('has_permission');
        $this->assertDatabaseCount('person_profiles', 0);
    }

    public function test_other_accounts_cannot_read_modify_or_delete_person_profiles(): void
    {
        $id = $this->postJson('/api/v1/person-profiles', $this->payload())->json('id');
        $this->actingAs(User::factory()->create(), 'user-api');
        foreach (['get', 'put', 'delete'] as $method) {
            $this->{$method.'Json'}('/api/v1/person-profiles/'.$id, [])->assertForbidden();
        }
        $this->postJson('/api/v1/person-profiles/'.$id.'/consent', ['has_ai_consent' => false])->assertForbidden();
        $this->postJson('/api/v1/conversations', ['person_profile_id' => $id])->assertForbidden();
        $this->getJson('/api/v1/person-profiles')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_person_conversation_has_no_child_and_cannot_mix_subject_identifiers(): void
    {
        $id = $this->postJson('/api/v1/person-profiles', array_merge($this->payload(), ['age_months' => 480]))->json('id');
        $this->postJson('/api/v1/conversations', ['person_profile_id' => $id])->assertCreated()
            ->assertJsonPath('person_profile_id', $id)->assertJsonPath('child_id', null);
        $child = Child::factory()->create(['user_id' => $this->user->id]);
        $this->postJson('/api/v1/conversations', ['person_profile_id' => $id, 'child_id' => $child->id])->assertUnprocessable();
    }

    public function test_legacy_child_link_requires_ownership_and_keeps_old_child_context_identifier(): void
    {
        $child = Child::factory()->create(['user_id' => $this->user->id]);
        $id = $this->postJson('/api/v1/person-profiles', array_merge($this->payload(), ['relationship' => 'caregiver', 'legacy_child_id' => $child->id]))
            ->assertCreated()->json('id');
        $this->postJson('/api/v1/conversations', ['person_profile_id' => $id])->assertCreated()->assertJsonPath('child_id', $child->id);
        $otherChild = Child::factory()->create();
        $this->postJson('/api/v1/person-profiles', array_merge($this->payload(), ['relationship' => 'caregiver', 'legacy_child_id' => $otherChild->id]))->assertUnprocessable();
        $this->postJson('/api/v1/person-profiles', array_merge($this->payload(), ['legacy_child_id' => $child->id]))->assertUnprocessable();
    }

    public function test_revocation_blocks_new_chat_without_saving_message_and_can_be_restored_explicitly(): void
    {
        $id = $this->postJson('/api/v1/person-profiles', $this->payload())->json('id');
        $conversationId = $this->postJson('/api/v1/conversations', ['person_profile_id' => $id])->json('id');
        $this->postJson('/api/v1/person-profiles/'.$id.'/consent', ['has_ai_consent' => false])->assertOk();
        $this->postJson('/api/v1/conversations/'.$conversationId.'/chat', ['message' => 'Help with sleep', 'language' => 'en'])->assertForbidden();
        $this->assertDatabaseCount('messages', 0);
        $this->postJson('/api/v1/person-profiles/'.$id.'/consent', ['has_ai_consent' => true])->assertUnprocessable();
        $this->postJson('/api/v1/person-profiles/'.$id.'/consent', ['has_ai_consent' => true, 'has_permission' => true, 'consent_version' => '1.0'])
            ->assertOk()->assertJsonPath('consent.has_ai_consent', true);
    }

    public function test_person_context_is_isolated_from_other_persons_and_child_profiles(): void
    {
        $first = $this->postJson('/api/v1/person-profiles', array_merge($this->payload(), ['age_months' => 480]))->json('id');
        $second = $this->postJson('/api/v1/person-profiles', $this->payload())->json('id');
        Conversation::factory()->create(['user_id' => $this->user->id, 'person_profile_id' => $second, 'summary' => 'OTHER PERSON SECRET']);
        $conversation = Conversation::factory()->create(['user_id' => $this->user->id, 'person_profile_id' => $first]);
        $context = app(PersonContextService::class)->build($conversation, $this->user->id);
        $this->assertSame(480, $context['profile']['age_months']);
        $this->assertSame('adult', $context['profile']['age_group']);
        $this->assertStringNotContainsString('OTHER PERSON SECRET', json_encode($context));
    }

    public function test_person_deletion_removes_messages_memories_and_files_but_keeps_other_people(): void
    {
        Storage::fake('private');
        $id = $this->postJson('/api/v1/person-profiles', $this->payload())->json('id');
        $other = $this->postJson('/api/v1/person-profiles', $this->payload())->json('id');
        $conversation = Conversation::factory()->create(['user_id' => $this->user->id, 'person_profile_id' => $id]);
        $message = Message::create(['conversation_id' => $conversation->id, 'user_id' => $this->user->id, 'role' => 'user', 'content' => 'I prefer writing']);
        app(ChildMemoryManager::class)->applyPersonCandidates($id, $this->user->id, $message->id, [[
            'key' => 'communication.preference', 'type' => 'preference', 'title' => 'Preference', 'content' => 'Prefers writing',
            'evidence' => 'I prefer writing', 'confidence' => 1, 'fact_status' => 'preference',
        ]]);
        Storage::disk('private')->put('person-test/report.txt', 'private report');
        ChatAttachment::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $this->user->id,
            'storage_disk' => 'private', 'file_path' => 'person-test/report.txt']);
        $conversation->delete();
        $this->deleteJson('/api/v1/person-profiles/'.$id)->assertOk();
        $this->assertDatabaseMissing('conversations', ['id' => $conversation->id]);
        $this->assertDatabaseMissing('messages', ['id' => $message->id]);
        $this->assertDatabaseCount('person_memories', 0);
        Storage::disk('private')->assertMissing('person-test/report.txt');
        $this->assertDatabaseHas('person_profiles', ['id' => $other]);
    }

    public function test_account_deletion_also_cleans_new_person_data(): void
    {
        $id = $this->postJson('/api/v1/person-profiles', $this->payload())->json('id');
        Conversation::factory()->create(['user_id' => $this->user->id, 'person_profile_id' => $id]);
        $this->deleteJson('/api/v1/user/account')->assertOk();
        $this->assertDatabaseCount('person_profiles', 0);
        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_support_catalogue_exposes_all_supplied_routes_and_two_authored_intake_gaps(): void
    {
        $this->getJson('/api/v1/support-pathways')->assertOk()->assertJsonCount(41, 'data')
            ->assertJsonPath('supplied_pathway_count', 39)->assertJsonPath('clinical_evidence', false);
    }

    private function payload(): array
    {
        return ['relationship' => 'self', 'has_permission' => true, 'has_persistence_consent' => true,
            'has_ai_consent' => true, 'consent_version' => '1.0'];
    }
}

<?php

namespace Tests\Unit;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\PersonProfile;
use App\Models\User;
use App\Services\AI\ChildMemoryManager;
use App\Services\AI\PersonContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonMemoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_owned_user_evidence_creates_person_memory_and_later_corrections_win(): void
    {
        $user = User::factory()->create(['ai_consent_accepted_at' => now()]);
        $first = $this->person($user);
        $other = $this->person($user);
        $conversation = Conversation::factory()->create(['user_id' => $user->id, 'person_profile_id' => $first->id]);
        $source = Message::create(['user_id' => $user->id, 'conversation_id' => $conversation->id, 'role' => 'user', 'content' => 'I am 30 years old']);
        $manager = new ChildMemoryManager();
        $candidate = ['key' => 'person.age', 'type' => 'general', 'title' => 'Reported age', 'content' => '30 years',
            'evidence' => '30 years', 'confidence' => 1, 'fact_status' => 'confirmed_by_caregiver'];
        $this->assertSame(0, $manager->applyPersonCandidates($other->id, $user->id, $source->id, [$candidate]));
        $this->assertSame(1, $manager->applyPersonCandidates($first->id, $user->id, $source->id, [$candidate]));
        $correction = Message::create(['user_id' => $user->id, 'conversation_id' => $conversation->id, 'role' => 'user', 'content' => 'Correction: I am 40 years old']);
        $updated = array_replace($candidate, ['content' => '40 years', 'evidence' => '40 years']);
        $this->assertSame(1, $manager->applyPersonCandidates($first->id, $user->id, $correction->id, [$updated]));
        $this->assertSame(0, $manager->applyPersonCandidates($first->id, $user->id, $source->id, [$candidate]));
        $context = app(PersonContextService::class)->build($conversation, $user->id);
        $this->assertSame('40 years', $context['memories'][0]['content']);
        $this->assertSame('30 years', $context['memories'][0]['metadata']['history'][0]['content']);
        $this->assertDatabaseCount('child_memories', 0);
    }

    public function test_revoked_consent_and_assistant_output_cannot_create_memory(): void
    {
        $user = User::factory()->create(['ai_consent_accepted_at' => now()]);
        $person = $this->person($user);
        $conversation = Conversation::factory()->create(['user_id' => $user->id, 'person_profile_id' => $person->id]);
        $candidate = ['key' => 'goal', 'content' => 'Goal', 'evidence' => 'Goal', 'confidence' => 1, 'fact_status' => 'goal'];
        $message = Message::create(['user_id' => $user->id, 'conversation_id' => $conversation->id, 'role' => 'assistant', 'content' => 'Goal']);
        $manager = new ChildMemoryManager();
        $this->assertSame(0, $manager->applyPersonCandidates($person->id, $user->id, $message->id, [$candidate]));
        $message->update(['role' => 'user']);
        $person->update(['ai_consent_accepted_at' => null]);
        $this->assertSame(0, $manager->applyPersonCandidates($person->id, $user->id, $message->id, [$candidate]));
        $this->assertDatabaseCount('person_memories', 0);
    }

    private function person(User $user): PersonProfile
    {
        return $user->personProfiles()->create(['relationship' => 'self', 'permission_attested_at' => now(),
            'ai_consent_accepted_at' => now(), 'persistence_consent_accepted_at' => now(), 'consent_version' => '1.0']);
    }
}
